<?php

namespace App\Http\Controllers;

use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Models\RmaTicket;
use App\Support\Spreadsheet\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketSlipController extends Controller
{
    public function show(Request $request, RmaTicket $ticket, string $type): View
    {
        return view('slips.show', $this->slip($request, $ticket, $type));
    }

    public function excel(Request $request, RmaTicket $ticket, string $type): BinaryFileResponse
    {
        $slip = $this->slip($request, $ticket, $type);
        $company = config('rma.company');
        $customer = $ticket->customer;

        $rows = [
            [mb_strtoupper($company['name']), null, null, null, mb_strtoupper($slip['title'])],
            ['ĐC: '.$company['address'], null, null, null, 'Số phiếu: '.$ticket->ticket_no.' · Ngày '.vn_date($slip['date'])],
            ['Tel: '.($company['phone'] ?: '…………').'   Email: '.($company['email'] ?: '…………')],
            [],
            ['Khách hàng:', $customer->name, null, 'Điện thoại:', $ticket->contact_phone ?? $customer->phone],
            ['Người liên hệ:', $ticket->contact_name, null, 'Địa chỉ:', $customer->address],
            ['Hình thức:', $ticket->kind()->label(), null, 'Tình trạng BH:', $ticket->warranty_status->label()],
        ];
        $bold = [0];

        if ($slip['product']) {
            $rows[] = [];
            $bold[] = count($rows);
            $rows[] = $slip['product']['head'];
            $rows[] = $slip['product']['row'];
        }

        foreach (array_chunk($slip['details'], 2) as $pair) {
            $rows[] = [$pair[0][0].':', $pair[0][1], null, isset($pair[1]) ? $pair[1][0].':' : null, $pair[1][1] ?? null];
        }

        if ($slip['lines']->isNotEmpty()) {
            $rows[] = [];
            $bold[] = count($rows);
            $rows[] = ['STT', 'Nội dung sửa chữa', 'SL', 'Thành tiền'];

            foreach ($slip['lines'] as $index => $line) {
                $rows[] = [$index + 1, $line->description, $line->quantity, $line->lineTotal()];
            }

            $bold[] = count($rows);
            $rows[] = [null, null, 'Tổng cộng', $ticket->charge_amount];
        } elseif ($slip['isReturn']) {
            $rows[] = ['Chi phí:', 'Miễn phí'];
        }

        $rows[] = [];
        $bold[] = count($rows);
        $rows[] = [null, 'KHÁCH HÀNG', null, null, $slip['isReturn'] ? 'NHÂN VIÊN TRẢ' : 'NHÂN VIÊN NHẬN'];
        $rows[] = [null, '(Ký, ghi rõ họ tên)', null, null, '(Ký, ghi rõ họ tên)'];
        $rows[] = [];
        $rows[] = [];
        $rows[] = [null, null, null, null, $slip['staff']?->name];

        return (new XlsxWriter)
            ->addSheet($ticket->ticket_no, $rows, [14, 40, 14, 18, 34, 22], $bold, ['A1:D1', 'A2:D2', 'A3:E3'], ['paper' => XlsxWriter::PAPER_A5, 'orientation' => 'landscape'])
            ->download(($slip['isReturn'] ? 'PhieuTra_' : 'PhieuNhan_').$ticket->ticket_no.'.xlsx');
    }

    /**
     * Everything one A5 slip shows: the receipt keeps its product table; the return lists device, serials and paid repair lines.
     *
     * @return array<string, mixed>
     */
    private function slip(Request $request, RmaTicket $ticket, string $type): array
    {
        abort_unless($ticket->printsSlips(), 404, 'Hãng bảo hành tại nhà khách không in phiếu nhận / trả.');

        $isReturn = $type === 'return';
        abort_if($isReturn && ! in_array($ticket->status, [TicketStatus::Ready, TicketStatus::Returned], true), 404, 'Phiếu chưa có kết quả để in phiếu trả.');
        abort_if(! $isReturn && $ticket->status === TicketStatus::Cancelled, 404, 'Phiếu đã hủy.');

        $ticket->load('customer', 'device.productModel.brand', 'device.productModel.deviceType', 'returnedDevice.productModel.brand', 'returnedDevice.productModel.deviceType', 'technician', 'creator', 'quoteItems');

        $date = $isReturn ? ($ticket->returned_date ?? today()) : $ticket->received_date;
        $isRepair = $ticket->kind() === TicketKind::Repair;
        $title = $isReturn
            ? ($isRepair ? 'Phiếu trả hàng sửa chữa' : 'Phiếu trả hàng bảo hành')
            : ($ticket->originalKind() === TicketKind::Repair ? 'Phiếu nhận hàng sửa chữa' : 'Phiếu nhận hàng bảo hành');

        $details = [];
        $product = null;

        if (! $isReturn) {
            $product = [
                'head' => ['STT', 'Tên sản phẩm', 'SL', 'Serial', 'Tình trạng / lỗi khách báo', 'Phụ kiện kèm theo'],
                'row' => [1, $ticket->device->displayName(), 1, $ticket->device->serial_number, $ticket->fault_description, $ticket->accessories ?: 'Không'],
            ];
        } else {
            $details[] = ['Thiết bị', $ticket->device->displayName(), false];

            if ($ticket->returnedDevice) {
                $details[] = ['Seri lỗi', $ticket->device->serial_number, false];
                $details[] = ['Seri trả', $ticket->returnedDevice->serial_number.($ticket->isSwappedToOtherProduct()
                    ? ' (hãng đã đổi sang sản phẩm khác: '.$ticket->returnedDevice->displayName().')'
                    : ' (hãng đổi máy mới cùng model)'), $ticket->isSwappedToOtherProduct()];
            } else {
                $details[] = ['Serial', $ticket->device->serial_number, false];
            }

            $details[] = ['Ngày nhận', vn_date($ticket->received_date), false];

            if ($ticket->scrap_reason) {
                $details[] = ['Lý do báo phế', $ticket->scrap_reason, true];
            }
        }

        return [
            'ticket' => $ticket,
            'type' => $type,
            'isReturn' => $isReturn,
            'title' => $title,
            'date' => $date,
            'staff' => $isReturn ? $request->user() : ($ticket->technician ?? $ticket->creator),
            'details' => $details,
            'product' => $product,
            'lines' => $isReturn && $ticket->is_chargeable ? $ticket->quoteItems : collect(),
            'company' => config('rma.company'),
        ];
    }
}
