@php
    use App\Enums\TicketKind;

    $query = fn (array $extra) => array_filter([
        'preset' => $preset,
        'from' => $preset ? null : $from->toDateString(),
        'to' => $preset ? null : $to->toDateString(),
        'kind' => $kind?->value,
        ...$extra,
    ], fn ($value) => $value !== null && $value !== '');

    // Categorical slots 1–4 of the validated reference palette, one fixed colour per kind
    // (colour follows the kind, never its rank or the active filter).
    $kindColors = array_combine(
        array_map(fn (TicketKind $type) => $type->shortLabel(), TicketKind::cases()),
        ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'],
    );

    // Monthly stacked columns: a round y-axis top and 2–5 recessive gridlines.
    $months = $report['months'];
    $max = max(1, ...array_column($months, 'total'));
    $step = $max <= 4 ? 1 : ($max <= 10 ? 2 : ($max <= 25 ? 5 : ($max <= 50 ? 10 : 25)));
    $top = (int) ceil($max / $step) * $step;
    $peak = max(array_column($months, 'total'));

    $sections = collect($report['sections'])->keyBy('id');
    $rowCount = fn (string $id) => collect($sections[$id]['rows'] ?? [])->reject(fn ($row) => $row['total'] ?? false)->count();

    $results = collect($sections['result']['rows'])->reject(fn ($row) => $row['total'] ?? false)->values();
    $resultMax = max(1, ...$results->map(fn ($row) => $row['cells'][1])->all() ?: [1]);

    $health = $report['backlog_health'];
    $healthTotal = array_sum($health);
    $overdueDays = config('rma.overdue_days');
    $healthLevels = [
        'ok' => ['Trong hạn', '≤ 3 ngày', '#0ca30c'],
        'warn' => ['Cần theo dõi', '4–'.$overdueDays.' ngày', '#fab219'],
        'bad' => ['Quá hạn', '> '.$overdueDays.' ngày', '#d03b3b'],
    ];

    $tabs = [
        'backlog' => ['Phiếu tồn', ['backlog'], $rowCount('backlog') ? ($sections['backlog']['paginator']?->total() ?? $rowCount('backlog')) : 0],
        'team' => ['Hình thức & nhân viên', ['kind', 'tech'], null],
        'center' => ['Hãng / TTBH', ['center'], null],
        'erp' => ['Doanh thu & ERP', ['erp'], $rowCount('erp')],
        'warranty' => ['Bảo hành sau sửa', ['repair_warranty', 'claims'], $rowCount('repair_warranty') + $rowCount('claims')],
        'device' => ['Thiết bị', ['brand', 'repeat'], null],
    ];
@endphp

