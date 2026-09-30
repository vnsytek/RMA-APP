@props(['contact' => null, 'bag' => 'contact', 'prefix' => 'contact'])

@php
    $failed = $errors->getBag($bag)->any();
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label class="label" for="{{ $prefix }}-name">Tên người liên hệ *</label>
        <input class="input" name="name" id="{{ $prefix }}-name" required maxlength="255" value="{{ $failed ? old('name') : $contact?->name }}" placeholder="VD: Chị Nga">
        <x-field-error name="name" :bag="$bag" />
    </div>
    <div>
        <label class="label" for="{{ $prefix }}-phone">Số điện thoại *</label>
        <input class="input" name="phone" id="{{ $prefix }}-phone" required maxlength="20" inputmode="tel" value="{{ $failed ? old('phone') : $contact?->phone }}" placeholder="VD: 0901 234 567">
        <x-field-error name="phone" :bag="$bag" />
    </div>
</div>
