@props(['ticket', 'visited'])

{{--
    Where the ticket stands in its kind's workflow. Steps come from the statuses the kind passes through;
    $visited (statuses from the ticket's history) tells which were actually reached, so a step the ticket
    jumped over (e.g. no quote because the fix was free) shows as skipped, not as done.
--}}
@php
    use App\Enums\TicketKind;
    use App\Enums\TicketStatus as S;

    $path = match ($ticket->kind()) {
        TicketKind::Onsite => [S::Scheduled, S::Done],
        TicketKind::OnsiteSangy => [S::Scheduled, S::Ready, S::Returned],
        TicketKind::CarryIn => [S::Received, S::Inspecting, S::AtCenter, S::Ready, S::Returned],
        TicketKind::Repair => [S::Received, S::Inspecting, S::Quoted, S::Repairing, S::Ready, S::Returned],
    };

    $current = $ticket->status;
    $cancelled = $current === S::Cancelled;
    $reached = collect($visited)->push($current)->unique(fn ($status) => $status->value);
    $lastReached = collect($path)->keys()->filter(fn ($i) => $reached->contains($path[$i]))->max() ?? 0;
    $found = array_search($current, $path, true);
    $currentIndex = $found === false ? null : $found;

    $steps = collect($path)->map(function (S $status, int $i) use ($reached, $current, $currentIndex, $lastReached, $cancelled) {
        $state = match (true) {
            $status === $current && $current->isFinished() => 'done',
            $status === $current => 'current',
            $reached->contains($status) => 'done',
            $i < ($currentIndex ?? $lastReached) => 'skipped',
            default => 'upcoming',
        };

        return ['label' => $status->label(), 'state' => $state];
    });

    if ($cancelled) {
        // A cancelled ticket stops where it was: drop the steps it never got to and end on "Đã hủy".
        $steps = $steps->take($lastReached + 1)->push(['label' => $current->label(), 'state' => 'cancelled']);
    }

    $stateText = ['done' => 'đã xong', 'current' => 'đang ở bước này', 'skipped' => 'bỏ qua', 'upcoming' => 'chưa tới', 'cancelled' => 'phiếu đã hủy'];
    $position = $steps->search(fn ($step) => in_array($step['state'], ['current', 'cancelled'], true));
@endphp

<div {{ $attributes }}>
    <p class="mb-3 text-[13px] text-muted sm:hidden">
        @if ($position !== false)
            Bước {{ $position + 1 }}/{{ $steps->count() }}: <b class="text-ink">{{ $steps[$position]['label'] }}</b>
        @else
            Đã hoàn tất cả {{ $steps->count() }} bước
        @endif
    </p>
    <ol class="steps" aria-label="Tiến trình xử lý">
        @foreach ($steps as $step)
            <li class="step is-{{ $step['state'] }}" @if (in_array($step['state'], ['current', 'cancelled'], true)) aria-current="step" @endif>
                <span class="step-dot">
                    @if ($step['state'] === 'done')
                        <x-icon name="check" class="size-3" stroke-width="3" />
                    @elseif ($step['state'] === 'cancelled')
                        <x-icon name="x" class="size-3" stroke-width="3" />
                    @endif
                </span>
                <span class="max-sm:sr-only">{{ $step['label'] }}</span>
                <span class="sr-only">({{ $stateText[$step['state']] }})</span>
            </li>
        @endforeach
    </ol>
</div>
