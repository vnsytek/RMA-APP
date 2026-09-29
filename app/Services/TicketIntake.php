<?php

namespace App\Services;

use App\Enums\AttachmentStage;
use App\Enums\ShipmentOutcome;
use App\Enums\TicketKind;
use App\Enums\WarrantyStatus;
use App\Models\Customer;
use App\Models\Device;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketIntake
{
    public function __construct(
        private TicketNumberGenerator $numbers,
        private CatalogResolver $catalog,
        private AttachmentStorage $attachments,
    ) {}

    /**
     * Open a new RMA ticket, creating the customer, catalog entries and device when they are new.
     *
     * @param  array<string, mixed>  $data  validated input from StoreRmaTicketRequest
     * @param  list<UploadedFile>  $photos  photos of the device as the customer hands it over, plus any documents
     */
    public function open(array $data, array $photos, User $user): RmaTicket
    {
        $kind = TicketKind::from($data['kind']);

        if ($kind->takesDeviceIn() && ! AttachmentStorage::containsPhoto($photos)) {
            throw ValidationException::withMessages(['photos' => AttachmentStorage::missingPhotoMessage(AttachmentStage::Intake)]);
        }

        return DB::transaction(function () use ($data, $photos, $user, $kind): RmaTicket {
            $receivedDate = Carbon::parse($data['received_date']);

            $ticket = RmaTicket::create([
                'ticket_no' => $this->numbers->next(today()),
                'service_type' => $kind->serviceType(),
                'original_service_type' => $kind->serviceType(),
                'onsite_location' => $kind->onsiteLocation(),
                'warranty_status' => WarrantyStatus::from($data['warranty_status']),
                'customer_id' => $this->resolveCustomer($data)->id,
                'device_id' => $this->resolveDevice($data)->id,
                'technician_id' => $data['technician_id'] ?? null,
                'fault_description' => $data['fault_description'],
                'accessories' => $kind->takesDeviceIn() ? ($data['accessories'] ?? null) : null,
                'received_date' => $receivedDate,
                'status' => $kind->initialStatus(),
                'note' => $data['note'] ?? null,
                'claim_ticket_id' => $kind === TicketKind::Repair ? ($data['claim_ticket_id'] ?? null) : null,
                'created_by' => $user->id,
            ]);

            if ($kind->isOnsite()) {
                $ticket->shipments()->create([
                    'service_center_id' => $data['service_center_id'],
                    'vendor_case_no' => $data['vendor_case_no'] ?? null,
                    'appointment_date' => $data['appointment_date'] ?? null,
                    'sent_date' => $receivedDate,
                    'outcome' => ShipmentOutcome::Pending,
                    'created_by' => $user->id,
                ]);
            }

            $log = $ticket->statusLogs()->create([
                'from_status' => null,
                'to_status' => $ticket->status,
                'user_id' => $user->id,
            ]);

            $this->attachments->storeForStage($photos, $ticket, AttachmentStage::Intake, $user, $log);

            return $ticket;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): Customer
    {
        if (($data['customer_id'] ?? 'new') !== 'new') {
            return Customer::findOrFail($data['customer_id']);
        }

        return Customer::create([
            'name' => Str::squish($data['customer_name']),
            'contact_name' => $data['customer_contact'] ?? null,
            'phone' => Str::squish($data['customer_phone']),
            'address' => $data['customer_address'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDevice(array $data): Device
    {
        $type = $this->catalog->deviceType($data['device_type_id'], $data['new_device_type'] ?? null);
        $brand = $this->catalog->brand($data['brand_id'], $data['new_brand'] ?? null);
        $model = $this->catalog->productModel($data['product_model_id'], $data['new_model_code'] ?? null, $type, $brand);

        return Device::firstOrCreate([
            'product_model_id' => $model->id,
            'serial_number' => Str::squish($data['serial_number']),
        ]);
    }
}
