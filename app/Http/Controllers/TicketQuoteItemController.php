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

        $wasSent = $ticket->status === TicketStatus::Quoted;

        DB::transaction(function () use ($ticket, $data, $part, $stock, $request, $wasSent): void {
            $item = $ticket->quoteItems()->create($data);

            if ($part) {
                $stock->issue($part, $data['quantity'], $request->user(), "Dùng cho phiếu {$ticket->ticket_no}");
            }

            if ($wasSent) {
                $this->logQuoteEdit($ticket, $request, null, $item);
            }
        });

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    public function destroy(RmaTicket $ticket, RmaQuoteItem $quoteItem, PartStock $stock, Request $request): RedirectResponse
    {
        $this->ensureEditable($ticket);

        $wasSent = $ticket->status === TicketStatus::Quoted;

        if ($quoteItem->part_id) {
            $stock->restore($quoteItem->part, $quoteItem->quantity, $request->user(), "Xoá dòng báo giá của phiếu {$ticket->ticket_no}");
        }

        if ($wasSent) {
            $this->logQuoteEdit($ticket, $request, $quoteItem, null);
        }

        $quoteItem->delete();

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    /**
     * The quote is priced once, while IT is inspecting. Once sent it would normally be locked,
     * but a line can still be corrected after the fact (price changed, wrong part quoted) — see
     * the logging below, which keeps that correction visible on the ticket's timeline.
     */
    private function ensureEditable(RmaTicket $ticket): void
    {
        abort_unless(
            $ticket->service_type === ServiceType::Repair && in_array($ticket->status, [TicketStatus::Inspecting, TicketStatus::Quoted], true),
            403,
            'Chỉ sửa được báo giá khi phiếu sửa chữa đang ở bước IT kiểm tra hoặc đã gửi báo giá (chưa được khách chốt).',
        );

        abort_if($ticket->claimPending(), 403, 'Kết luận yêu cầu bảo hành sửa chữa trước (trong hay ngoài phạm vi) rồi mới lập báo giá.');
    }

    /**
     * Normal line management while still inspecting needs no log — it's the quote being built,
     * not a correction. Once the quote has already been sent, a line added or removed is a real
     * correction and goes on the ticket's timeline.
     */
    private function logQuoteEdit(RmaTicket $ticket, Request $request, ?RmaQuoteItem $before, ?RmaQuoteItem $after): void
    {
        $describe = fn (RmaQuoteItem $item) => "{$item->description} ({$item->quantity} x ".money_vnd($item->unit_price).')';

        $ticket->edits()->create([
            'user_id' => $request->user()->id,
            'field' => 'Báo giá (sau khi đã gửi)',
            'old_value' => $before ? $describe($before) : null,
            'new_value' => $after ? $describe($after) : null,
        ]);
    }
}
