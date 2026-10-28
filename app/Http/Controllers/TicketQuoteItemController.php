<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Models\RmaQuoteItem;
use App\Models\RmaTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketQuoteItemController extends Controller
{
    public function store(Request $request, RmaTicket $ticket): RedirectResponse
    {
        $this->ensureEditable($ticket);

        $request->merge(['unit_price' => preg_replace('/\D/', '', (string) $request->input('unit_price'))]);

        $data = $request->validateWithBag('quote', [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ], attributes: ['description' => 'nội dung', 'quantity' => 'số lượng', 'unit_price' => 'đơn giá']);

        $wasSent = $ticket->status === TicketStatus::Quoted;
        $item = $ticket->quoteItems()->create($data);

        if ($wasSent) {
            $this->logQuoteEdit($ticket, $request, null, $item);
        }

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    public function destroy(RmaTicket $ticket, RmaQuoteItem $quoteItem, Request $request): RedirectResponse
    {
        $this->ensureEditable($ticket);

        $wasSent = $ticket->status === TicketStatus::Quoted;

        if ($wasSent) {
            $this->logQuoteEdit($ticket, $request, $quoteItem, null);
        }

        $quoteItem->delete();

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    /**
     * The quote is priced once, while IT is inspecting. Once sent it would normally be locked,
     * but a line can still be corrected after the fact (price changed, wrong line quoted) — see
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
