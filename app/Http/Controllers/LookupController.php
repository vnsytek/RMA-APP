<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\RmaWarrantyItem;
use App\Services\DeviceTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Small JSON endpoints used by the ticket form.
 */
class LookupController extends Controller
{
    public function models(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_type_id' => ['required', 'integer'],
            'brand_id' => ['required', 'integer'],
        ]);

        return response()->json(
            ProductModel::active()
                ->where('device_type_id', $data['device_type_id'])
                ->where('brand_id', $data['brand_id'])
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        );
    }

    public function device(Request $request, DeviceTracker $tracker): JsonResponse
    {
        $serial = trim((string) $request->validate(['serial' => ['required', 'string', 'max:100']])['serial']);

        $devices = Device::query()->where('serial_number', $serial)->with('productModel')->get();

        return response()->json($devices->map(function (Device $device) use ($tracker) {
            $summary = $tracker->for($device);
            $last = $summary->lastTicket();
            $warranty = $summary->repairWarranty;

            return [
                'id' => $device->id,
                'product_model_id' => $device->product_model_id,
                'device_type_id' => $device->productModel->device_type_id,
                'brand_id' => $device->productModel->brand_id,
                'name' => $device->displayName(),
                'tickets_count' => $summary->tickets->count(),
                'last_ticket' => $last ? ['ticket_no' => $last->ticket_no, 'received_date' => vn_date($last->received_date), 'url' => route('tickets.show', $last)] : null,
                'customer_id' => $summary->customer?->id,
                'replaced' => $summary->replacements->isNotEmpty(),
                'repair_warranty' => $warranty->level->isValid()
                    ? ['ends_on' => vn_date($warranty->endsOn), 'ticket_no' => $warranty->ticket->ticket_no, 'label' => $warranty->label()]
                    : null,
                'warranty_claims' => $summary->claimableTickets()->map(fn (RmaTicket $ticket) => [
                    'id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'url' => route('tickets.show', $ticket),
                    'returned_date' => vn_date($ticket->returned_date),
                    'ends_on' => vn_date($ticket->repairWarrantyEndsOn()),
                    'items' => $ticket->warrantyItems->map(fn (RmaWarrantyItem $item) => [
                        'description' => $item->description,
                        'months' => $item->months,
                        'ends_on' => vn_date($item->endsOn()),
                        'level' => $item->level()->value,
                        'label' => $item->statusLabel(),
                    ])->all(),
                    'exclusions' => $ticket->warrantyExclusionList(),
                ])->all(),
                'url' => route('devices.show', $device),
            ];
        }));
    }
}
