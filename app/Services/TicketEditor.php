<?php

namespace App\Services;

use App\Enums\WarrantyStatus;
use App\Models\Device;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lets staff correct a mistake made at intake (wrong serial, typo'd fault description, wrong date)
 * without cancelling and re-opening the ticket. Every change is logged to rma_ticket_edits so the
 * ticket's timeline still shows what was first recorded and what was corrected, by whom and when.
 */
class TicketEditor
{
    /**
     * @param  array{serial_number: string, product_model_id?: ?int, warranty_status: string, fault_description: string, received_date: string}  $data
     */
    public function correct(RmaTicket $ticket, array $data, User $user, ?string $note): void
    {
        DB::transaction(function () use ($ticket, $data, $user, $note): void {
            $this->correctDevice($ticket, $data['serial_number'], $data['product_model_id'] ?? null, $user, $note);

            $warranty = WarrantyStatus::from($data['warranty_status']);

            if ($warranty !== $ticket->warranty_status) {
                $this->log($ticket, $user, 'Tình trạng bảo hành', $ticket->warranty_status->label(), $warranty->label(), $note);
                $ticket->warranty_status = $warranty;
            }

            if ($data['fault_description'] !== $ticket->fault_description) {
                $this->log($ticket, $user, 'Lỗi khách báo', $ticket->fault_description, $data['fault_description'], $note);
                $ticket->fault_description = $data['fault_description'];
            }

            if ($data['received_date'] !== $ticket->received_date->toDateString()) {
                $label = match (true) {
                    $ticket->kind()->isOnsite() => 'Ngày yêu cầu hãng',
                    $ticket->kind()->repairsAtCustomer() => 'Ngày yêu cầu',
                    default => 'Ngày nhận',
                };
                $this->log($ticket, $user, $label, vn_date($ticket->received_date), vn_date(Carbon::parse($data['received_date'])), $note);
                $ticket->received_date = $data['received_date'];
            }

            $ticket->save();
        });
    }

    /**
     * The device is a model + serial pair, so a correction to either points the ticket at the matching
     * device (created when it does not exist yet). The log says what changed: just the serial, or the product.
     */
    private function correctDevice(RmaTicket $ticket, string $serial, ?int $modelId, User $user, ?string $note): void
    {
        $serial = Str::squish($serial);
        $current = $ticket->device;
        $modelId ??= $current->product_model_id;
        $modelChanged = $modelId !== $current->product_model_id;

        if (! $modelChanged && strcasecmp($serial, $current->serial_number) === 0) {
            return;
        }

        $device = Device::firstOrCreate(['product_model_id' => $modelId, 'serial_number' => $serial]);

        if ($modelChanged) {
            $this->log($ticket, $user, 'Thiết bị', "{$current->displayName()} · {$current->serial_number}", "{$device->load('productModel')->displayName()} · {$serial}", $note);
        } else {
            $this->log($ticket, $user, 'Serial / thiết bị', $current->serial_number, $serial, $note);
        }

        $ticket->device_id = $device->id;
    }

    private function log(RmaTicket $ticket, User $user, string $field, ?string $old, ?string $new, ?string $note): void
    {
        $ticket->edits()->create([
            'user_id' => $user->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'note' => $note,
        ]);
    }
}
