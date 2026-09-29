<?php

namespace App\Http\Controllers;

use App\Enums\TicketKind;
use App\Services\ReportBuilder;
use App\Support\Spreadsheet\XlsxWriter;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public const PRESETS = [
        'this' => 'Tháng này',
        'last' => 'Tháng trước',
        'quarter' => '3 tháng gần nhất',
        'year' => 'Năm nay',
    ];

    public function index(Request $request, ReportBuilder $builder): View
    {
        [$from, $to, $kind, $preset] = $this->period($request);

        return view('reports.index', [
            'report' => $builder->build($from, $to, $kind),
            'from' => $from,
            'to' => $to,
            'kind' => $kind,
            'preset' => $preset,
            'presets' => self::PRESETS,
        ]);
    }

    public function export(Request $request, ReportBuilder $builder): BinaryFileResponse
    {
        [$from, $to, $kind] = $this->period($request);
        $report = $builder->build($from, $to, $kind);

        $company = mb_strtoupper(config('rma.company.name'));
        $period = 'Kỳ báo cáo: '.$from->format('d/m/Y').' – '.$to->format('d/m/Y').($kind ? ' · '.$kind->label() : '');
        $head = fn (string $title) => [[$company], [$title], [$period], []];
        $xlsx = new XlsxWriter;

        $xlsx->addSheet('Tổng quan', [
            ...$head('BÁO CÁO TỔNG HỢP RMA'),
            ...array_map(fn (array $kpi) => [$kpi['label'], $kpi['value'], $kpi['sub']], $report['kpis']),
            [],
            ['Phiếu nhận theo tháng'],
            ['Tháng', ...array_keys($report['months'][0]['by_kind'] ?? []), 'Tổng'],
            ...array_map(fn (array $month) => [$month['label'], ...array_values($month['by_kind']), $month['total']], $report['months']),
        ], [30, 20, 48, 14, 14, 14], [0, 11]);

        foreach ($report['sections'] as $section) {
            $xlsx->addSheet($section['title'], [
                ...$head(mb_strtoupper($section['title'])),
                [$section['note']],
                array_column($section['columns'], 0),
                ...array_map(fn (array $row) => array_map(
                    fn ($value, array $column) => $this->cell($value, $column[1]),
                    $row['cells'],
                    $section['columns'],
                ), $section['rows']),
            ], array_map(fn (array $column) => $column[1] === 'text' ? 28 : 15, $section['columns']), [0, 5]);
        }

        $xlsx->addSheet('Danh sách phiếu', [
            ...$head('DANH SÁCH PHIẾU NHẬN TRONG KỲ'),
            ['Số phiếu', 'Hình thức', 'Tình trạng BH', 'Trạng thái', 'Khách hàng', 'Người liên hệ', 'SĐT', 'Thiết bị', 'Serial nhận', 'Serial trả', 'Nhân viên phụ trách', 'Ngày nhận', 'Ngày trả', 'Kết quả', 'BH sau sửa (tháng)', 'Số tiền', 'V223', 'V233', 'Hãng/TTBH', 'Mã hồ sơ hãng', 'Số lần gửi TTBH'],
            ...$report['received']->map(fn ($ticket) => [
                $ticket->ticket_no, $ticket->kind()->label(), $ticket->warranty_status->label(), $ticket->status->label(), $ticket->customer->name,
                $ticket->customer->contact_name, $ticket->customer->phone, $ticket->device->displayName(), $ticket->device->serial_number,
                $ticket->returnedDevice ? $ticket->returnedDevice->serial_number.($ticket->isSwappedToOtherProduct() ? ' ('.$ticket->returnedDevice->productModel->shortName().')' : '') : null,
                $ticket->technician?->name, vn_date($ticket->received_date), vn_date($ticket->returned_date), $ticket->result?->label(),
                $ticket->hasRepairWarranty() ? $ticket->repair_warranty_months : null, $ticket->charge_amount, $ticket->erp_receipt_no,
                $ticket->erp_return_no, $ticket->shipments->last()?->serviceCenter->name, $ticket->shipments->last()?->vendor_case_no, $ticket->shipments->count(),
            ])->all(),
        ], [11, 20, 14, 16, 28, 18, 14, 32, 20, 24, 18, 11, 11, 32, 10, 12, 14, 14, 26, 16, 10], [0, 4]);

        return $xlsx->download('BaoCao_RMA_'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.xlsx');
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: ?TicketKind, 3: ?string}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(self::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'kind' => ['nullable', Rule::enum(TicketKind::class)],
        ]);

        $kind = isset($data['kind']) ? TicketKind::from($data['kind']) : null;

        if (isset($data['from'], $data['to']) && empty($data['preset'])) {
            $from = Carbon::parse($data['from']);
            $to = Carbon::parse($data['to']);

            return $from->lte($to) ? [$from, $to, $kind, null] : [$to, $from, $kind, null];
        }

        $preset = $data['preset'] ?? 'this';
        $today = today();

        [$from, $to] = match ($preset) {
            'last' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today->copy()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };

        return [$from, $to, $kind, $preset];
    }

    private function cell(mixed $value, string $type): string|int|float|null
    {
        return match (true) {
            $value === null => null,
            $value instanceof CarbonInterface => $value->format('d/m/Y'),
            $value instanceof BackedEnum && method_exists($value, 'label') => $value->label(),
            $type === 'pct' => round($value * 100, 1).'%',
            $type === 'severity' => $value[1],
            is_int($value) || is_float($value) => $value,
            default => (string) $value,
        };
    }
}