<x-layouts.app title="Báo cáo" subtitle="Tổng quan tình hình tiếp nhận, xử lý và doanh thu theo kỳ">
    <x-slot:actions>
        <a href="{{ route('reports.export', $query([])) }}" class="btn btn-primary">
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path d="M10 2.75a.75.75 0 0 1 .75.75v7.19l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 0 1 1.06-1.06l2.22 2.22V3.5a.75.75 0 0 1 .75-.75ZM4 13.75a.75.75 0 0 1 .75.75v.75c0 .14.11.25.25.25h10a.25.25 0 0 0 .25-.25v-.75a.75.75 0 0 1 1.5 0v.75A1.75 1.75 0 0 1 15 17H5a1.75 1.75 0 0 1-1.75-1.75v-.75a.75.75 0 0 1 .75-.75Z"/></svg>
            Xuất Excel
        </a>
    </x-slot:actions>

    {{-- Filters: one row — period presets, custom range, kind. --}}
    <form method="GET" action="{{ route('reports.index') }}" class="card mb-5 flex flex-wrap items-end gap-x-3 gap-y-3 p-3" data-report-filter>
        <div>
            <span class="label">Kỳ báo cáo</span>
            <div class="segmented [&>*]:px-3">
                @foreach ($presets as $key => $label)
                    <a href="{{ route('reports.index', array_filter(['preset' => $key, 'kind' => $kind?->value])) }}" @class(['is-active' => $preset === $key, 'hover:bg-panel' => $preset !== $key])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
        @if ($preset)
            <input type="hidden" name="preset" value="{{ $preset }}">
        @endif
        <div class="flex items-end gap-2">
            <div><label class="label" for="from">Từ ngày</label><input class="input w-36 py-1.5" type="date" name="from" id="from" value="{{ $from->toDateString() }}"></div>
            <span class="pb-2 text-muted">–</span>
            <div><label class="label" for="to">Đến ngày</label><input class="input w-36 py-1.5" type="date" name="to" id="to" value="{{ $to->toDateString() }}"></div>
        </div>
        <div class="min-w-40">
            <label class="label" for="kind">Hình thức</label>
            <select class="input py-1.5" name="kind" id="kind">
                <option value="">Tất cả hình thức</option>
                @foreach (TicketKind::cases() as $type)
                    <option value="{{ $type->value }}" @selected($kind === $type)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary py-1.5">Xem</button>
    </form>

    {{-- Headline numbers --}}
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ($report['kpis'] as $kpi)
            <div class="card relative overflow-hidden p-4 last:col-span-2 lg:last:col-span-1">
                <div class="text-xs font-medium text-muted">{{ $kpi['label'] }}</div>
                <div class="mt-1 text-[26px] leading-tight font-semibold tracking-tight tabular-nums">{{ $kpi['value'] }}</div>
                <div @class(['mt-1 text-xs', 'text-muted' => ($kpi['tone'] ?? null) !== 'bad', 'font-semibold text-red-700' => ($kpi['tone'] ?? null) === 'bad'])>
                    @if (($kpi['tone'] ?? null) === 'bad')<span aria-hidden="true">✕ </span>@endif{{ $kpi['sub'] }}
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-5 grid items-stretch gap-4 lg:grid-cols-3">
        {{-- Tickets received per month, stacked by kind --}}
        <section class="card p-5 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Phiếu nhận theo tháng</h2>
                    <p class="text-xs text-muted">6 tháng gần nhất · tháng mờ nằm ngoài kỳ báo cáo</p>
                </div>
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Chú thích">
                    @foreach ($kindColors as $label => $color)
                        <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $color }}"></span>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-5 flex gap-2" role="img" aria-label="Biểu đồ cột số phiếu nhận theo tháng, chi tiết ở bảng bên dưới">
                <div class="relative h-52 w-6 shrink-0 text-right text-[11px] text-muted tabular-nums" aria-hidden="true">
                    @for ($value = 0; $value <= $top; $value += $step)
                        <span class="absolute right-0" style="bottom: {{ $value / $top * 100 }}%; transform: translateY(50%)">{{ $value }}</span>
                    @endfor
                </div>
                <div class="min-w-0 flex-1">
                    <div class="relative h-52">
                        @for ($value = 0; $value <= $top; $value += $step)
                            <div @class(['absolute inset-x-0 border-t', 'border-line' => $value === 0, 'border-line/60 border-dashed' => $value > 0]) style="bottom: {{ $value / $top * 100 }}%"></div>
                        @endfor
                        <div class="absolute inset-0 flex">
                            @foreach ($months as $month)
                                @php
                                    $tip = "Tháng {$month['ym']}: {$month['total']} phiếu\n".collect($month['by_kind'])->filter()->map(fn ($count, $label) => "{$label}: {$count}")->implode("\n");
                                @endphp
                                <div class="group relative flex flex-1 cursor-default flex-col items-center justify-end" data-tip="{{ $tip }}" tabindex="0">
                                    <div class="pointer-events-none absolute inset-x-1 inset-y-0 rounded-md bg-ink/[0.04] opacity-0 transition group-hover:opacity-100 group-focus:opacity-100"></div>
                                    @if ($month['total'] > 0)
                                        @if ($loop->last || $month['total'] === $peak)
                                            <span class="relative mb-1 text-xs font-semibold tabular-nums">{{ $month['total'] }}</span>
                                        @endif
                                        <div @class(['relative flex w-1/2 max-w-11 flex-col-reverse gap-[2px] overflow-hidden rounded-t transition', 'opacity-35' => ! $month['in_period']])
                                             style="height: {{ $month['total'] / $top * 100 }}%">
                                            @foreach ($month['by_kind'] as $label => $count)
                                                @if ($count > 0)
                                                    <span class="block min-h-[2px]" style="flex: {{ $count }} 1 0; background: {{ $kindColors[$label] }}"></span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-2 flex text-[11px] text-muted">
                        @foreach ($months as $month)
                            <span @class(['flex-1 text-center', 'font-semibold text-ink' => $month['in_period']])>{{ $month['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-xs font-semibold text-muted hover:text-ink">Xem số liệu dạng bảng</summary>
                <div class="mt-2 overflow-x-auto rounded-md border border-line">
                    <table class="table">
                        <thead><tr><th>Tháng</th>@foreach (array_keys($months[0]['by_kind']) as $label)<th class="text-right">{{ $label }}</th>@endforeach<th class="text-right">Tổng</th></tr></thead>
                        <tbody>
                            @foreach ($months as $month)
                                <tr><td>{{ $month['label'] }}</td>@foreach ($month['by_kind'] as $count)<td class="text-right">{{ $count }}</td>@endforeach<td class="text-right font-semibold">{{ $month['total'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        {{-- Backlog health --}}
        <section class="card flex flex-col p-5">
            <h2 class="font-semibold">Sức khoẻ phiếu tồn</h2>
            <p class="text-xs text-muted">Phiếu chưa đóng, tính đến hôm nay</p>

            <div class="mt-5 flex items-baseline gap-2">
                <span class="text-4xl font-semibold tracking-tight tabular-nums">{{ $healthTotal }}</span>
                <span class="text-sm text-muted">phiếu đang mở</span>
            </div>

            @if ($healthTotal)
                <div class="mt-4 flex h-3 gap-[2px] overflow-hidden rounded-full" role="img" aria-label="Tỷ lệ phiếu tồn theo tình trạng">
                    @foreach ($healthLevels as $level => [$label, $range, $color])
                        @if ($health[$level])
                            <span style="flex: {{ $health[$level] }} 1 0; background: {{ $color }}" data-tip="{{ $label }} ({{ $range }}): {{ $health[$level] }} phiếu"></span>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="mt-4 h-3 rounded-full bg-panel"></div>
            @endif

            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($healthLevels as $level => [$label, $range, $color])
                    <li class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2"><x-report-status :level="$level" :label="$label" /><span class="text-xs text-muted">{{ $range }}</span></span>
                        <span class="font-semibold tabular-nums">{{ $health[$level] }}</span>
                    </li>
                @endforeach
            </ul>

            <a href="#tab-backlog" data-tab-link="backlog" class="mt-auto pt-4 text-sm font-semibold text-brand hover:underline">Xem danh sách phiếu tồn →</a>
        </section>
    </div>

    {{-- Outcomes, ranked --}}
    <section class="card mb-5 p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold">Kết quả xử lý</h2>
            <p class="text-xs text-muted">{{ $sections['result']['note'] }}@if ($results->isNotEmpty()) · tổng {{ collect($sections['result']['rows'])->last()['cells'][1] }} phiếu @endif</p>
        </div>
        @if ($results->isEmpty())
            <p class="py-6 text-center text-sm text-muted">Chưa có phiếu hoàn tất hoặc bị hủy trong kỳ này.</p>
        @else
            <ul class="mt-4 grid gap-x-10 gap-y-3 md:grid-cols-2">
                @foreach ($results as $row)
                    @php [$label, $count, $share] = $row['cells']; @endphp
                    <li class="grid grid-cols-[minmax(0,11rem)_1fr_auto] items-center gap-3 text-sm" data-tip="{{ $label }}: {{ $count }} phiếu ({{ round($share * 100) }}%)">
                        <span class="truncate" title="{{ $label }}">{{ $label }}</span>
                        <span class="h-2.5 overflow-hidden rounded-full bg-panel"><span class="block h-full rounded-full bg-brand" style="width: {{ max(2, $count / $resultMax * 100) }}%"></span></span>
                        <span class="w-20 text-right tabular-nums"><b>{{ $count }}</b> <span class="text-xs text-muted">· {{ round($share * 100) }}%</span></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Detail tables, one tab at a time --}}
    <section data-tabs>
        <nav class="tabbar mb-3" role="tablist" aria-label="Bảng chi tiết">
            @foreach ($tabs as $key => [$label, $ids, $badge])
                <button type="button" role="tab" id="tab-{{ $key }}-button" aria-controls="tab-{{ $key }}" data-tab="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                       >
                    {{ $label }}
                    @if ($badge !== null)
                        <span class="tab-count">{{ $badge }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        @foreach ($tabs as $key => [$label, $ids])
            <div id="tab-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}-button" data-tab-panel="{{ $key }}" @class(['grid items-start gap-4', 'xl:grid-cols-2' => count($ids) > 1 && ! collect($ids)->contains(fn ($id) => $sections[$id]['wide'])]) @unless ($loop->first) hidden @endunless>
                @foreach ($ids as $id)
                    @include('reports._table', ['section' => $sections[$id]])
                @endforeach
            </div>
        @endforeach
    </section>
</x-layouts.app>
