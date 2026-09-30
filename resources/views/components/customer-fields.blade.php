@props(['customer' => null, 'prefix' => 'c'])

{{--
    Customer form fields shared by the "add" and "edit" dialogs.
    Old input is used only when this form came back with errors (bag "customer").
--}}
@php
    $failed = $errors->getBag('customer')->any();
    $value = fn (string $field) => $failed ? old($field) : $customer?->{$field};
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <x-tax-code-field :id="$prefix.'-tax_code'" :value="$value('tax_code')" bag="customer" class="sm:col-span-2"
                      :data-except="$customer?->id" />
    <div class="sm:col-span-2">
        <label class="label" for="{{ $prefix }}-name">Tên khách / công ty *</label>
        <input class="input" name="name" id="{{ $prefix }}-name" required maxlength="255" value="{{ $value('name') }}" data-tax-fill="name">
        <x-field-error name="name" bag="customer" />
    </div>
    <div class="sm:col-span-2">
        <label class="label" for="{{ $prefix }}-phone">Số điện thoại công ty <span class="label-hint">(tuỳ chọn · số của từng người thì thêm ở mục Người liên hệ)</span></label>
        <input class="input" name="phone" id="{{ $prefix }}-phone" maxlength="20" inputmode="tel" value="{{ $value('phone') }}">
        <x-field-error name="phone" bag="customer" />
    </div>
    <div class="sm:col-span-2">
        <label class="label" for="{{ $prefix }}-address">Địa chỉ</label>
        <textarea class="input min-h-16" name="address" id="{{ $prefix }}-address" maxlength="500" data-tax-fill="address">{{ $value('address') }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="label" for="{{ $prefix }}-note">Ghi chú</label>
        <textarea class="input min-h-16" name="note" id="{{ $prefix }}-note" maxlength="2000">{{ $value('note') }}</textarea>
    </div>
</div>
