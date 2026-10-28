@props(['level', 'label'])

{{-- Status never rides on colour alone: each level has its own symbol and a text label. --}}
@php
    [$symbol, $classes] = match ($level) {
        'ok' => ['✓', 'bg-emerald-50 text-emerald-800 ring-emerald-600/20'],
        'warn' => ['!', 'bg-amber-50 text-amber-800 ring-amber-600/25'],
        default => ['✕', 'bg-red-50 text-red-800 ring-red-600/20'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset', $classes]) }}>
    <span aria-hidden="true" class="text-[10px]">{{ $symbol }}</span>{{ $label }}
</span>
