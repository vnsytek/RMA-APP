@props(['ticket'])

{{--
    How long Sang Y has held the ticket, with the same thresholds as the backlog report:
    ≤ 3 days on time, up to rma.overdue_days to watch, beyond that overdue. A closed ticket shows its total time.
--}}
@php
    use App\Enums\TicketStatus;

    $overdueDays = config('rma.overdue_days');
    $tones = [
        'ok' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        'warn' => 'bg-amber-50 text-amber-800 ring-amber-600/25',
        'bad' => 'bg-red-50 text-red-800 ring-red-600/20',
        'done' => 'bg-stone-100 text-stone-700 ring-stone-500/20',
    ];

    if ($ticket->status === TicketStatus::Cancelled) {
        $badge = null;
    } elseif ($ticket->isClosed()) {
        $badge = $ticket->returned_date
            ? ['done', 'Xong sau '.(int) $ticket->received_date->diffInDays($ticket->returned_date).' ngày', null]
            : null;
    } else {
        $age = (int) $ticket->received_date->diffInDays(today());
        $badge = match (true) {
            $age <= 3 => ['ok', $age === 0 ? 'Nhận hôm nay' : $age.' ngày', 'Trong hạn'],
            $age <= $overdueDays => ['warn', $age.' ngày', 'Cần theo dõi'],
            default => ['bad', $age.' ngày · quá hạn', "Quá {$overdueDays} ngày"],
        };
    }
@endphp

@if ($badge)
    <span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset', $tones[$badge[0]]]) }}
          @if ($badge[2]) title="{{ $badge[2] }}: tính từ ngày nhận đến hôm nay" @endif>{{ $badge[1] }}</span>
@endif
