<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Models\RmaReplacedPart;
use App\Models\RmaTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketReplacedPartController extends Controller
{
    public function store(Request $request, RmaTicket $ticket): RedirectResponse
    {
        $this->ensureEditable($ticket);

        $data = $request->validateWithBag('parts', [
            'part_code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ], attributes: ['part_code' => 'mã linh kiện', 'description' => 'mô tả', 'quantity' => 'số lượng']);

        $ticket->replacedParts()->create([
            ...$data,
            'rma_center_shipment_id' => $ticket->latestShipment?->id,
        ]);

        return redirect()->to(route('tickets.show', $ticket).'#linh-kien');
    }

    public function destroy(RmaTicket $ticket, RmaReplacedPart $replacedPart): RedirectResponse
    {
        $this->ensureEditable($ticket);

        $replacedPart->delete();

        return redirect()->to(route('tickets.show', $ticket).'#linh-kien');
    }

    private function ensureEditable(RmaTicket $ticket): void
    {
        abort_if(
            $ticket->isClosed() || $ticket->original_service_type === ServiceType::Repair,
            403,
            'Chỉ ghi linh kiện hãng thay cho phiếu bảo hành chưa đóng.',
        );
    }
}
