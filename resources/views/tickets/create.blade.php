@php
    use App\Enums\TicketKind;
    use App\Enums\WarrantyStatus;

    $warranty = old('warranty_status', WarrantyStatus::OutOfWarranty->value);
    $kindValue = old('kind', TicketKind::Repair->value);
    $typeValue = (string) old('device_type_id', '');
    $brandValue = (string) old('brand_id', '');
    $modelValue = (string) old('product_model_id', '');
    $initialModels = is_numeric($typeValue) && is_numeric($brandValue)
        ? $models->where('device_type_id', (int) $typeValue)->where('brand_id', (int) $brandValue)
        : collect();
    $customerValue = (string) old('customer_id', $prefill['customer'] ?? 'new');
@endphp

<x-layouts.app title="Lập phiếu mới" :subtitle="'Số phiếu dự kiến: '.$nextTicketNo.' · Ngày '.today()->format('d/m/Y')">
    <x-slot:actions>
        <a href="{{ route('tickets.index') }}" class="btn btn-secondary">← Danh sách phiếu</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="card max-w-5xl p-5" id="ticket-form"
          data-models-url="{{ route('lookup.models') }}" data-device-url="{{ route('lookup.devices') }}" data-claim-old="{{ old('claim_ticket_id') }}"
          data-type-names='@json($deviceTypes->pluck('name'))' data-brand-names='@json($brands->pluck('name'))'>
        @csrf

        <div class="section-title mt-0">Thiết bị</div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-3">
                <label class="label" for="serial_number">Số serial / Service Tag *</label>
                <input class="input font-mono" name="serial_number" id="serial_number" value="{{ old('serial_number', $prefill['serial'] ?? '') }}" required maxlength="100"
                       placeholder="Nhập serial trước: máy đã có sẽ tự điền loại, hãng, model" autocomplete="off">
                <x-field-error name="serial_number" />
                <div id="device-box" class="mt-2 text-sm" aria-live="polite"></div>
                <x-field-error name="claim_ticket_id" />
            </div>
            <div>
                <label class="label" for="device_type_id">Loại thiết bị *</label>
                <select class="input" name="device_type_id" id="device_type_id" required>
                    <option value="">Chọn loại…</option>
                    @foreach ($deviceTypes as $type)
                        <option value="{{ $type->id }}" @selected($typeValue === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                    <option value="new" @selected($typeValue === 'new')>+ Loại mới…</option>
                </select>
                <input class="input mt-2" name="new_device_type" id="new_device_type" value="{{ old('new_device_type') }}" placeholder="Tên loại mới, VD: Máy chiếu" maxlength="100" @if ($typeValue !== 'new') hidden @endif>
                <p class="mt-1 text-xs text-muted" data-new-hint="device_type"></p>
                <x-field-error name="device_type_id" />
                <x-field-error name="new_device_type" />
            </div>
            <div>
                <label class="label" for="brand_id">Hãng *</label>
                <select class="input" name="brand_id" id="brand_id" required>
                    <option value="">Chọn hãng…</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected($brandValue === (string) $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                    <option value="new" @selected($brandValue === 'new')>+ Hãng mới…</option>
                </select>
                <input class="input mt-2" name="new_brand" id="new_brand" value="{{ old('new_brand') }}" placeholder="Tên hãng mới, VD: LENOVO" maxlength="100" @if ($brandValue !== 'new') hidden @endif>
                <p class="mt-1 text-xs text-muted" data-new-hint="brand"></p>
                <x-field-error name="brand_id" />
                <x-field-error name="new_brand" />
            </div>
            <div>
                <label class="label" for="product_model_id">Model *</label>
                <select class="input" name="product_model_id" id="product_model_id" required @disabled($typeValue === '' || $brandValue === '')>
                    @if ($typeValue === '' || $brandValue === '')
                        <option value="">Chọn loại và hãng trước</option>
                    @elseif ($typeValue === 'new' || $brandValue === 'new')
                        <option value="new" selected>+ Model mới…</option>
                    @else
                        <option value="">{{ $initialModels->isEmpty() ? 'Chưa có model nào' : 'Chọn model…' }}</option>
                        @foreach ($initialModels as $model)
                            <option value="{{ $model->id }}" @selected($modelValue === (string) $model->id)>{{ $model->code }}</option>
                        @endforeach
                        <option value="new" @selected($modelValue === 'new')>+ Model mới…</option>
                    @endif
                </select>
                <input class="input mt-2" name="new_model_code" id="new_model_code" value="{{ old('new_model_code') }}" placeholder="Mã model mới, VD: U2723QE" maxlength="100" @if ($modelValue !== 'new' && $typeValue !== 'new' && $brandValue !== 'new') hidden @endif>
                <p class="mt-1 text-xs text-muted" data-new-hint="model"></p>
                <x-field-error name="product_model_id" />
                <x-field-error name="new_model_code" />
            </div>
        </div>

        <div class="section-title">Tình trạng bảo hành <span class="font-normal tracking-normal normal-case">· nhân viên tự chọn</span></div>
        <div class="segmented" role="radiogroup" aria-label="Tình trạng bảo hành">
            @foreach (WarrantyStatus::cases() as $status)
                <label><input type="radio" name="warranty_status" value="{{ $status->value }}" class="sr-only" @checked($warranty === $status->value)>{{ $status->label() }}</label>
            @endforeach
        </div>
        <x-field-error name="warranty_status" />

        <div class="section-title">Hình thức xử lý</div>
        <div class="segmented" role="radiogroup" aria-label="Hình thức xử lý">
            @foreach (TicketKind::cases() as $kind)
                <label><input type="radio" name="kind" value="{{ $kind->value }}" class="sr-only" @checked($kindValue === $kind->value) data-description="{{ $kind->description() }}">{{ $kind->label() }}</label>
            @endforeach
        </div>
        <p class="mt-1 text-xs text-muted" id="kind-hint"></p>
        <x-field-error name="kind" />

        <div class="section-title">Khách hàng</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label" for="customer_id">Khách gửi máy</label>
                <select class="input" name="customer_id" id="customer_id">
                    <option value="new">+ Khách mới</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected($customerValue === (string) $customer->id)>{{ $customer->name }} · {{ $customer->phone }}</option>
                    @endforeach
                </select>
            </div>
            <div data-new-customer>
                <label class="label" for="customer_name">Tên khách / công ty *</label>
                <input class="input" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" maxlength="255">
                <x-field-error name="customer_name" />
            </div>
            <div data-new-customer>
                <label class="label" for="customer_phone">Số điện thoại *</label>
                <input class="input" name="customer_phone" id="customer_phone" value="{{ old('customer_phone') }}" maxlength="20" inputmode="tel">
                <x-field-error name="customer_phone" />
            </div>
            <div data-new-customer>
                <label class="label" for="customer_contact">Người liên hệ <span class="label-hint">(tuỳ chọn)</span></label>
                <input class="input" name="customer_contact" id="customer_contact" value="{{ old('customer_contact') }}" maxlength="255">
            </div>
            <div data-new-customer>
                <label class="label" for="customer_address">Địa chỉ <span class="label-hint">(tuỳ chọn)</span></label>
                <input class="input" name="customer_address" id="customer_address" value="{{ old('customer_address') }}" maxlength="500">
            </div>
        </div>

        <div class="section-title">Tiếp nhận</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label" for="fault_description">Lỗi khách báo *</label>
                <textarea class="input min-h-20" name="fault_description" id="fault_description" required maxlength="2000">{{ old('fault_description') }}</textarea>
                <x-field-error name="fault_description" />
            </div>
            <div data-takes-device>
                <label class="label" for="accessories">Phụ kiện kèm theo <span class="label-hint">(tuỳ chọn)</span></label>
                <input class="input" name="accessories" id="accessories" value="{{ old('accessories') }}" placeholder="Sạc, dây nguồn, remote…" maxlength="255">
            </div>
            <div>
                <label class="label" for="received_date"><span data-date-label>Ngày nhận máy</span> *</label>
                <input class="input" type="date" name="received_date" id="received_date" value="{{ old('received_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                <x-field-error name="received_date" />
            </div>
            <div>
                <label class="label" for="technician_id">Nhân viên phụ trách</label>
                <select class="input" name="technician_id" id="technician_id">
                    <option value="">Chưa phân công</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected((string) old('technician_id', auth()->id()) === (string) $technician->id)>{{ $technician->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="note">Ghi chú <span class="label-hint">(tuỳ chọn)</span></label>
                <input class="input" name="note" id="note" value="{{ old('note') }}" maxlength="2000">
            </div>
        </div>

        <div data-onsite-only>
            <div class="section-title">Hãng đến bảo hành</div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="service_center_id">Hãng / TTBH *</label>
                    <select class="input" name="service_center_id" id="service_center_id">
                        <option value="">Chọn…</option>
                        @foreach ($serviceCenters as $center)
                            <option value="{{ $center->id }}" @selected((string) old('service_center_id') === (string) $center->id)>{{ $center->name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="service_center_id" />
                </div>
                <div>
                    <label class="label" for="vendor_case_no">Mã hồ sơ của hãng <span class="label-hint">(VD: SER No)</span></label>
                    <input class="input font-mono" name="vendor_case_no" id="vendor_case_no" value="{{ old('vendor_case_no') }}" maxlength="50">
                </div>
                <div>
                    <label class="label" for="appointment_date">Ngày hẹn hãng đến</label>
                    <input class="input" type="date" name="appointment_date" id="appointment_date" value="{{ old('appointment_date') }}">
                    <x-field-error name="appointment_date" />
                </div>
            </div>
        </div>

        <div class="section-title">
            <label for="photos">
                <span data-photo-required>Ảnh tình trạng máy khi nhận <span class="text-red-700">*</span> <span class="font-normal tracking-normal normal-case">· bắt buộc ít nhất 1 ảnh</span></span>
                <span data-photo-optional hidden>Ảnh / chứng từ <span class="font-normal tracking-normal normal-case">· tuỳ chọn (hãng xử lý tại nhà khách, Sang Y không nhận máy)</span></span>
            </label>
        </div>
        <input type="file" name="photos[]" id="photos" multiple accept="image/*,application/pdf" class="input py-1.5" data-file-preview="photos-preview" required>
        <p class="mt-1 text-xs text-muted">{{ \App\Enums\AttachmentStage::Intake->hint() }} Có thể kèm phiếu bảo hành, hoá đơn (PDF). Tối đa {{ config('rma.attachments.max_files') }} tệp, mỗi tệp {{ intdiv(config('rma.attachments.max_kb'), 1024) }} MB; ảnh lớn được tự thu nhỏ trước khi gửi.</p>
        <div id="photos-preview" class="mt-2 flex flex-wrap gap-2"></div>
        <x-field-error name="photos" />
        @foreach ($errors->get('photos.*') as $messages)
            <p class="mt-1 text-xs text-red-700">{{ $messages[0] }}</p>
        @endforeach

        <x-field-error name="ticket" class="mt-4" />

        <div class="mt-6 flex justify-end gap-2 border-t border-line pt-4">
            <a href="{{ route('tickets.index') }}" class="btn btn-secondary">Hủy bỏ</a>
            <button class="btn btn-primary">Lập phiếu</button>
        </div>
    </form>
</x-layouts.app>
