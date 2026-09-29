<?php

namespace App\Http\Requests;

use App\Enums\AttachmentStage;
use App\Enums\TicketKind;
use App\Enums\WarrantyStatus;
use App\Models\RmaTicket;
use App\Services\AttachmentStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRmaTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $kind = TicketKind::tryFrom((string) $this->input('kind'));
        $newType = $this->input('device_type_id') === 'new';
        $newBrand = $this->input('brand_id') === 'new';
        $newModel = $this->input('product_model_id') === 'new';
        $newCustomer = $this->input('customer_id', 'new') === 'new';

        return [
            'warranty_status' => ['required', Rule::enum(WarrantyStatus::class)],
            'kind' => ['required', Rule::enum(TicketKind::class)],
            'serial_number' => ['required', 'string', 'max:100'],

            'device_type_id' => $newType ? ['required'] : ['required', 'integer', Rule::exists('device_types', 'id')],
            'new_device_type' => [Rule::requiredIf($newType), 'nullable', 'string', 'max:100'],
            'brand_id' => $newBrand ? ['required'] : ['required', 'integer', Rule::exists('brands', 'id')],
            'new_brand' => [Rule::requiredIf($newBrand), 'nullable', 'string', 'max:100'],
            'product_model_id' => $newModel
                ? ['required']
                : ['required', 'integer', Rule::exists('product_models', 'id')
                    ->where('brand_id', $this->integer('brand_id'))
                    ->where('device_type_id', $this->integer('device_type_id'))],
            'new_model_code' => [Rule::requiredIf($newModel), 'nullable', 'string', 'max:100'],

            'customer_id' => $newCustomer ? ['required'] : ['required', 'integer', Rule::exists('customers', 'id')],
            'customer_name' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:255'],
            'customer_phone' => [Rule::requiredIf($newCustomer), 'nullable', 'string', 'max:20'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],

            'fault_description' => ['required', 'string', 'max:2000'],
            'accessories' => ['nullable', 'string', 'max:255'],
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:2000'],

            'service_center_id' => [Rule::requiredIf($kind?->isOnsite() === true), 'nullable', 'integer', Rule::exists('service_centers', 'id')->where('is_active', true)],
            'vendor_case_no' => ['nullable', 'string', 'max:50'],
            'appointment_date' => ['nullable', 'date', 'after_or_equal:received_date'],

            'claim_ticket_id' => ['nullable', 'integer', Rule::exists('rma_tickets', 'id')->whereNull('deleted_at')],

            ...AttachmentStorage::uploadRules('photos', AttachmentStage::Intake, $kind?->takesDeviceIn() ?? true),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['warranty_status', 'kind', 'product_model_id'])) {
                    return;
                }

                if ($this->input('warranty_status') === WarrantyStatus::OutOfWarranty->value && $this->input('kind') !== TicketKind::Repair->value) {
                    $validator->errors()->add('kind', 'Máy hết bảo hành chỉ lập được phiếu sửa chữa.');
                }

                $catalogIsNew = $this->input('device_type_id') === 'new' || $this->input('brand_id') === 'new';

                if ($catalogIsNew && $this->input('product_model_id') !== 'new') {
                    $validator->errors()->add('product_model_id', 'Loại hoặc hãng mới chưa có model nào, hãy nhập mã model mới.');
                }
            },
            function (Validator $validator): void {
                if (blank($this->input('claim_ticket_id')) || $validator->errors()->hasAny(['claim_ticket_id', 'serial_number', 'kind'])) {
                    return;
                }

                $claim = RmaTicket::with('device', 'returnedDevice')->find($this->integer('claim_ticket_id'));

                if ($this->input('kind') !== TicketKind::Repair->value) {
                    $validator->errors()->add('claim_ticket_id', 'Khách yêu cầu bảo hành sửa chữa của Sang Y: chọn hình thức "Sửa chữa".');
                } elseif (mb_strtolower($claim->currentDevice()->serial_number) !== mb_strtolower(trim((string) $this->input('serial_number')))) {
                    $validator->errors()->add('claim_ticket_id', "Phiếu {$claim->ticket_no} không phải của máy có serial này.");
                } elseif (! $claim->repairWarrantyIsActive()) {
                    $validator->errors()->add('claim_ticket_id', "Bảo hành sửa chữa của phiếu {$claim->ticket_no} đã hết hạn.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warranty_status' => 'tình trạng bảo hành',
            'kind' => 'hình thức xử lý',
            'serial_number' => 'số serial',
            'device_type_id' => 'loại thiết bị',
            'new_device_type' => 'tên loại thiết bị mới',
            'brand_id' => 'hãng',
            'new_brand' => 'tên hãng mới',
            'product_model_id' => 'model',
            'new_model_code' => 'mã model mới',
            'customer_id' => 'khách hàng',
            'customer_name' => 'tên khách',
            'customer_phone' => 'số điện thoại',
            'customer_contact' => 'người liên hệ',
            'fault_description' => 'lỗi khách báo',
            'accessories' => 'phụ kiện',
            'received_date' => TicketKind::tryFrom((string) $this->input('kind')) === TicketKind::Onsite ? 'ngày yêu cầu hãng' : 'ngày nhận máy',
            'technician_id' => 'nhân viên phụ trách',
            'service_center_id' => 'hãng / TTBH',
            'vendor_case_no' => 'mã hồ sơ hãng',
            'appointment_date' => 'ngày hẹn hãng',
            'photos' => 'ảnh tình trạng máy',
            'claim_ticket_id' => 'phiếu bảo hành gốc',
            'photos.*' => 'tệp đính kèm',
        ];
    }
}
