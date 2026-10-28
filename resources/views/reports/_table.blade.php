@php
    $numeric = ['num', 'money', 'days', 'pct'];
@endphp

<section class="overflow-hidden rounded-lg border border-line bg-white">
    <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-line px-4 py-3">
        <h3 class="text-sm font-semibold">{{ $section['title'] }}</h3>
        <p class="text-xs text-muted">{{ $section['note'] }}</p>
    </header>
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
                            <td @class(['text-right whitespace-nowrap' => in_array($type, $numeric, true), 'border-b-0' => $loop->parent->last])>
                                @if ($value === null || $value === '')
                                    @if ($type === 'money') {{ money_vnd(0) }} @else <span class="text-muted/60">—</span> @endif
                                @else
                                    @switch($type)
                                        @case('ticket') <a href="{{ route('tickets.show', $value) }}" class="ticket-no whitespace-nowrap">{{ $value }}</a> @break
                                        @case('kind') <x-kind-tag :kind="$value" /> @break
                                        @case('status') <x-status-pill :status="$value" /> @break
                                        @case('date') {{ vn_date($value) }} @break
                                        @case('money') {{ is_int($value) ? money_vnd($value) : $value }} @break
                                        @case('days') {{ number_format($value, 1, ',', '.') }} ngày @break
                                        @case('mono') <span class="font-mono text-xs">{{ $value }}</span> @break
                                        @case('pct')
                                            <span class="mr-2 inline-block h-1.5 w-16 overflow-hidden rounded-full bg-brand-soft align-middle"><span class="block h-full rounded-full bg-brand" style="width: {{ round($value * 100) }}%"></span></span>{{ round($value * 100) }}%
                                            @break
                                        @case('severity')
                                            <x-report-status :level="$value[0]" :label="$value[1]" />
                                            @break
                                        @default {{ $value }}
                                    @endswitch
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['columns']) }}" class="border-b-0 py-8 text-center text-muted">Không có dữ liệu trong kỳ này.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if (! empty($section['paginator']) && $section['paginator']->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $section['paginator']->links() }}</div>
    @endif
</section>
