@props(['ticket', 'highlight' => null])

@php
    $items = $ticket->warrantyItems;
    $exclusions = $ticket->warrantyExclusionList();
@endphp

<div {{ $attributes->class(['space-y-2']) }}>
    @if ($items->isNotEmpty())
        <ul class="divide-y divide-line rounded-md border border-line bg-white">
            @foreach ($items as $item)
                <li @class(['flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm', 'bg-brand-soft' => $highlight === $item->id])>
                    <span>
                        {{ $item->description }}
                        <span class="text-xs text-muted">· {{ $item->months }} tháng{{ $item->endsOn() ? ' · đến '.vn_date($item->endsOn()) : '' }}</span>
                        @if ($highlight === $item->id)
                            <span class="text-xs font-semibold text-brand">· được bảo hành lần này</span>
                        @endif
                    </span>
                    <x-pill :tone="$item->level()->tone()">{{ $item->statusLabel() }}</x-pill>
                </li>
            @endforeach
        </ul>
    @elseif ($ticket->hasRepairWarranty())
        <p class="text-sm">Phiếu cũ chưa ghi hạng mục: bảo hành chung {{ $ticket->repair_warranty_months }} tháng{{ $ticket->returned_date ? ', đến '.vn_date($ticket->repairWarrantyEndsOn()) : ', tính từ ngày trả máy' }}.</p>
    @else
        <p class="text-sm text-muted">Không có hạng mục nào được bảo hành.</p>
    @endif

    @if ($exclusions !== [])
        <div class="text-sm">
            <span class="font-semibold">Không bảo hành:</span>
            <ul class="mt-0.5 list-disc pl-5 text-muted">
                @foreach ($exclusions as $exclusion)
                    <li>{{ $exclusion }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
