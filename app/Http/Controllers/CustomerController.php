<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\DeviceSummary;
use App\Services\DeviceTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, DeviceTracker $tracker): View
    {
        $search = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->withCount(['tickets', 'tickets as open_tickets_count' => fn (Builder $query) => $query->open()])
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $devicesByCustomer = $tracker->all()
            ->filter(fn (DeviceSummary $item) => $item->customer !== null && $item->replacements->isEmpty())
            ->groupBy(fn (DeviceSummary $item) => $item->customer->id);

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'deviceCounts' => $devicesByCustomer->map->count(),
            'warrantyCounts' => $devicesByCustomer->map(fn ($items) => $items->filter(fn (DeviceSummary $item) => $item->repairWarranty->level->isValid())->count()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));

        return back()->with('status', 'Đã thêm khách hàng.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', [
            'customer' => $customer,
            'tickets' => $customer->tickets()->with('device.productModel.brand', 'device.productModel.deviceType')->orderByDesc('ticket_no')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.index')->with('status', 'Đã lưu khách hàng.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validateWithBag('customer', [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['name' => 'tên khách', 'contact_name' => 'người liên hệ', 'phone' => 'số điện thoại', 'address' => 'địa chỉ', 'note' => 'ghi chú']);
    }
}
