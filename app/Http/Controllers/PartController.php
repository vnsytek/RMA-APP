<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartMovement;
use App\Services\PartStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartController extends Controller
{
    public function index(): View
    {
        return view('parts.index', [
            'parts' => Part::query()->orderBy('name')->get(),
            'movements' => PartMovement::with(['part', 'user'])->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('part', [
            'code' => ['required', 'string', 'max:50', Rule::unique('parts', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], attributes: ['code' => 'mã linh kiện', 'name' => 'tên linh kiện', 'unit' => 'đơn vị tính', 'min_quantity' => 'mức tồn tối thiểu', 'warranty_months' => 'số tháng bảo hành mặc định']);

        Part::create([...$data, 'unit' => $data['unit'] ?: 'cái']);

        return back()->with('status', 'Đã thêm linh kiện.');
    }

    public function stockIn(Request $request, Part $part, PartStock $stock): RedirectResponse
    {
        $data = $request->validateWithBag('stockIn', [
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['quantity' => 'số lượng nhập', 'note' => 'ghi chú']);

        $stock->receive($part, $data['quantity'], $request->user(), $data['note'] ?? null);

        return back()->with('status', "Đã nhập {$data['quantity']} {$part->unit} \"{$part->name}\" vào kho.");
    }

    public function stockOut(Request $request, Part $part, PartStock $stock): RedirectResponse
    {
        $data = $request->validateWithBag('stockOut', [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$part->quantity_on_hand],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['quantity' => 'số lượng xuất', 'note' => 'ghi chú']);

        $stock->issue($part, $data['quantity'], $request->user(), $data['note'] ?? null);

        return back()->with('status', "Đã xuất {$data['quantity']} {$part->unit} \"{$part->name}\" khỏi kho.");
    }

    /**
     * Admin-only stocktake correction: set the count directly, keep why in the note.
     */
    public function adjust(Request $request, Part $part, PartStock $stock): RedirectResponse
    {
        $data = $request->validateWithBag('adjust', [
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'note' => ['required', 'string', 'max:255'],
        ], attributes: ['quantity_on_hand' => 'số lượng sau kiểm kê', 'note' => 'lý do điều chỉnh']);

        $stock->adjust($part, $data['quantity_on_hand'], $request->user(), $data['note']);

        return back()->with('status', "Đã điều chỉnh tồn \"{$part->name}\" thành {$data['quantity_on_hand']} {$part->unit}.");
    }

    public function toggle(Part $part): RedirectResponse
    {
        $part->update(['is_active' => ! $part->is_active]);

        return back()->with('status', ($part->is_active ? 'Đã hiện lại ' : 'Đã ẩn ').$part->name.'.');
    }
}
