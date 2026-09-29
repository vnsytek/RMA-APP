@props(['kind'])

@php
    $colors = [
        'violet' => 'border-violet-400 text-violet-800',
        'fuchsia' => 'border-fuchsia-400 bg-fuchsia-50 text-fuchsia-800',
        'blue' => 'border-sky-400 text-sky-800',
        'amber' => 'border-amber-500 text-amber-800',
    ];
@endphp

<span {{ $attributes->class(['inline-block rounded border px-1.5 text-[11px] font-semibold whitespace-nowrap', $colors[$kind->tone()]]) }}>{{ $kind->shortLabel() }}</span>
