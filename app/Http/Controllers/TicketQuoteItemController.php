<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Models\Part;
use App\Models\RmaQuoteItem;
use App\Models\RmaTicket;
use App\Services\PartStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketQuoteItemController extends Controller
{
    public function store(Request $request, RmaTicket $ticket, PartStock $stock): RedirectResponse
    {
        $this->ensureEditable($ticket);

        $request->merge(['unit_price' => preg_replace('/\D/', '', (string) $request->input('unit_price'))]);

        $part = $request->filled('part_id') ? Part::query()->active()->find($request->integer('part_id')) : null;

        $quantityRules = ['required', 'integer', 'min:1', 'max:1000'];
        if ($part) {
            $quantityRules[] = 'max:'.$part->quantity_on_hand;
        }

        $data = $request->validateWithBag('quote', [
            'part_id' => ['nullable', 'integer', Rule::exists('parts', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => $quantityRules,
            'unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ], attributes: ['part_id' => 'linh kiện trong kho', 'description' => 'nội dung', 'quantity' => 'số lượng', 'unit_price' => 'đơn giá']);

        DB::transaction(function () use ($ticket, $data, $part, $stock, $request): void {
            $ticket->quoteItems()->create($data);

            if ($part) {
                $stock->issue($part, $data['quantity'], $request->user(), "Dùng cho phiếu {$ticket->ticket_no}");
            }
        });

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    public function destroy(RmaTicket $ticket, RmaQuoteItem $quoteItem, PartStock $stock, Request $request): RedirectResponse
    {
        $this->ensureEditable($ticket);

        if ($quoteItem->part_id) {
            $stock->restore($quoteItem->part, $quoteItem->quantity, $request->user(), "Xoá dòng báo giá của phiếu {$ticket->ticket_no}");
        }

        $quoteItem->delete();

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    /**
     * The quote is priced once, while IT is inspecting; once sent it is locked.
     */
    private function ensureEditable(RmaTicket $ticket): void
    {
        abort_unless(
            $ticket->service_type === ServiceType::Repair && $ticket->status === TicketStatus::Inspecting,
            403,
            'Chỉ sửa được báo giá khi phiếu sửa chữa đang ở bước IT kiểm tra.',
        );

        abort_if($ticket->claimPending(), 403, 'Kết luận yêu cầu bảo hành sửa chữa trước (trong hay ngoài phạm vi) rồi mới lập báo giá.');
    }
}
