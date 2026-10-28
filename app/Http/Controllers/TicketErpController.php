<?php

namespace App\Http\Controllers;

use App\Models\RmaTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TicketErpController extends Controller
{
    public function update(Request $request, RmaTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->is_chargeable && ! $ticket->isClosed(), 403, 'Chỉ phiếu có phí chưa đóng mới nhập chứng từ ERP.');

        // V223 is the ticket number itself; only the return voucher (V233) is typed, and it must be unique.
        $request->merge(['erp_return_no' => Str::squish((string) $request->input('erp_return_no')) ?: null]);

        $data = $request->validateWithBag('erp', [
            'erp_return_no' => ['nullable', 'string', 'max:50', Rule::unique('rma_tickets', 'erp_return_no')->ignore($ticket)],
        ], [
            'erp_return_no.unique' => 'Số V233 này đã dùng cho phiếu khác.',
        ], ['erp_return_no' => 'số chứng từ V233']);

        $ticket->update($data);

        return redirect()->to(route('tickets.show', $ticket).'#erp')->with('status', 'Đã lưu số chứng từ V233.');
    }
}
