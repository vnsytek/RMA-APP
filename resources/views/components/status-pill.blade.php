@props(['status'])

<x-pill :tone="$status->tone()" {{ $attributes }}>{{ $status->label() }}</x-pill>
