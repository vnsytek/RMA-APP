@props(['center' => null, 'bag' => 'serviceCenter', 'prefix' => 'center'])

@php
    $failed = $errors->getBag($bag)->any();
    $value = fn (string $field) => $failed ? old($field) : $center?->{$field};
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="label" for="{{ $prefix }}-name">Tên *</label>
        <input class="input" name="name" id="{{ $prefix }}-name" required maxlength="255" value="{{ $value('name') }}" placeholder="VD: TTBH ASUS Hải Phòng">
        <x-field-error name="name" :bag="$bag" />
    </div>
    <div>
        <label class="label" for="{{ $prefix }}-brands">Nhận bảo hành hãng</label>
        <input class="input" name="brands" id="{{ $prefix }}-brands" maxlength="255" value="{{ $value('brands') }}" placeholder="VD: DELL, HP">
    </div>
    <div>
        <label class="label" for="{{ $prefix }}-phone">Số điện thoại</label>
        <input class="input" name="phone" id="{{ $prefix }}-phone" maxlength="30" inputmode="tel" value="{{ $value('phone') }}">
    </div>
    <div class="sm:col-span-2">
        <label class="label" for="{{ $prefix }}-address">Địa chỉ</label>
        <textarea class="input min-h-16" name="address" id="{{ $prefix }}-address" maxlength="500">{{ $value('address') }}</textarea>
    </div>
</div>
