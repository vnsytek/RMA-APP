<?php

namespace Database\Factories;

use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use App\Models\Customer;
use App\Models\Device;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RmaTicket>
 */
class RmaTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_no' => today()->format('ym').fake()->unique()->numerify('####'),
            'service_type' => TicketKind::Repair->serviceType(),
            'original_service_type' => TicketKind::Repair->serviceType(),
            'onsite_location' => null,
            'warranty_status' => WarrantyStatus::OutOfWarranty,
            'customer_id' => Customer::factory(),
            'device_id' => Device::factory(),
            'technician_id' => null,
            'fault_description' => fake()->sentence(),
            'received_date' => today(),
            'status' => TicketStatus::Received,
            'created_by' => User::factory(),
        ];
    }

    public function ofKind(TicketKind $kind): static
    {
        return $this->state(fn () => [
            'service_type' => $kind->serviceType(),
            'original_service_type' => $kind->serviceType(),
            'onsite_location' => $kind->onsiteLocation(),
            'warranty_status' => $kind->isWarranty() ? WarrantyStatus::InWarranty : WarrantyStatus::OutOfWarranty,
            'status' => $kind->initialStatus(),
        ]);
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
