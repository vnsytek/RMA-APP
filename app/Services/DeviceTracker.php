<?php

namespace App\Services;

use App\Models\Device;
use App\Models\RmaTicket;
use Illuminate\Support\Collection;

/**
 * Builds {@see DeviceSummary} objects for every device with a fixed number of queries.
 */
class DeviceTracker
{
    /**
     * @return Collection<int, DeviceSummary> keyed by device id
     */
    public function all(): Collection
    {
        $devices = Device::query()->with('productModel.brand', 'productModel.deviceType')->get()->keyBy('id');
        $tickets = RmaTicket::query()
            ->with('customer')
            ->orderByDesc('received_date')
            ->orderByDesc('ticket_no')
            ->get();

        $byDevice = [];

        foreach ($tickets as $ticket) {
            $byDevice[$ticket->device_id][$ticket->id] = $ticket;

            if ($ticket->returned_device_id !== null) {
                $byDevice[$ticket->returned_device_id][$ticket->id] = $ticket;
            }
        }

        $children = $devices->groupBy('replaced_from_device_id');

        return $devices->map(function (Device $device) use ($devices, $byDevice, $children) {
            $own = [];

            foreach ($this->lineage($device, $devices) as $id) {
                $own += $byDevice[$id] ?? [];
            }

            $list = collect($own)
                ->sort(fn (RmaTicket $a, RmaTicket $b) => [$b->received_date->timestamp, $b->ticket_no] <=> [$a->received_date->timestamp, $a->ticket_no])
                ->values();

            return $this->summarize($device, $list, $devices->get($device->replaced_from_device_id), $children->get($device->id, collect()));
        });
    }

    public function for(Device $device): DeviceSummary
    {
        $device->loadMissing('productModel.brand', 'productModel.deviceType', 'replacedFrom', 'replacements.productModel.brand', 'replacements.productModel.deviceType');
        $ids = $device->lineageIds();

        $tickets = RmaTicket::query()
            ->where(fn ($query) => $query->whereIn('device_id', $ids)->orWhereIn('returned_device_id', $ids))
            ->with('customer', 'device', 'returnedDevice', 'warrantyItems')
            ->orderByDesc('received_date')
            ->orderByDesc('ticket_no')
            ->get();

        return $this->summarize($device, $tickets, $device->replacedFrom, $device->replacements);
    }

    /**
     * @param  Collection<int, RmaTicket>  $tickets
     * @param  Collection<int, Device>  $replacements
     */
    private function summarize(Device $device, Collection $tickets, ?Device $replacedFrom, Collection $replacements): DeviceSummary
    {
        return new DeviceSummary(
            device: $device,
            tickets: $tickets,
            customer: $tickets->first()?->customer,
            customerIds: $tickets->pluck('customer_id')->unique()->values(),
            openTicket: $tickets->first(fn (RmaTicket $ticket) => ! $ticket->isClosed()),
            repairWarranty: RepairWarranty::fromTickets($tickets),
            replacedFrom: $replacedFrom,
            replacements: $replacements->values(),
        );
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @return list<int>
     */
    private function lineage(Device $device, Collection $devices): array
    {
        $ids = [];

        for ($current = $device; $current !== null && ! in_array($current->id, $ids, true); $current = $devices->get($current->replaced_from_device_id)) {
            $ids[] = $current->id;
        }

        return $ids;
    }
}
