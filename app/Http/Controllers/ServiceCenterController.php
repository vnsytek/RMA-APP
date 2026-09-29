<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentOutcome;
use App\Models\ServiceCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCenterController extends Controller
{
    public function index(): View
    {
        return view('service-centers.index', [
            'serviceCenters' => ServiceCenter::query()
                ->withCount(['shipments', 'shipments as pending_count' => fn ($query) => $query->where('outcome', ShipmentOutcome::Pending)])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ServiceCenter::create($this->validated($request));

        return back()->with('status', 'Đã thêm hãng / TTBH.');
    }

    public function update(Request $request, ServiceCenter $serviceCenter): RedirectResponse
    {
        $serviceCenter->update($this->validated($request));

        return back()->with('status', 'Đã lưu hãng / TTBH.');
    }

    public function toggle(ServiceCenter $serviceCenter): RedirectResponse
    {
        $serviceCenter->update(['is_active' => ! $serviceCenter->is_active]);

        return back()->with('status', ($serviceCenter->is_active ? 'Đã hiện lại ' : 'Đã ẩn ').$serviceCenter->name.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validateWithBag('serviceCenter', [
            'name' => ['required', 'string', 'max:255'],
            'brands' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ], attributes: ['name' => 'tên', 'brands' => 'hãng nhận bảo hành', 'phone' => 'số điện thoại', 'address' => 'địa chỉ']);
    }
}
