<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Device;
use App\Models\RmaTicket;
use Illuminate\Support\Collection;

/**
 * What the device pages show about one machine, derived from its tickets.
 */
final readonly class DeviceSummary
{
    /**
     * @param  Collection<int, RmaTicket>  $tickets  newest first, including tickets of the devices it replaced
     * @param  Collection<int, int>  $customerIds
     * @param  Collection<int, Device>  $replacements
     */
    public function __construct(
        public Device $device,
        public Collection $tickets,
        public ?Customer $customer,
        public Collection $customerIds,
        public ?RmaTicket $openTicket,
        public RepairWarranty $repairWarranty,
        public ?Device $replacedFrom,
        public Collection $replacements,
    ) {}

    public function lastTicket(): ?RmaTicket
    {
        return $this->tickets->first();
    }

    /**
     * Repairs whose Sang Y warranty is still running today, newest first: what a returning customer can claim.
     *
     * @return Collection<int, RmaTicket>
     */
    public function claimableTickets(): Collection
    {
        return $this->tickets->filter(fn (RmaTicket $ticket) => $ticket->repairWarrantyIsActive())->values();
    }

    public function isSwapped(): bool
    {
        return $this->replacedFrom !== null || $this->replacements->isNotEmpty();
    }
}
