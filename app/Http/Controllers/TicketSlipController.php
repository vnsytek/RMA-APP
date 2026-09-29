<?php

namespace App\Http\Controllers;

use App\Enums\TicketKind;
use App\Enums\TicketResult;
use App\Enums\TicketStatus;
use App\Models\RmaTicket;
use App\Models\RmaWarrantyItem;
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
            [mb_strtoupper($company['name'])],
            ['ĐC: '.$company['address']],
            ['Tel: '.($company['phone'] ?: '…………').'   Email: '.($company['email'] ?: '…………')],
            [],
            [mb_strtoupper($slip['title'])],
            ['Số phiếu: '.$ticket->ticket_no.'   Ngày '.vn_date($slip['date'])],
            [],
            ['Khách hàng:', $customer->name, null, 'Điện thoại:', $customer->phone],
            ['Người liên hệ:', $customer->contact_name, null, 'Địa chỉ:', $customer->address],
            ['Hình thức:', $ticket->kind()->label(), null, 'Tình trạng BH:', $ticket->warranty_status->label()],
            [],
            $slip['head'],
            $slip['row'],
        ];
        $bold = [0, 4, 11];

        foreach (array_filter([$slip['swapLine'], $slip['isReturn'] && $ticket->scrap_reason ? 'Lý do báo phế: '.$ticket->scrap_reason : null]) as $line) {
            $rows[] = [];
            $rows[] = [$line];
        }

        if ($slip['lines']->isNotEmpty()) {
            $rows[] = [];
            $bold[] = count($rows);
            $rows[] = ['STT', 'Nội dung sửa chữa', 'SL', 'Đơn giá', 'Thành tiền'];

            foreach ($slip['lines'] as $index => $line) {
                $rows[] = [$index + 1, $line->description, $line->quantity, $line->unit_price, $line->lineTotal()];
            }

            $bold[] = count($rows);
            $rows[] = [null, null, null, 'Tổng cộng', $ticket->charge_amount];
        } elseif ($slip['isReturn']) {
            $rows[] = [];
            $rows[] = ['Chi phí:', 'Miễn phí'];
        }

        if ($slip['warrantyLine']) {
            $rows[] = [];
            $rows[] = [$slip['warrantyLine']];
        }

        if ($slip['warrantyRows'] !== []) {
            $bold[] = count($rows);
            $rows[] = ['STT', 'Hạng mục bảo hành', 'Tháng', 'Đến ngày'];

            foreach ($slip['warrantyRows'] as $index => $row) {
                $rows[] = [$index + 1, $row['description'], $row['months'], vn_date($row['ends_on'])];
            }
        }

        if ($slip['exclusions'] !== []) {
            $rows[] = [];
            $bold[] = count($rows);
            $rows[] = ['Không bảo hành:'];

            foreach ($slip['exclusions'] as $exclusion) {
                $rows[] = [null, '- '.$exclusion];
            }
        }

        $rows[] = [];
        $rows[] = [];
        $bold[] = count($rows);
        $rows[] = [null, 'KHÁCH HÀNG', null, null, $slip['isReturn'] ? 'NHÂN VIÊN TRẢ' : 'NHÂN VIÊN NHẬN'];
        $rows[] = [null, '(Ký, ghi rõ họ tên)', null, null, '(Ký, ghi rõ họ tên)'];
        $rows[] = [];
        $rows[] = [];
        $rows[] = [null, null, null, null, $slip['staff']?->name];

        return (new XlsxWriter)
            ->addSheet($ticket->ticket_no, $rows, [16, 36, 6, 24, 30, 32], $bold, ['A1:F1', 'A2:F2', 'A3:F3', 'A5:F5', 'A6:F6'])
            ->download(($slip['isReturn'] ? 'PhieuTra_' : 'PhieuNhan_').$ticket->ticket_no.'.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function slip(Request $request, RmaTicket $ticket, string $type): array
    {
        abort_unless($ticket->printsSlips(), 404, 'Hãng bảo hành tại nhà khách không in phiếu nhận / trả.');

        $isReturn = $type === 'return';
        abort_if($isReturn && ! in_array($ticket->status, [TicketStatus::Ready, TicketStatus::Returned], true), 404, 'Phiếu chưa có kết quả để in phiếu trả.');
        abort_if(! $isReturn && $ticket->status === TicketStatus::Cancelled, 404, 'Phiếu đã hủy.');

        $ticket->load('customer', 'device.productModel.brand', 'device.productModel.deviceType', 'returnedDevice.productModel.brand', 'returnedDevice.productModel.deviceType', 'technician', 'creator', 'quoteItems', 'warrantyItems', 'claimTicket', 'claimItem');

        $date = $isReturn ? ($ticket->returned_date ?? today()) : $ticket->received_date;
        $isRepair = $ticket->kind() === TicketKind::Repair;
        $title = $isReturn
            ? ($isRepair ? 'Phiếu trả hàng sửa chữa' : 'Phiếu trả hàng bảo hành')
            : ($ticket->originalKind() === TicketKind::Repair ? 'Phiếu nhận hàng sửa chữa' : 'Phiếu nhận hàng bảo hành');

        $serialOut = $ticket->currentDevice()->serial_number;
        $swapped = $ticket->returnedDevice !== null;
        $otherProduct = $ticket->isSwappedToOtherProduct();

        $warrantyLine = null;
        $warrantyRows = [];
        $exclusions = [];

        if ($isReturn && $isRepair && $ticket->result?->carriesRepairWarranty()) {
            $warrantyRows = $ticket->warrantyItems
                ->map(fn (RmaWarrantyItem $item) => ['description' => $item->description, 'months' => $item->months, 'ends_on' => $item->endsOn($date)])
                ->all();
            $covered = $ticket->warrantyItems->pluck('rma_quote_item_id')->filter()->all();
            $exclusions = [
                ...$ticket->quoteItems->reject(fn ($line) => in_array($line->id, $covered, true))->pluck('description')->all(),
                ...$ticket->warrantyExclusionList(),
            ];

            if ($warrantyRows === []) {
                $endsOn = $ticket->repairWarrantyEndsOn($date);
                $warrantyLine = $endsOn
                    ? "Bảo hành sau sửa chữa: {$ticket->repair_warranty_months} tháng, đến ngày {$endsOn->format('d/m/Y')}."
                    : 'Không bảo hành sau sửa chữa.';
            } else {
                $warrantyLine = 'Sang Y chỉ bảo hành đúng các hạng mục ghi thời hạn dưới đây, tính từ ngày trả máy. Khi bảo hành, quý khách vui lòng mang theo phiếu này.';
            }
        }

        if ($isReturn && $ticket->result === TicketResult::RepairWarranty && $ticket->claimTicket) {
            $endsOn = $ticket->claimItem?->endsOn() ?? $ticket->claimTicket->repairWarrantyEndsOn();
            $warrantyLine = "Bảo hành sửa chữa theo phiếu {$ticket->claimTicket->ticket_no}"
                .($ticket->claimItem ? " (hạng mục: {$ticket->claimItem->description})" : '')
                .': miễn phí. Thời hạn bảo hành giữ nguyên'.($endsOn ? ' đến ngày '.$endsOn->format('d/m/Y') : '').'.';
        }

        return [
            'ticket' => $ticket,
            'type' => $type,
            'isReturn' => $isReturn,
            'title' => $title,
            'date' => $date,
            'staff' => $isReturn ? $request->user() : ($ticket->technician ?? $ticket->creator),
            'head' => $isReturn
                ? ['STT', 'Tên sản phẩm', 'SL', 'Seri lỗi', 'Seri trả', 'Kết quả']
                : ['STT', 'Tên sản phẩm', 'SL', 'Serial', 'Tình trạng / lỗi khách báo', 'Phụ kiện kèm theo'],
            'row' => $isReturn
                ? [1, $ticket->device->displayName(), 1, $ticket->device->serial_number, $otherProduct ? "{$serialOut} ({$ticket->returnedDevice->displayName()})" : $serialOut, $ticket->result?->label()]
                : [1, $ticket->device->displayName(), 1, $ticket->device->serial_number, $ticket->fault_description, $ticket->accessories ?: 'Không'],
            'swapLine' => $isReturn && $swapped
                ? 'Hãng đã đổi '.($otherProduct ? 'sang sản phẩm khác' : 'máy mới cùng model').": {$ticket->returnedDevice->displayName()}, serial {$serialOut}."
                : null,
            'lines' => $isReturn && $ticket->is_chargeable ? $ticket->quoteItems : collect(),
            'warrantyLine' => $warrantyLine,
            'warrantyRows' => $warrantyRows,
            'exclusions' => $exclusions,
            'company' => config('rma.company'),
        ];
    }
}
