@props(['name' => 'tax_code', 'id' => null, 'value' => null, 'bag' => 'default'])

{{--
    Tax code (MST) input with a "Tra MST" button. The lookup fills the fields marked
    data-tax-fill="name" / "address" in the same form; see setUpTaxLookup() in app.js.
--}}
@php
    $id ??= $name;
@endphp

<div {{ $attributes }}>
    <label class="label" for="{{ $id }}">Mã số thuế <span class="label-hint">(tuỳ chọn · tự điền tên, địa chỉ)</span></label>
    <div class="flex gap-2">
        <input class="input font-mono" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" maxlength="20" inputmode="numeric"
               placeholder="VD: 0202019370" autocomplete="off" data-tax-code>
        <button type="button" class="btn btn-secondary shrink-0" data-tax-lookup data-url="{{ route('lookup.tax-code') }}">Tra MST</button>
    </div>
    <p class="mt-1 text-xs" data-tax-result aria-live="polite"></p>
    <x-field-error :name="$name" :bag="$bag" />
</div>
