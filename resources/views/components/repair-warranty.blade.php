@props(['warranty'])

@if ($warranty->level === \App\Enums\RepairWarrantyLevel::None)
    <span class="text-sm text-muted">Chưa có BH sửa chữa</span>
@else
    <div class="grid min-w-48 justify-items-start gap-1">
        <x-pill :tone="$warranty->level->tone()">{{ $warranty->label() }}</x-pill>
        @if ($warranty->level->isValid())
            <span class="block h-1.5 w-40 overflow-hidden rounded bg-brand-soft">
                <span @class(['block h-full rounded', 'bg-emerald-600' => $warranty->level === \App\Enums\RepairWarrantyLevel::Active, 'bg-amber-500' => $warranty->level === \App\Enums\RepairWarrantyLevel::Expiring]) style="width: {{ $warranty->remainingPercent() }}%"></span>
            </span>
        @endif
        <span class="text-xs text-muted">{{ $warranty->ticket->repair_warranty_months }} tháng · {{ $warranty->level->isValid() ? 'đến' : 'hết' }} {{ vn_date($warranty->endsOn) }}</span>
        <span class="text-xs text-muted">theo phiếu <a href="{{ route('tickets.show', $warranty->ticket) }}" class="ticket-no">{{ $warranty->ticket->ticket_no }}</a></span>
    </div>
@endif
