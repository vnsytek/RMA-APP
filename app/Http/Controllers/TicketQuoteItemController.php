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

        $ticket->quoteItems()->create($data);

        return redirect()->to(route('tickets.show', $ticket).'#bao-gia');
    }

    public function destroy(RmaTicket $ticket, RmaQuoteItem $quoteItem): RedirectResponse
    {
        $this->ensureEditable($ticket);

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
