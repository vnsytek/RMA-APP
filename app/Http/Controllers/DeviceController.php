<?php

namespace App\Http\Controllers;

use App\Enums\RepairWarrantyLevel;
use App\Models\Customer;
use App\Models\Device;
use App\Services\DeviceSummary;
use App\Services\DeviceTracker;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public const FILTERS = [
        'active' => 'Còn BH sau sửa',
        'expiring' => 'Sắp hết BH sau sửa',
        'expired' => 'Đã hết BH sau sửa',
        'open' => 'Đang xử lý',
        'multi' => 'Gửi từ 2 lần',
        'swapped' => 'Máy hãng đổi',
    ];

    public const SORTS = [
        'recent' => 'Gửi gần đây nhất',
        'expiring' => 'BH sau sửa sắp hết trước',
        'most' => 'Gửi nhiều lần nhất',
    ];

    public function index(Request $request, DeviceTracker $tracker): View
    {
        $filters = $request->validate([
            'filter' => ['nullable', Rule::in(array_keys(self::FILTERS))],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'customer' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $all = $tracker->all();
        $counts = ['' => $all->count()] + collect(self::FILTERS)->map(fn ($label, $key) => $all->filter(fn (DeviceSummary $item) => $this->matches($item, $key))->count())->all();
        $search = mb_strtolower(trim($filters['q'] ?? ''));
        $customerId = isset($filters['customer']) ? (int) $filters['customer'] : null;

        $rows = $all
            ->filter(fn (DeviceSummary $item) => empty($filters['filter']) || $this->matches($item, $filters['filter']))
            ->filter(fn (DeviceSummary $item) => $customerId === null || $item->customerIds->contains($customerId))
            ->filter(fn (DeviceSummary $item) => $search === '' || str_contains($this->haystack($item), $search))
            ->sort($this->sorter($filters['sort'] ?? 'recent'))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 30;

        return view('devices.index', [
            'devices' => (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url()]))->withQueryString(),
            'counts' => $counts,
            'filters' => $filters,
            'customers' => Customer::query()->whereHas('tickets')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Device $device, DeviceTracker $tracker): View
    {
        return view('devices.show', ['summary' => $tracker->for($device)]);
    }

    private function matches(DeviceSummary $item, string $filter): bool
    {
        return match ($filter) {
            'active' => $item->repairWarranty->level->isValid(),
            'expiring' => $item->repairWarranty->level === RepairWarrantyLevel::Expiring,
            'expired' => $item->repairWarranty->level === RepairWarrantyLevel::Expired,
            'open' => $item->openTicket !== null,
            'multi' => $item->tickets->count() >= 2,
            'swapped' => $item->isSwapped(),
            default => true,
        };
    }

    private function haystack(DeviceSummary $item): string
    {
        $model = $item->device->productModel;
        $customers = $item->tickets->pluck('customer')->unique('id')
            ->map(fn (Customer $customer) => "{$customer->name} {$customer->contact_name} {$customer->phone}")
            ->implode(' ');

        return mb_strtolower("{$item->device->serial_number} {$model->code} {$model->brand->name} {$model->deviceType->name} {$customers}");
    }

    private function sorter(string $sort): callable
    {
        $recent = fn (DeviceSummary $a, DeviceSummary $b) => ($b->lastTicket()?->received_date?->timestamp ?? 0) <=> ($a->lastTicket()?->received_date?->timestamp ?? 0);

        return match ($sort) {
            'expiring' => function (DeviceSummary $a, DeviceSummary $b) use ($recent) {
                $rank = fn (DeviceSummary $item) => match ($item->repairWarranty->level) {
                    RepairWarrantyLevel::Expiring => 0,
                    RepairWarrantyLevel::Active => 1,
                    default => 2,
                };

                return [$rank($a), $a->repairWarranty->daysLeft ?? PHP_INT_MAX] <=> [$rank($b), $b->repairWarranty->daysLeft ?? PHP_INT_MAX] ?: $recent($a, $b);
            },
            'most' => fn (DeviceSummary $a, DeviceSummary $b) => $b->tickets->count() <=> $a->tickets->count() ?: $recent($a, $b),
            default => $recent,
        };
    }
}
