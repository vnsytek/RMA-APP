<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\DeviceSummary;
use App\Services\DeviceTracker;
use App\Services\TaxCodeLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, DeviceTracker $tracker): View
    {
        $search = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('contacts', fn (Builder $contact) => $contact->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                ->orWhere('tax_code', 'like', "%{$search}%")))
            ->with(['contacts' => fn ($query) => $query->active()])
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
        $customer = Customer::create($this->validated($request, null));

        return redirect()->route('customers.show', $customer)->with('status', "Đã thêm khách hàng {$customer->name}.");
    }

    public function show(Request $request, Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer,
            'tickets' => $customer->tickets()->visibleTo($request->user())->with('device.productModel.brand', 'device.productModel.deviceType')->orderByDesc('ticket_no')->limit(100)->get(),
            'contacts' => $customer->contacts()->withCount('tickets')->get(),
            'ticketCount' => $customer->tickets()->withTrashed()->count(),
            'openCount' => $customer->tickets()->open()->count(),
            'deviceCount' => $customer->tickets()->distinct()->count('device_id'),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('status', 'Đã lưu khách hàng.');
    }

    /**
     * Admins remove a customer entered by mistake; one that already has tickets is kept for their history.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $tickets = $customer->tickets()->withTrashed()->count();

        if ($tickets > 0) {
            return back()->with('error', "Không xoá được {$customer->name}: khách đã có {$tickets} phiếu. Chỉ xoá được khách chưa có phiếu nào.");
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('status', "Đã xoá khách hàng {$customer->name}.");
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * A company is identified by its tax code; people and companies without one need a phone number.
     */
    private function validated(Request $request, ?Customer $customer): array
    {
        $request->merge(['tax_code' => TaxCodeLookup::normalize($request->input('tax_code'))]);

        return $request->validateWithBag('customer', [
            'name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'regex:'.TaxCodeLookup::pattern(), Rule::unique('customers', 'tax_code')->ignore($customer)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'tax_code.regex' => 'Mã số thuế gồm 10 số, chi nhánh thêm "-" và 3 số (VD: 0801379534-001).',
            'tax_code.unique' => 'Đã có khách hàng dùng mã số thuế này.',
        ], ['name' => 'tên khách', 'tax_code' => 'mã số thuế', 'phone' => 'số điện thoại công ty', 'address' => 'địa chỉ', 'note' => 'ghi chú']);
    }
}
