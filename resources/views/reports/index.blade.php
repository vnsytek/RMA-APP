@php
    use App\Enums\TicketKind;

    $query = fn (array $extra) => array_filter([
        'preset' => $preset,
        'from' => $preset ? null : $from->toDateString(),
        'to' => $preset ? null : $to->toDateString(),
        'kind' => $kind?->value,
        ...$extra,
    ], fn ($value) => $value !== null && $value !== '');

    $months = $report['months'];
    $max = max(1, ...array_column($months, 'total'));
    $step = $max <= 5 ? 1 : ($max <= 10 ? 2 : ($max <= 25 ? 5 : 10));
    $top = (int) ceil($max / $step) * $step;
    [$width, $height, $left, $right, $plotTop, $bottom] = [640, 210, 30, 8, 18, 26];
    $plotHeight = $height - $plotTop - $bottom;
    $band = ($width - $left - $right) / count($months);
    $barWidth = min(24, $band * 0.5);
    $baseline = $plotTop + $plotHeight;
    $numeric = ['num', 'money', 'days', 'pct'];
@endphp

<x-layouts.app title="Báo cáo" subtitle="Thống kê theo kỳ, phiếu tồn, hãng / TTBH, nhân viên, doanh thu và đối soát ERP">
    <x-slot:actions>
        <a href="{{ route('reports.export', $query([])) }}" class="btn btn-primary">Xuất báo cáo Excel</a>
    </x-slot:actions>

    <div class="mb-3 flex flex-wrap items-end gap-3">
        <div class="segmented">
            @foreach ($presets as $key => $label)
                <a href="{{ route('reports.index', array_filter(['preset' => $key, 'kind' => $kind?->value])) }}" @class(['is-active' => $preset === $key, 'hover:bg-panel' => $preset !== $key])>{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            @if ($kind) <input type="hidden" name="kind" value="{{ $kind->value }}"> @endif
            <div><label class="label" for="from">Từ ngày</label><input class="input" type="date" name="from" id="from" value="{{ $from->toDateString() }}"></div>
            <div><label class="label" for="to">Đến ngày</label><input class="input" type="date" name="to" id="to" value="{{ $to->toDateString() }}"></div>
            <button class="btn btn-secondary">Xem</button>
        </form>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="segmented">
            <a href="{{ route('reports.index', $query(['kind' => null])) }}" @class(['is-active' => $kind === null, 'hover:bg-panel' => $kind !== null])>Tất cả hình thức</a>
            @foreach (TicketKind::cases() as $type)
                <a href="{{ route('reports.index', $query(['kind' => $type->value])) }}" @class(['is-active' => $kind === $type, 'hover:bg-panel' => $kind !== $type])>{{ $type->label() }}</a>
            @endforeach
        </div>
        <span class="text-sm text-muted">Kỳ báo cáo: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</span>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($report['kpis'] as $kpi)
            <div class="card p-4">
                <div class="text-xs text-muted">{{ $kpi['label'] }}</div>
                <div class="mt-0.5 text-2xl font-semibold">{{ $kpi['value'] }}</div>
                <div class="text-xs text-muted">{{ $kpi['sub'] }}</div>
            </div>
        @endforeach
    </div>

    <section class="card">
        <div class="flex flex-wrap items-baseline justify-between gap-2 px-4 pt-4 pb-2">
            <h2 class="font-semibold">Phiếu nhận theo tháng</h2>
            <p class="text-xs text-muted">6 tháng gần nhất · cột đậm là tháng trong kỳ báo cáo · rê chuột vào cột để xem chi tiết</p>
        </div>
        <div class="px-4 pb-3">
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" role="img" aria-label="Số phiếu nhận theo tháng">
                @for ($value = 0; $value <= $top; $value += $step)
                    @php $y = $baseline - $value / $top * $plotHeight; @endphp
                    <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#d8dfda" stroke-width="1" />
                    <text x="{{ $left - 8 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="11" fill="#5b6a62">{{ $value }}</text>
                @endfor
                @foreach ($months as $index => $month)
                    @php
                        $x = $left + $band * $index + ($band - $barWidth) / 2;
                        $barHeight = $month['total'] / $top * $plotHeight;
                        $radius = min(4, $barHeight);
                        $y = $baseline - $barHeight;
                    @endphp
                    <g>
                        <title>Tháng {{ $month['ym'] }}: {{ $month['total'] }} phiếu — {{ collect($month['by_kind'])->map(fn ($count, $label) => "{$label} {$count}")->implode(' · ') }}</title>
                        <rect x="{{ $left + $band * $index }}" y="{{ $plotTop }}" width="{{ $band }}" height="{{ $plotHeight }}" fill="transparent" />
                        @if ($barHeight > 0)
                            <path d="M{{ $x }},{{ $baseline }} V{{ $y + $radius }} Q{{ $x }},{{ $y }} {{ $x + $radius }},{{ $y }} H{{ $x + $barWidth - $radius }} Q{{ $x + $barWidth }},{{ $y }} {{ $x + $barWidth }},{{ $y + $radius }} V{{ $baseline }} Z"
                                  fill="#1f4a3d" opacity="{{ $month['in_period'] ? 1 : 0.35 }}" />
                        @endif
                        @if ($loop->last || $month['total'] === $max)
                            <text x="{{ $x + $barWidth / 2 }}" y="{{ $y - 6 }}" text-anchor="middle" font-size="12" font-weight="600" fill="#16211c">{{ $month['total'] }}</text>
                        @endif
                        <text x="{{ $x + $barWidth / 2 }}" y="{{ $height - 6 }}" text-anchor="middle" font-size="11" fill="#5b6a62">{{ $month['label'] }}</text>
                    </g>
                @endforeach
            </svg>
        </div>
        <details class="px-4 pb-4 text-sm">
            <summary class="cursor-pointer text-muted">Xem dạng bảng</summary>
            <div class="mt-2 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tháng</th>@foreach (array_keys($months[0]['by_kind']) as $label)<th class="text-right">{{ $label }}</th>@endforeach<th class="text-right">Tổng</th></tr></thead>
                    <tbody>
                        @foreach ($months as $month)
                            <tr><td>{{ $month['label'] }}</td>@foreach ($month['by_kind'] as $count)<td class="text-right">{{ $count }}</td>@endforeach<td class="text-right">{{ $month['total'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <div class="mt-4 grid items-start gap-4 xl:grid-cols-2">
        @foreach ($report['sections'] as $section)
            <section @class(['card overflow-hidden', 'xl:col-span-2' => $section['wide']])>
                <div class="flex flex-wrap items-baseline justify-between gap-2 px-4 pt-4 pb-2">
                    <h2 class="font-semibold">{{ $section['title'] }}</h2>
                    <p class="text-xs text-muted">{{ $section['note'] }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                @foreach ($section['columns'] as [$heading, $type])
                                    <th @class(['text-right' => in_array($type, $numeric, true)])>{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($section['rows'] as $row)
                                <tr @class(['bg-panel font-semibold' => $row['total'] ?? false]) @isset($row['ticket']) data-href="{{ route('tickets.show', $row['ticket']) }}" @endisset>
                                    @foreach ($row['cells'] as $index => $value)
                                        @php $type = $section['columns'][$index][1]; @endphp
                                        <td @class(['text-right whitespace-nowrap' => in_array($type, $numeric, true)])>
                                            @if ($value === null || $value === '')
                                                @if ($type === 'money') {{ money_vnd(0) }} @else <span class="text-muted">—</span> @endif
                                            @else
                                                @switch($type)
                                                    @case('ticket') <a href="{{ route('tickets.show', $value) }}" class="ticket-no">{{ $value }}</a> @break
                                                    @case('kind') <x-kind-tag :kind="$value" /> @break
                                                    @case('status') <x-status-pill :status="$value" /> @break
                                                    @case('date') {{ vn_date($value) }} @break
                                                    @case('money') {{ is_int($value) ? money_vnd($value) : $value }} @break
                                                    @case('days') {{ number_format($value, 1, ',', '.') }} ngày @break
                                                    @case('mono') <span class="font-mono text-xs">{{ $value }}</span> @break
                                                    @case('pct')
                                                        <span class="mr-2 inline-block h-2 w-20 overflow-hidden rounded bg-brand-soft align-middle"><span class="block h-full rounded bg-brand" style="width: {{ round($value * 100) }}%"></span></span>{{ round($value * 100) }}%
                                                        @break
                                                    @case('severity')
                                                        <span @class(['inline-flex items-center gap-1.5 text-xs font-semibold whitespace-nowrap', 'text-emerald-700' => $value[0] === 'ok', 'text-amber-700' => $value[0] === 'warn', 'text-red-700' => $value[0] === 'bad'])>
                                                            <span class="size-2 rounded-full bg-current"></span>{{ $value[1] }}
                                                        </span>
                                                        @break
                                                    @default {{ $value }}
                                                @endswitch
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($section['columns']) }}" class="text-muted">Không có dữ liệu.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>
