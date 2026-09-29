<?php

namespace App\Enums;

use App\Services\AttachmentStorage;
use App\Services\TicketWorkflow;
use Illuminate\Validation\Rule;

/**
 * Workflow steps staff can take on a ticket. {@see TicketWorkflow} applies them.
 */
enum TicketAction: string
{
    case Inspect = 'inspect';
    case SendToCenter = 'send-to-center';
    case FixInhouse = 'fix-inhouse';
    case ReceiveFromCenter = 'receive-from-center';
    case CenterRejected = 'center-rejected';
    case ResendToCenter = 'resend-to-center';
    case ClaimCovered = 'claim-covered';
    case ClaimRejected = 'claim-rejected';
    case MarkFree = 'mark-free';
    case SendQuote = 'send-quote';
    case Scrap = 'scrap';
    case AcceptQuote = 'accept-quote';
    case RejectQuote = 'reject-quote';
    case FinishRepair = 'finish-repair';
    case VendorDoneAtCustomer = 'vendor-done-at-customer';
    case VendorDoneAtSangy = 'vendor-done-at-sangy';
    case ReturnToCustomer = 'return-to-customer';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Inspect => 'Bắt đầu kiểm tra',
            self::SendToCenter => 'Gửi TTBH',
            self::FixInhouse => 'IT tự xử lý xong',
            self::ReceiveFromCenter => 'Nhận lại từ TTBH',
            self::CenterRejected => 'TTBH từ chối BH → chuyển sửa chữa',
            self::ResendToCenter => 'Vẫn lỗi – gửi TTBH lại',
            self::ClaimCovered => 'Trong phạm vi BH → sửa miễn phí',
            self::ClaimRejected => 'Ngoài phạm vi BH → báo giá',
            self::MarkFree => 'Lỗi đơn giản – Miễn phí',
            self::SendQuote => 'Gửi báo giá cho khách',
            self::Scrap => 'Báo phế',
            self::AcceptQuote => 'Khách đồng ý báo giá',
            self::RejectQuote => 'Khách không đồng ý',
            self::FinishRepair => 'Sửa xong',
            self::VendorDoneAtCustomer, self::VendorDoneAtSangy => 'Hãng đã xử lý xong',
            self::ReturnToCustomer => 'Trả khách',
            self::Cancel => 'Hủy phiếu',
        };
    }

    /**
     * Button style: primary, secondary or danger.
     */
    public function style(): string
    {
        return match ($this) {
            self::CenterRejected, self::ResendToCenter, self::ClaimRejected, self::Scrap, self::RejectQuote => 'secondary',
            self::Cancel => 'danger',
            default => 'primary',
        };
    }

    /**
     * @return list<TicketKind>
     */
    public function kinds(): array
    {
        return match ($this) {
            self::Inspect => [TicketKind::CarryIn, TicketKind::Repair],
            self::SendToCenter, self::FixInhouse, self::ReceiveFromCenter, self::CenterRejected, self::ResendToCenter => [TicketKind::CarryIn],
            self::ClaimCovered, self::ClaimRejected, self::MarkFree, self::SendQuote, self::Scrap, self::AcceptQuote,
            self::RejectQuote, self::FinishRepair => [TicketKind::Repair],
            self::VendorDoneAtCustomer => [TicketKind::Onsite],
            self::VendorDoneAtSangy => [TicketKind::OnsiteSangy],
            self::ReturnToCustomer => [TicketKind::OnsiteSangy, TicketKind::CarryIn, TicketKind::Repair],
            self::Cancel => TicketKind::cases(),
        };
    }

    /**
     * @return list<TicketStatus>
     */
    public function fromStatuses(): array
    {
        return match ($this) {
            self::Inspect => [TicketStatus::Received],
            self::SendToCenter, self::FixInhouse, self::ClaimCovered, self::ClaimRejected, self::MarkFree,
            self::SendQuote, self::Scrap => [TicketStatus::Inspecting],
            self::ReceiveFromCenter, self::CenterRejected => [TicketStatus::AtCenter],
            self::ResendToCenter, self::ReturnToCustomer => [TicketStatus::Ready],
            self::AcceptQuote, self::RejectQuote => [TicketStatus::Quoted],
            self::FinishRepair => [TicketStatus::Repairing],
            self::VendorDoneAtCustomer, self::VendorDoneAtSangy => [TicketStatus::Scheduled],
            self::Cancel => [TicketStatus::Scheduled, TicketStatus::Received, TicketStatus::Inspecting, TicketStatus::Quoted],
        };
    }

    public function targetStatus(): TicketStatus
    {
        return match ($this) {
            self::Inspect, self::CenterRejected, self::ClaimRejected => TicketStatus::Inspecting,
            self::SendToCenter, self::ResendToCenter => TicketStatus::AtCenter,
            self::FixInhouse, self::ReceiveFromCenter, self::ClaimCovered, self::MarkFree, self::Scrap, self::RejectQuote,
            self::FinishRepair, self::VendorDoneAtSangy => TicketStatus::Ready,
            self::SendQuote => TicketStatus::Quoted,
            self::AcceptQuote => TicketStatus::Repairing,
            self::VendorDoneAtCustomer => TicketStatus::Done,
            self::ReturnToCustomer => TicketStatus::Returned,
            self::Cancel => TicketStatus::Cancelled,
        };
    }

    /**
     * Steps that settle a warranty claim; the normal repair steps wait until one of them is taken.
     */
    public function decidesClaim(): bool
    {
        return $this === self::ClaimCovered || $this === self::ClaimRejected;
    }

    /**
     * Stage the photos uploaded with this step are filed under; null when the step takes no photos.
     */
    public function photoStage(): ?AttachmentStage
    {
        return match ($this) {
            self::Inspect, self::FixInhouse, self::ClaimCovered, self::ClaimRejected, self::MarkFree, self::SendQuote,
            self::Scrap, self::FinishRepair => AttachmentStage::Inspection,
            self::SendToCenter, self::ResendToCenter => AttachmentStage::SendToCenter,
            self::ReceiveFromCenter, self::CenterRejected => AttachmentStage::BackFromCenter,
            self::VendorDoneAtCustomer, self::VendorDoneAtSangy => AttachmentStage::VendorVisit,
            self::ReturnToCustomer => AttachmentStage::Return,
            self::Cancel => AttachmentStage::Other,
            self::AcceptQuote, self::RejectQuote => null,
        };
    }

    /**
     * Handing the device back to the customer needs a photo of its condition.
     */
    public function requiresPhoto(): bool
    {
        return $this->photoStage()?->requiresPhoto() ?? false;
    }

    /**
     * Whether the step may hand back a different device (new serial and possibly another model).
     */
    public function canSwapDevice(): bool
    {
        return in_array($this, [self::ReceiveFromCenter, self::VendorDoneAtCustomer, self::VendorDoneAtSangy], true);
    }

    /**
     * Form fields shown when the action is chosen.
     *
     * @return array<string, array{label: string, type: string, required: bool, hint?: string}>
     */
    public function fields(): array
    {
        $note = fn (string $label = 'Ghi chú', bool $required = false) => ['label' => $label, 'type' => 'textarea', 'required' => $required];
        $date = fn (string $label) => ['label' => $label, 'type' => 'date', 'required' => true];
        $text = fn (string $label) => ['label' => $label, 'type' => 'text', 'required' => false];
        $center = ['label' => 'Trung tâm bảo hành', 'type' => 'service_center', 'required' => true];
        $swap = [
            'new_serial' => ['label' => 'Serial máy trả (nếu hãng đổi máy)', 'type' => 'text', 'required' => false, 'hint' => 'Để trống nếu hãng sửa máy cũ'],
            'new_model_id' => ['label' => 'Model máy trả (nếu đổi sang sản phẩm khác)', 'type' => 'product_model', 'required' => false],
        ];
        $warranty = [
            'warranty_items' => ['label' => 'Hạng mục bảo hành sau sửa', 'type' => 'warranty_items', 'required' => false, 'hint' => 'Nhập số tháng cho hạng mục được bảo hành, để trống là không bảo hành. Tính từ ngày trả máy.'],
            'warranty_exclusions' => ['label' => 'Không bảo hành', 'type' => 'lines', 'required' => false, 'hint' => 'Mỗi dòng một mục, in lên phiếu trả. Lấy sẵn theo loại thiết bị, sửa riêng cho phiếu này được.'],
        ];

        return match ($this) {
            self::Inspect, self::SendQuote, self::AcceptQuote, self::RejectQuote => ['note' => $note()],
            self::SendToCenter => ['service_center_id' => $center, 'sent_date' => $date('Ngày gửi TTBH'), 'vendor_case_no' => $text('Mã hồ sơ của hãng / TTBH'), 'note' => $note()],
            self::ResendToCenter => ['service_center_id' => $center, 'sent_date' => $date('Ngày gửi lại'), 'vendor_case_no' => $text('Mã hồ sơ của hãng / TTBH'), 'note' => $note('Lỗi gì còn lại', true)],
            self::FixInhouse => ['note' => $note('Đã xử lý gì')],
            self::ClaimCovered => ['claim_item_id' => ['label' => 'Hạng mục được bảo hành', 'type' => 'claim_item', 'required' => true], 'note' => $note('Đã sửa gì')],
            self::ClaimRejected => ['note' => $note('Lý do ngoài phạm vi bảo hành', true)],
            self::MarkFree => [...$warranty, 'note' => $note('Đã xử lý gì')],
            self::FinishRepair => [...$warranty, 'note' => $note('Đã sửa gì')],
            self::ReceiveFromCenter => ['back_date' => $date('Ngày nhận về công ty'), 'center_return_no' => $text('Số phiếu trả của TTBH'), ...$swap, 'note' => $note()],
            self::CenterRejected => ['back_date' => $date('Ngày nhận về công ty'), 'note' => $note('Lý do TTBH từ chối', true)],
            self::Scrap => ['note' => $note('Lý do báo phế', true)],
            self::VendorDoneAtCustomer => ['done_date' => $date('Ngày hãng xử lý xong'), 'vendor_case_no' => $text('Mã hồ sơ của hãng'), ...$swap, 'note' => $note('Hãng đã làm gì')],
            self::VendorDoneAtSangy => ['done_date' => $date('Ngày hãng xử lý xong'), 'vendor_case_no' => $text('Mã hồ sơ của hãng'), ...$swap, 'note' => $note('Hãng đã làm gì')],
            self::ReturnToCustomer => ['returned_date' => $date('Ngày trả khách'), 'note' => $note()],
            self::Cancel => ['note' => $note('Lý do hủy', true)],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        $maxMonths = config('rma.max_repair_warranty_months');

        foreach ($this->fields() as $name => $field) {
            if ($field['type'] === 'warranty_items') {
                $rules += [
                    'warranty_items' => ['nullable', 'array', 'max:30'],
                    'warranty_items.*.quote_item_id' => ['nullable', 'integer'],
                    'warranty_items.*.description' => ['nullable', 'string', 'max:255', 'required_with:warranty_items.*.months'],
                    'warranty_items.*.months' => ['nullable', 'integer', 'min:1', 'max:'.$maxMonths],
                ];

                continue;
            }

            $rules[$name] = match ($field['type']) {
                'date' => ['required', 'date', 'before_or_equal:today'],
                'service_center' => ['required', 'integer', Rule::exists('service_centers', 'id')->where('is_active', true)],
                'product_model' => ['nullable', 'integer', Rule::exists('product_models', 'id')],
                'claim_item' => ['nullable', 'integer'],
                'lines' => ['nullable', 'string', 'max:2000'],
                'textarea' => [$field['required'] ? 'required' : 'nullable', 'string', 'max:1000'],
                default => ['nullable', 'string', $name === 'new_serial' ? 'max:100' : 'max:50'],
            };
        }

        if (isset($rules['new_model_id'])) {
            $rules['new_serial'][] = 'required_with:new_model_id';
        }

        if ($stage = $this->photoStage()) {
            $rules += AttachmentStorage::uploadRules('photos', $stage, $this->requiresPhoto());
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...array_map(fn (array $field) => mb_strtolower($field['label']), $this->fields()),
            'warranty_items.*.description' => 'hạng mục bảo hành',
            'warranty_items.*.months' => 'số tháng bảo hành',
            'photos' => 'ảnh',
            'photos.*' => 'tệp đính kèm',
        ];
    }
}
