@props(['tone' => 'gray'])

@php
    $tones = [
        'blue' => 'bg-sky-100 text-sky-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'violet' => 'bg-violet-100 text-violet-800',
        'fuchsia' => 'bg-fuchsia-100 text-fuchsia-800',
        'orange' => 'bg-orange-100 text-orange-800',
        'green' => 'bg-emerald-100 text-emerald-800',
        'gray' => 'bg-stone-200 text-stone-700',
        'red' => 'bg-red-100 text-red-800',
    ];
@endphp

<span {{ $attributes->class(['inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', $tones[$tone] ?? $tones['gray']]) }}>{{ $slot }}</span>
