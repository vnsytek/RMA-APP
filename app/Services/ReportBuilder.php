<?php

namespace App\Services;

use App\Enums\ClaimResult;
use App\Enums\ServiceType;
use App\Enums\ShipmentOutcome;
use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Models\Device;
use App\Models\RmaCenterShipment;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the report page and its Excel export from one set of queries.
 *
 * Column types: text, num, money, days, pct, date, ticket, kind, status, severity, mono.
 */
class ReportBuilder
{
    /**
     * @return array{
     *     kpis: list<array{label: string, value: string, sub: string}>,
     *     months: list<array{label: string, ym: string, total: int, by_kind: array<string, int>, in_period: bool}>,
     *     sections: list<array{id: string, title: string, note: string, wide: bool, columns: list<array{0: string, 1: string}>, rows: list<array{cells: list<mixed>, ticket?: string, total?: bool}>}>,
     *     received: Collection<int, RmaTicket>
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to, ?TicketKind $kind): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->endOfDay();
        $inRange = fn (?CarbonInterface $date): bool => $date !== null && $date->between($from, $to);
        $days = fn (CarbonInterface $start, CarbonInterface $end): int => (int) $start->diffInDays($end);

        $tickets = RmaTicket::query()
            ->ofKind($kind)
            ->where(fn (Builder $query) => $query
                ->whereBetween('received_date', [$from->toDateString(), $to->toDateString()])
                ->orWhereBetween('returned_date', [$from->toDateString(), $to->toDateString()])
                ->orWhereIn('status', TicketStatus::open())
                ->orWhere(fn (Builder $query) => $query->where('repair_warranty_months', '>', 0)->whereNotNull('returned_date'))
                ->orWhereHas('statusLogs', fn (Builder $log) => $log->where('to_status', TicketStatus::Cancelled)->whereBetween('created_at', [$from, $to])))
            ->with(['customer', 'device.productModel.brand', 'device.productModel.deviceType', 'returnedDevice.productModel.brand', 'technician', 'shipments.serviceCenter', 'quoteItems', 'statusLogs', 'claimTicket', 'claimItem'])
            ->orderBy('ticket_no')
            ->get();

        $received = $tickets->filter(fn (RmaTicket $ticket) => $inRange($ticket->received_date))->values();
        $finished = $tickets->filter(fn (RmaTicket $ticket) => $ticket->status->isFinished() && $inRange($ticket->returned_date))->values();
        $open = $tickets->filter(fn (RmaTicket $ticket) => ! $ticket->isClosed())->values();

        $stat = function (Collection $list) use ($inRange, $days): array {
            $done = $list->filter(fn (RmaTicket $ticket) => $ticket->status->isFinished() && $inRange($ticket->returned_date));
            $durations = $done->map(fn (RmaTicket $ticket) => $days($ticket->received_date, $ticket->returned_date));

            return [
                'received' => $list->filter(fn (RmaTicket $ticket) => $inRange($ticket->received_date))->count(),
                'finished' => $done->count(),
                'open' => $list->filter(fn (RmaTicket $ticket) => ! $ticket->isClosed())->count(),
                'avg_days' => $durations->isEmpty() ? null : round($durations->avg(), 1),
                'revenue' => (int) $done->where('is_chargeable', true)->sum('charge_amount'),
                'converted' => $list->filter(fn (RmaTicket $ticket) => $inRange($ticket->received_date) && $ticket->wasConverted())->count(),
            ];
        };

        $all = $stat($tickets);
        $overdueDays = config('rma.overdue_days');
        $overdue = $open->filter(fn (RmaTicket $ticket) => $days($ticket->received_date, today()) > $overdueDays)->count();
        $kinds = $kind ? [$kind] : TicketKind::cases();

        $kpis = [
            ['label' => 'Phiếu nhận trong kỳ', 'value' => (string) $all['received'], 'sub' => collect(TicketKind::cases())->map(fn (TicketKind $type) => $type->shortLabel().' '.$received->filter(fn (RmaTicket $ticket) => $ticket->kind() === $type)->count())->implode(' · ')],
            ['label' => 'Hoàn tất trong kỳ', 'value' => (string) $all['finished'], 'sub' => 'Đã trả khách hoặc hãng xử lý xong'],
            ['label' => 'Đang xử lý', 'value' => (string) $open->count(), 'sub' => $overdue ? "{$overdue} phiếu quá {$overdueDays} ngày" : "Không có phiếu quá {$overdueDays} ngày"],
            ['label' => 'Doanh thu sửa chữa', 'value' => money_vnd($all['revenue']), 'sub' => $finished->where('is_chargeable', true)->count().' phiếu có phí đã trả'],
            ['label' => 'Thời gian xử lý TB', 'value' => $all['avg_days'] === null ? '—' : number_format($all['avg_days'], 1, ',', '.').' ngày', 'sub' => 'Từ ngày nhận đến ngày trả'],
        ];

        $sections = [];

        $sections[] = [
            'id' => 'kind', 'title' => 'Theo hình thức', 'note' => 'Nhận, hoàn tất trong kỳ; đang xử lý tính đến hôm nay', 'wide' => false,
            'columns' => [['Hình thức', 'text'], ['Nhận', 'num'], ['Hoàn tất', 'num'], ['Đang xử lý', 'num'], ['TB xử lý', 'days'], ['Doanh thu', 'money']],
            'rows' => [
                ...array_map(function (TicketKind $type) use ($tickets, $stat) {
                    $row = $stat($tickets->filter(fn (RmaTicket $ticket) => $ticket->kind() === $type));
                    $label = $type->label().($type === TicketKind::Repair && $row['converted'] ? " (gồm {$row['converted']} phiếu BH bị từ chối)" : '');

                    return ['cells' => [$label, $row['received'], $row['finished'], $row['open'], $row['avg_days'], $row['revenue']]];
                }, $kinds),
                ['total' => true, 'cells' => ['Tổng', $all['received'], $all['finished'], $all['open'], $all['avg_days'], $all['revenue']]],
            ],
        ];

        $cancelled = $tickets->filter(fn (RmaTicket $ticket) => $ticket->status === TicketStatus::Cancelled
            && $ticket->statusLogs->contains(fn ($log) => $log->to_status === TicketStatus::Cancelled && $inRange($log->created_at)));
        $resultTotal = $finished->count() + $cancelled->count();
        $sections[] = [
            'id' => 'result', 'title' => 'Kết quả xử lý', 'note' => 'Phiếu hoàn tất hoặc bị hủy trong kỳ', 'wide' => false,
            'columns' => [['Kết quả', 'text'], ['Số phiếu', 'num'], ['Tỷ lệ', 'pct']],
            'rows' => [
                ...$finished->filter(fn (RmaTicket $ticket) => $ticket->result !== null)->groupBy(fn (RmaTicket $ticket) => $ticket->result->value)
                    ->map(fn (Collection $group) => ['cells' => [$group->first()->result->label(), $group->count(), $group->count() / $resultTotal]])
                    ->sortByDesc(fn (array $row) => $row['cells'][1])->values()->all(),
                ...($cancelled->isNotEmpty() ? [['cells' => ['Đã hủy phiếu', $cancelled->count(), $cancelled->count() / $resultTotal]]] : []),
                ['total' => true, 'cells' => ['Tổng', $resultTotal, $resultTotal ? 1 : 0]],
            ],
        ];

        $sections[] = [
            'id' => 'backlog', 'title' => 'Phiếu tồn', 'note' => 'Phiếu chưa đóng, tính đến ngày '.today()->format('d/m/Y'), 'wide' => true,
            'columns' => [['Số phiếu', 'ticket'], ['Hình thức', 'kind'], ['Khách hàng', 'text'], ['Thiết bị', 'text'], ['Trạng thái', 'status'], ['Phụ trách', 'text'], ['Ngày nhận', 'date'], ['Số ngày', 'num'], ['Tình trạng', 'severity'], ['Ghi chú', 'text']],
            'rows' => $open->sortByDesc(fn (RmaTicket $ticket) => $days($ticket->received_date, today()))->map(function (RmaTicket $ticket) use ($days, $overdueDays) {
                $age = $days($ticket->received_date, today());
                $pending = $ticket->shipments->firstWhere('outcome', ShipmentOutcome::Pending);
                $note = match (true) {
                    $ticket->status === TicketStatus::AtCenter && $pending !== null => 'Ở '.$pending->serviceCenter->name.' '.$days($pending->sent_date, today()).' ngày',
                    $ticket->status === TicketStatus::Scheduled && $pending?->appointment_date !== null => 'Hẹn hãng ngày '.$pending->appointment_date->format('d/m/Y'),
                    $ticket->status === TicketStatus::Quoted => 'Chờ khách duyệt báo giá: '.money_vnd($ticket->quoteTotal()),
                    $ticket->status === TicketStatus::Ready => 'Báo khách đến lấy máy',
                    default => '',
                };

                return ['ticket' => $ticket->ticket_no, 'cells' => [
                    $ticket->ticket_no, $ticket->kind(), $ticket->customer->name, $ticket->device->displayName(), $ticket->status,
                    $ticket->technician?->name, $ticket->received_date, $age,
                    $age <= 3 ? ['ok', 'Trong hạn'] : ($age <= $overdueDays ? ['warn', 'Cần theo dõi'] : ['bad', "Quá {$overdueDays} ngày"]),
                    $note,
                ]];
            })->values()->all(),
        ];

        $shipments = RmaCenterShipment::query()
            ->whereIn('rma_ticket_id', RmaTicket::query()->ofKind($kind)->select('id'))
            ->get();
        $sections[] = [
            'id' => 'center', 'title' => 'Theo hãng / TTBH', 'note' => 'Lần gửi / hẹn và kết quả trong kỳ; "Đang giữ" tính đến hôm nay', 'wide' => false,
            'columns' => [['Hãng / TTBH', 'text'], ['Gửi / hẹn', 'num'], ['Đang giữ', 'num'], ['Đã xong', 'num'], ['Đổi mới', 'num'], ['Từ chối BH', 'num'], ['TB ngày', 'days']],
            'rows' => ServiceCenter::orderBy('name')->get()->map(function (ServiceCenter $center) use ($shipments, $inRange, $days) {
                $list = $shipments->where('service_center_id', $center->id);
                $back = $list->filter(fn (RmaCenterShipment $shipment) => $shipment->outcome !== ShipmentOutcome::Pending && $inRange($shipment->back_date));
                $completed = $back->filter(fn (RmaCenterShipment $shipment) => $shipment->outcome->isCompleted());
                $durations = $completed->map(fn (RmaCenterShipment $shipment) => $days($shipment->sent_date, $shipment->back_date));

                return ['cells' => [
                    $center->name,
                    $list->filter(fn (RmaCenterShipment $shipment) => $inRange($shipment->sent_date))->count(),
                    $list->where('outcome', ShipmentOutcome::Pending)->count(),
                    $completed->count(),
                    $completed->where('outcome', ShipmentOutcome::Replaced)->count(),
                    $back->where('outcome', ShipmentOutcome::Rejected)->count(),
                    $durations->isEmpty() ? null : round($durations->avg(), 1),
                ]];
            })->values()->all(),
        ];

        $sections[] = [
            'id' => 'tech', 'title' => 'Theo nhân viên phụ trách', 'note' => 'Nhận, hoàn tất trong kỳ; "Đang giữ" tính đến hôm nay', 'wide' => false,
            'columns' => [['Nhân viên', 'text'], ['Nhận', 'num'], ['Hoàn tất', 'num'], ['Đang giữ', 'num'], ['TB xử lý', 'days'], ['Doanh thu', 'money']],
            'rows' => User::orderBy('name')->get()->map(function (User $user) use ($tickets, $stat) {
                $row = $stat($tickets->where('technician_id', $user->id));

                return ['cells' => [$user->name, $row['received'], $row['finished'], $row['open'], $row['avg_days'], $row['revenue']]];
            })->values()->all(),
        ];

        $paid = $tickets->filter(fn (RmaTicket $ticket) => $ticket->is_chargeable && ($inRange($ticket->received_date) || $inRange($ticket->returned_date)))->values();
        $sections[] = [
            'id' => 'erp', 'title' => 'Doanh thu và đối soát ERP', 'note' => 'Phiếu có phí nhận hoặc trả trong kỳ', 'wide' => true,
            'columns' => [['Số phiếu', 'ticket'], ['Khách hàng', 'text'], ['Ngày nhận', 'date'], ['Ngày trả', 'date'], ['Trạng thái', 'status'], ['Số tiền', 'money'], ['V223', 'text'], ['V233', 'text'], ['Chứng từ', 'severity']],
            'rows' => [
                ...$paid->map(fn (RmaTicket $ticket) => ['ticket' => $ticket->ticket_no, 'cells' => [
                    $ticket->ticket_no, $ticket->customer->name, $ticket->received_date, $ticket->returned_date, $ticket->status, $ticket->charge_amount,
                    $ticket->erp_receipt_no, $ticket->erp_return_no,
                    match (true) {
                        filled($ticket->erp_receipt_no) && filled($ticket->erp_return_no) => ['ok', 'Đủ chứng từ'],
                        blank($ticket->erp_receipt_no) => ['bad', 'Thiếu V223'],
                        default => ['warn', 'Chưa có V233'],
                    },
                ]])->all(),
                ['total' => true, 'cells' => ['Tổng', $paid->count().' phiếu', null, null, null, (int) $paid->sum('charge_amount'), null, null, null]],
            ],
        ];

        $underWarranty = $tickets
            ->filter(fn (RmaTicket $ticket) => $ticket->repairWarrantyEndsOn()?->gte(today()) === true)
            ->sortBy(fn (RmaTicket $ticket) => $ticket->repairWarrantyEndsOn()->timestamp);
        $sections[] = [
            'id' => 'repair_warranty', 'title' => 'Máy đang trong bảo hành sau sửa', 'note' => 'Tính đến ngày '.today()->format('d/m/Y').', không theo kỳ', 'wide' => false,
            'columns' => [['Số phiếu', 'ticket'], ['Khách hàng', 'text'], ['Serial', 'mono'], ['Ngày trả', 'date'], ['Số tháng', 'num'], ['Hết hạn', 'date']],
            'rows' => $underWarranty->map(fn (RmaTicket $ticket) => ['ticket' => $ticket->ticket_no, 'cells' => [
                $ticket->ticket_no, $ticket->customer->name, $ticket->currentDevice()->serial_number, $ticket->returned_date, $ticket->repair_warranty_months, $ticket->repairWarrantyEndsOn(),
            ]])->values()->all(),
        ];

        $claims = $received->filter(fn (RmaTicket $ticket) => $ticket->claim_ticket_id !== null);
        $sections[] = [
            'id' => 'claims', 'title' => 'Khách quay lại bảo hành sau sửa', 'wide' => true,
            'note' => 'Nhận trong kỳ: '.$claims->count().' phiếu · trong phạm vi '.$claims->where('claim_result', ClaimResult::Covered)->count()
                .' · ngoài phạm vi '.$claims->where('claim_result', ClaimResult::Rejected)->count().' · đang kiểm tra '.$claims->whereNull('claim_result')->count(),
            'columns' => [['Số phiếu', 'ticket'], ['Phiếu gốc', 'text'], ['Khách hàng', 'text'], ['Serial', 'mono'], ['Ngày nhận', 'date'], ['Kết luận', 'text'], ['Hạng mục / lý do', 'text']],
            'rows' => $claims->map(fn (RmaTicket $ticket) => ['ticket' => $ticket->ticket_no, 'cells' => [
                $ticket->ticket_no, $ticket->claimTicket?->ticket_no, $ticket->customer->name, $ticket->device->serial_number, $ticket->received_date,
                $ticket->claim_result?->label() ?? 'Đang kiểm tra',
                $ticket->claimItem?->description ?? $ticket->claim_note,
            ]])->values()->all(),
        ];

        $sections[] = [
            'id' => 'brand', 'title' => 'Theo hãng và loại thiết bị', 'note' => 'Phiếu nhận trong kỳ', 'wide' => false,
            'columns' => [['Hãng', 'text'], ['Loại thiết bị', 'text'], ['Số phiếu', 'num'], ['Bảo hành', 'num'], ['Sửa chữa', 'num']],
            'rows' => $received->groupBy(fn (RmaTicket $ticket) => $ticket->device->productModel->brand->name.'|'.$ticket->device->productModel->deviceType->name)
                ->map(fn (Collection $group) => ['cells' => [
                    $group->first()->device->productModel->brand->name,
                    $group->first()->device->productModel->deviceType->name,
                    $group->count(),
                    $group->filter(fn (RmaTicket $ticket) => $ticket->service_type->isWarranty())->count(),
                    $group->where('service_type', ServiceType::Repair)->count(),
                ]])
                ->sortByDesc(fn (array $row) => $row['cells'][2])->values()->all(),
        ];

        $repeat = Device::query()
            ->whereHas('tickets', fn (Builder $query) => $query->ofKind($kind), '>=', 2)
            ->withCount(['tickets' => fn (Builder $query) => $query->ofKind($kind)])
            ->with(['productModel.brand', 'productModel.deviceType', 'tickets' => fn ($query) => $query->orderBy('received_date')])
            ->orderByDesc('tickets_count')
            ->limit(50)
            ->get();
        $sections[] = [
            'id' => 'repeat', 'title' => 'Máy gửi nhiều lần', 'note' => 'Toàn bộ dữ liệu, không theo kỳ', 'wide' => false,
            'columns' => [['Serial', 'mono'], ['Thiết bị', 'text'], ['Số lần', 'num'], ['Lần gần nhất', 'date'], ['Các phiếu', 'text']],
            'rows' => $repeat->map(fn (Device $device) => ['cells' => [
                $device->serial_number, $device->displayName(), $device->tickets_count, $device->tickets->last()?->received_date, $device->tickets->pluck('ticket_no')->implode(', '),
            ]])->all(),
        ];

        return [
            'kpis' => $kpis,
            'months' => $this->months($from, $to, $kind),
            'sections' => $sections,
            'received' => $received,
        ];
    }

    /**
     * Tickets received per month for the six months ending with the report's last month.
     *
     * @return list<array{label: string, ym: string, total: int, by_kind: array<string, int>, in_period: bool}>
     */
    private function months(CarbonInterface $from, CarbonInterface $to, ?TicketKind $kind): array
    {
        $to = Carbon::instance($to)->min(today()->endOfDay())->max(Carbon::instance($from));
        $start = $to->copy()->startOfMonth()->subMonthsNoOverflow(5);
        $rows = RmaTicket::query()
            ->ofKind($kind)
            ->whereBetween('received_date', [$start->toDateString(), $to->copy()->endOfMonth()->toDateString()])
            ->get(['received_date', 'service_type', 'onsite_location']);

        $months = [];

        for ($month = $start->copy(); $month->lte($to); $month->addMonthNoOverflow()) {
            $list = $rows->filter(fn (RmaTicket $ticket) => $ticket->received_date->isSameMonth($month));
            $months[] = [
                'label' => 'T'.$month->month.'/'.$month->format('y'),
                'ym' => $month->format('m/Y'),
                'total' => $list->count(),
                'by_kind' => collect(TicketKind::cases())->mapWithKeys(fn (TicketKind $type) => [$type->shortLabel() => $list->filter(fn (RmaTicket $ticket) => $ticket->kind() === $type)->count()])->all(),
                'in_period' => $month->copy()->endOfMonth()->gte($from) && $month->lte($to),
            ];
        }

        return $months;
    }
}
