<?php

namespace App\Services;

use App\Enums\ClaimResult;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Enums\ShipmentOutcome;
use App\Enums\TicketAction;
use App\Enums\TicketResult;
use App\Models\Device;
use App\Models\RmaQuoteItem;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketWorkflow
{
    public function __construct(private AttachmentStorage $attachments) {}

    /**
     * @return list<TicketAction>
     */
    public function availableActions(RmaTicket $ticket): array
    {
        if ($ticket->isClosed()) {
            return [];
        }

        return array_values(array_filter(TicketAction::cases(), fn (TicketAction $action) => $this->allows($ticket, $action)));
    }

    public function allows(RmaTicket $ticket, TicketAction $action): bool
    {
        if (! in_array($ticket->kind(), $action->kinds(), true) || ! in_array($ticket->status, $action->fromStatuses(), true)) {
            return false;
        }

        return match ($action) {
            TicketAction::ResendToCenter => $ticket->result === TicketResult::WarrantyCenter,
            TicketAction::ClaimCovered, TicketAction::ClaimRejected => $ticket->claimPending(),
            TicketAction::MarkFree, TicketAction::SendQuote, TicketAction::Scrap => ! $ticket->claimPending(),
            default => true,
        };
    }

    /**
     * Run a workflow step: check the business rules, apply it and log the status change.
     * Photos are filed under the step's stage and linked to its log entry.
     *
     * @param  array<string, mixed>  $input  validated input from {@see TicketAction::rules()}
     * @param  list<UploadedFile>  $photos
     */
    public function perform(RmaTicket $ticket, TicketAction $action, array $input, User $user, array $photos = []): void
    {
        if (! $this->allows($ticket, $action)) {
            throw ValidationException::withMessages([
                'action' => "Không thể \"{$action->label()}\" khi phiếu đang ở trạng thái \"{$ticket->status->label()}\".",
            ]);
        }

        $this->guard($ticket, $action, $input, $photos);

        DB::transaction(function () use ($ticket, $action, $input, $user, $photos): void {
            $from = $ticket->status;

            $this->apply($ticket, $action, $input, $user);

            $ticket->status = $action->targetStatus();
            $ticket->save();

            $log = $ticket->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $ticket->status,
                'user_id' => $user->id,
                'note' => $input['note'] ?? null,
            ]);

            if ($stage = $action->photoStage()) {
                $this->attachments->storeForStage($photos, $ticket, $stage, $user, $log);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $photos
     */
    private function guard(RmaTicket $ticket, TicketAction $action, array $input, array $photos): void
    {
        $errors = [];

        if ($action->requiresPhoto() && ! AttachmentStorage::containsPhoto($photos)) {
            $errors['photos'] = AttachmentStorage::missingPhotoMessage($action->photoStage());
        }
        $notBefore = function (string $field, Carbon $limit, string $what) use ($input, &$errors): void {
            if (isset($input[$field]) && Carbon::parse($input[$field])->lt($limit)) {
                $errors[$field] = "Ngày không được trước {$what} ({$limit->format('d/m/Y')}).";
            }
        };

        switch ($action) {
            case TicketAction::ReceiveFromCenter:
            case TicketAction::CenterRejected:
                $shipment = $ticket->pendingShipment;

                if ($shipment === null) {
                    $errors['back_date'] = 'Phiếu không có lần gửi TTBH nào đang chờ.';
                } else {
                    $notBefore('back_date', $shipment->sent_date, 'ngày gửi TTBH');
                }
                break;

            case TicketAction::SendToCenter:
            case TicketAction::ResendToCenter:
                $notBefore('sent_date', $ticket->received_date, 'ngày nhận máy');
                break;

            case TicketAction::VendorDoneAtCustomer:
            case TicketAction::VendorDoneAtSangy:
                $notBefore('done_date', $ticket->received_date, 'ngày nhận / yêu cầu');
                break;

            case TicketAction::ClaimCovered:
                $claim = $ticket->claimTicket;
                $items = $claim->activeWarrantyItems();

                if ($claim->warrantyItems->isEmpty()) {
                    if (! $claim->repairWarrantyIsActive()) {
                        $errors['claim_item_id'] = "Bảo hành của phiếu {$claim->ticket_no} đã hết hạn.";
                    }
                } elseif ($items->isEmpty()) {
                    $errors['claim_item_id'] = "Phiếu {$claim->ticket_no} không còn hạng mục nào trong hạn bảo hành.";
                } elseif (! $items->contains('id', (int) ($input['claim_item_id'] ?? 0))) {
                    $errors['claim_item_id'] = 'Chọn hạng mục còn trong hạn bảo hành của phiếu '.$claim->ticket_no.'.';
                }
                break;

            case TicketAction::SendQuote:
                if ($ticket->quoteItems()->doesntExist()) {
                    $errors['quote'] = 'Thêm ít nhất một dòng vào bảng báo giá trước khi gửi.';
                }
                break;

            case TicketAction::ReturnToCustomer:
                $notBefore('returned_date', $ticket->received_date, 'ngày nhận máy');

                if ($ticket->is_chargeable && blank($ticket->erp_receipt_no)) {
                    $errors['erp_receipt_no'] = 'Phiếu có phí: cần lưu số chứng từ V223 trước khi trả khách.';
                }

                if ($ticket->is_chargeable && blank($ticket->erp_return_no)) {
                    $errors['erp_return_no'] = 'Phiếu có phí: cần lưu số chứng từ V233 trước khi trả khách.';
                }
                break;

            default:
                break;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function apply(RmaTicket $ticket, TicketAction $action, array $input, User $user): void
    {
        switch ($action) {
            case TicketAction::SendToCenter:
            case TicketAction::ResendToCenter:
                $ticket->shipments()->create([
                    'service_center_id' => $input['service_center_id'],
                    'vendor_case_no' => $input['vendor_case_no'] ?? null,
                    'sent_date' => $input['sent_date'],
                    'outcome' => ShipmentOutcome::Pending,
                    'note' => $input['note'] ?? null,
                    'created_by' => $user->id,
                ]);
                $ticket->result = null;
                break;

            case TicketAction::FixInhouse:
                $ticket->result = TicketResult::WarrantyInhouse;
                break;

            case TicketAction::ReceiveFromCenter:
                $replaced = $this->swapDevice($ticket, $input['new_serial'] ?? null, $input['new_model_id'] ?? null);
                $ticket->pendingShipment->update([
                    'back_date' => $input['back_date'],
                    'center_return_no' => $input['center_return_no'] ?? null,
                    'outcome' => $replaced ? ShipmentOutcome::Replaced : ShipmentOutcome::Repaired,
                ]);
                $ticket->result = TicketResult::WarrantyCenter;
                break;

            case TicketAction::CenterRejected:
                $ticket->pendingShipment->update([
                    'back_date' => $input['back_date'],
                    'outcome' => ShipmentOutcome::Rejected,
                    'note' => $input['note'],
                ]);
                $ticket->service_type = ServiceType::Repair;
                $ticket->result = null;
                break;

            case TicketAction::ClaimCovered:
                $ticket->quoteItems()->delete();
                $ticket->claim_result = ClaimResult::Covered;
                $ticket->claim_item_id = $ticket->claimTicket->warrantyItems->isEmpty() ? null : (int) $input['claim_item_id'];
                $ticket->claim_note = $input['note'] ?? null;
                $ticket->result = TicketResult::RepairWarranty;
                break;

            case TicketAction::ClaimRejected:
                $ticket->claim_result = ClaimResult::Rejected;
                $ticket->claim_note = $input['note'];
                break;

            case TicketAction::MarkFree:
                $ticket->quoteItems()->delete();
                $ticket->result = TicketResult::Free;
                $this->recordWarranty($ticket, $input);
                break;

            case TicketAction::SendQuote:
                $ticket->quote_status = QuoteStatus::Sent;
                $ticket->quoted_at = now();
                break;

            case TicketAction::Scrap:
                $ticket->quoteItems()->delete();
                $ticket->result = TicketResult::Scrap;
                $ticket->scrap_reason = $input['note'];
                break;

            case TicketAction::AcceptQuote:
                $ticket->quote_status = QuoteStatus::Accepted;
                $ticket->quote_decided_at = now();
                $ticket->is_chargeable = true;
                $ticket->charge_amount = $ticket->quoteItems()->get()->sum(fn (RmaQuoteItem $item) => $item->lineTotal());
                break;

            case TicketAction::RejectQuote:
                $ticket->quote_status = QuoteStatus::Rejected;
                $ticket->quote_decided_at = now();
                $ticket->result = TicketResult::QuoteRejected;
                break;

            case TicketAction::FinishRepair:
                $ticket->result = TicketResult::Repaired;
                $this->recordWarranty($ticket, $input);
                break;

            case TicketAction::VendorDoneAtCustomer:
            case TicketAction::VendorDoneAtSangy:
                $replaced = $this->swapDevice($ticket, $input['new_serial'] ?? null, $input['new_model_id'] ?? null);
                $shipment = $ticket->pendingShipment;
                $shipment?->update([
                    'back_date' => $input['done_date'],
                    'vendor_case_no' => filled($input['vendor_case_no'] ?? null) ? $input['vendor_case_no'] : $shipment->vendor_case_no,
                    'outcome' => $replaced ? ShipmentOutcome::Replaced : ShipmentOutcome::Repaired,
                ]);
                $ticket->result = TicketResult::OnsiteDone;

                if ($action === TicketAction::VendorDoneAtCustomer) {
                    $ticket->returned_date = $input['done_date'];
                }
                break;

            case TicketAction::ReturnToCustomer:
                $ticket->returned_date = $input['returned_date'];
                break;

            case TicketAction::Cancel:
                $ticket->pendingShipment?->update(['outcome' => ShipmentOutcome::Cancelled]);
                break;

            case TicketAction::Inspect:
                break;
        }
    }

    /**
     * Save the warranted items (rows with a number of months) and the "không bảo hành" list.
     * The ticket's overall warranty length is its longest item, so device pages and reports see one end date.
     *
     * @param  array<string, mixed>  $input
     */
    private function recordWarranty(RmaTicket $ticket, array $input): void
    {
        $quoteItemIds = $ticket->quoteItems()->pluck('id')->all();

        $rows = collect($input['warranty_items'] ?? [])
            ->filter(fn (array $row) => filled($row['description'] ?? null) && filled($row['months'] ?? null))
            ->map(fn (array $row) => [
                'rma_quote_item_id' => in_array((int) ($row['quote_item_id'] ?? 0), $quoteItemIds, true) ? (int) $row['quote_item_id'] : null,
                'description' => Str::squish($row['description']),
                'months' => (int) $row['months'],
            ]);

        $ticket->warrantyItems()->delete();
        $ticket->warrantyItems()->createMany($rows->all());
        $ticket->unsetRelation('warrantyItems');

        $ticket->repair_warranty_months = $rows->max('months');
        $ticket->warranty_exclusions = implode("\n", text_lines($input['warranty_exclusions'] ?? null)) ?: null;
    }

    /**
     * Record the device the vendor hands back when it is a different unit (new serial, and
     * possibly another model). Returns false when the vendor repaired the original device.
     */
    private function swapDevice(RmaTicket $ticket, ?string $serial, int|string|null $modelId): bool
    {
        $serial = Str::squish((string) $serial);
        $original = $ticket->device;
        $modelId = $modelId ? (int) $modelId : $original->product_model_id;

        if ($serial === '' || ($modelId === $original->product_model_id && mb_strtolower($serial) === mb_strtolower($original->serial_number))) {
            return false;
        }

        $replacement = Device::firstOrCreate(
            ['product_model_id' => $modelId, 'serial_number' => $serial],
            ['replaced_from_device_id' => $original->id],
        );

        $ticket->returned_device_id = $replacement->id;
        $ticket->setRelation('returnedDevice', $replacement);

        return true;
    }
}
