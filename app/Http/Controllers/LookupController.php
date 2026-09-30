<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Device;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\RmaWarrantyItem;
use App\Services\DeviceTracker;
use App\Services\TaxCodeLookup;
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

    /**
     * Registered name and address for a tax code, plus the customer already saved with it, if any.
     */
    public function taxCode(Request $request, TaxCodeLookup $lookup): JsonResponse
    {
        $taxCode = TaxCodeLookup::normalize($request->query('code'));

        if ($taxCode === null || ! preg_match(TaxCodeLookup::pattern(), $taxCode)) {
            return response()->json(['result' => 'invalid', 'message' => 'Mã số thuế gồm 10 số, chi nhánh thêm "-" và 3 số (VD: 0801379534-001).'], 422);
        }

        $existing = Customer::where('tax_code', $taxCode)->first(['id', 'name']);
        $found = $lookup->find($taxCode);

        return response()->json([
            ...$found,
            'tax_code' => $taxCode,
            'existing' => $existing ? ['id' => $existing->id, 'name' => $existing->name, 'url' => route('customers.show', $existing)] : null,
            'message' => match ($found['result']) {
                TaxCodeLookup::FOUND => $found['active'] ? 'Đã điền tên và địa chỉ theo đăng ký thuế.' : 'Đã điền theo đăng ký thuế. Lưu ý: '.$found['status'].'.',
                TaxCodeLookup::NOT_FOUND => 'Không tìm thấy mã số thuế này. Kiểm tra lại số hoặc nhập tay.',
                default => 'Chưa tra được (mất mạng hoặc dịch vụ tra cứu đang bận). Thử lại sau ít phút hoặc nhập tay.',
            },
        ]);
    }

    public function device(Request $request, DeviceTracker $tracker): JsonResponse
    {
        $serial = trim((string) $request->validate(['serial' => ['required', 'string', 'max:100']])['serial']);

        $devices = Device::query()->where('serial_number', $serial)->with('productModel')->get();

        $user = $request->user();

        return response()->json($devices->map(function (Device $device) use ($tracker, $user) {
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
                'last_ticket' => $last ? ['ticket_no' => $last->ticket_no, 'received_date' => vn_date($last->received_date), 'url' => $user->can('view', $last) ? route('tickets.show', $last) : null] : null,
                'customer_id' => $summary->customer?->id,
                'replaced' => $summary->replacements->isNotEmpty(),
                'repair_warranty' => $warranty->level->isValid()
                    ? ['ends_on' => vn_date($warranty->endsOn), 'ticket_no' => $warranty->ticket->ticket_no, 'label' => $warranty->label()]
                    : null,
                'warranty_claims' => $summary->claimableTickets()->map(fn (RmaTicket $ticket) => [
                    'id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'url' => $user->can('view', $ticket) ? route('tickets.show', $ticket) : null,
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
