<?php

namespace App\Http\Controllers;

use App\Models\RmaTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketErpController extends Controller
{
    public function update(Request $request, RmaTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->is_chargeable && ! $ticket->isClosed(), 403, 'Chỉ phiếu có phí chưa đóng mới nhập chứng từ ERP.');

        $data = $request->validateWithBag('erp', [
            'erp_receipt_no' => ['nullable', 'string', 'max:50'],
            'erp_return_no' => ['nullable', 'string', 'max:50'],
        ], attributes: ['erp_receipt_no' => 'số chứng từ V223', 'erp_return_no' => 'số chứng từ V233']);

        $ticket->update($data);

        return redirect()->to(route('tickets.show', $ticket).'#erp')->with('status', 'Đã lưu số chứng từ ERP.');
    }
}
