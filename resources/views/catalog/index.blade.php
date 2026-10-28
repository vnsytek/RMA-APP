@php
    // Reopen the tab (and dialog) whose form came back with errors.
    $failedTab = match (true) {
        $errors->getBag('productModel')->any() => 'models',
        $errors->getBag('brand')->any() => 'brands',
        $errors->getBag('deviceType')->any() => 'types',
        default => null,
    };
    $editingType = old('device_type_id');
    $activeTypes = $deviceTypes->where('is_active', true);
    $activeBrands = $brands->where('is_active', true);
    $searchText = fn (...$parts) => mb_strtolower(implode(' ', array_filter($parts)));
@endphp

<x-layouts.app title="Loại, hãng, model" subtitle="Danh mục thiết bị dùng khi lập phiếu. Trong form lập phiếu cũng thêm nhanh được.">
    <section data-tabs data-tabs-default="models" @if ($failedTab) data-tabs-force="{{ $failedTab }}" @endif>
        <nav class="tabbar mb-4" role="tablist" aria-label="Danh mục">
            @foreach (['models' => ['Model', $productModels], 'types' => ['Loại thiết bị', $deviceTypes], 'brands' => ['Hãng', $brands]] as $key => [$label, $items])
                <button type="button" role="tab" id="tab-{{ $key }}-button" aria-controls="tab-{{ $key }}" data-tab="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                       >
                    {{ $label }}
                    <span class="tab-count">{{ $items->where('is_active', true)->count() }}</span>
                </button>
            @endforeach
        </nav>

        {{-- Models --}}
        <div id="tab-models" role="tabpanel" aria-labelledby="tab-models-button" data-tab-panel="models" data-filterable>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <label class="search w-full flex-none sm:w-64"><x-icon name="search" /><input type="search" class="input" placeholder="Tìm mã hoặc tên model…" aria-label="Tìm model" data-filter-search></label>
                <select class="input w-auto" aria-label="Lọc theo loại" data-filter="type">
                    <option value="">Mọi loại</option>
                    @foreach ($deviceTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <select class="input w-auto" aria-label="Lọc theo hãng" data-filter="brand">
                    <option value="">Mọi hãng</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
                <label class="flex cursor-pointer items-center gap-2 px-1 text-sm text-muted"><input type="checkbox" class="accent-[var(--color-brand)]" data-filter-hidden> Hiện mục đã ẩn</label>
                <button type="button" class="btn btn-primary ml-auto" data-dialog-open="model-new"><x-icon name="plus" />Thêm model</button>
            </div>

            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Model</th><th>Hãng</th><th>Loại</th><th class="text-right">Số máy</th><th class="w-px"></th></tr></thead>
                    <tbody>
                        @foreach ($productModels as $model)
                            <tr data-row data-search="{{ $searchText($model->code, $model->name, $model->brand->name, $model->deviceType->name) }}"
                                data-type="{{ $model->device_type_id }}" data-brand="{{ $model->brand_id }}" data-active="{{ $model->is_active ? 1 : 0 }}"
                                @class(['text-muted' => ! $model->is_active])>
                                <td>
                                    <div class="font-mono text-[13px] font-semibold">{{ $model->code }}</div>
                                    @if ($model->name)<div class="text-xs text-muted">{{ $model->name }}</div>@endif
                                </td>
                                <td>{{ $model->brand->name }}</td>
                                <td>{{ $model->deviceType->name }}</td>
                                <td class="text-right">{{ $model->devices_count ?: '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @unless ($model->is_active)<span class="mr-2 text-xs">Đã ẩn</span>@endunless
                                    @can('admin')
                                        <form method="POST" action="{{ route('catalog.product-models.toggle', $model) }}" class="inline"
                                              @if ($model->is_active) data-confirm="Ẩn model {{ $model->code }}? Sẽ không chọn được khi lập phiếu, phiếu cũ vẫn giữ." @endif>
                                            @csrf @method('PATCH')
                                            <button class="cursor-pointer text-xs font-semibold text-muted hover:text-ink hover:underline">{{ $model->is_active ? 'Ẩn' : 'Hiện lại' }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        <tr data-filter-empty hidden><td colspan="5" class="py-8 text-center text-muted">Không tìm thấy model nào.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Device types --}}
        <div id="tab-types" role="tabpanel" aria-labelledby="tab-types-button" data-tab-panel="types" data-filterable hidden>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <label class="search w-full flex-none sm:w-64"><x-icon name="search" /><input type="search" class="input" placeholder="Tìm loại thiết bị…" aria-label="Tìm loại thiết bị" data-filter-search></label>
                <label class="flex cursor-pointer items-center gap-2 px-1 text-sm text-muted"><input type="checkbox" class="accent-[var(--color-brand)]" data-filter-hidden> Hiện mục đã ẩn</label>
                <button type="button" class="btn btn-primary ml-auto" data-dialog-open="type-new"><x-icon name="plus" />Thêm loại</button>
            </div>

            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Loại thiết bị</th><th>Không bảo hành mặc định</th><th class="text-right">Model</th><th class="w-px"></th></tr></thead>
                    <tbody>
                        @foreach ($deviceTypes as $type)
                            @php $exclusions = text_lines($type->warranty_exclusions); @endphp
                            <tr data-row data-search="{{ $searchText($type->name, $type->code) }}" data-active="{{ $type->is_active ? 1 : 0 }}" @class(['text-muted' => ! $type->is_active])>
                                <td>
                                    <div class="font-medium">{{ $type->name }}</div>
                                    <div class="font-mono text-[11px] text-muted">{{ $type->code }}</div>
                                </td>
                                <td class="max-w-md">
                                    @if ($exclusions)
                                        <div class="line-clamp-2 text-sm text-muted" title="{{ implode(', ', $exclusions) }}">{{ implode(' · ', $exclusions) }}</div>
                                    @else
                                        <span class="text-sm text-muted/70">Chưa cài</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($type->product_models_count)
                                        <button type="button" class="cursor-pointer font-semibold text-brand hover:underline" data-filter-goto="models" data-filter-key="type" data-filter-value="{{ $type->id }}"
                                                title="Xem các model {{ $type->name }}">{{ $type->product_models_count }}</button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @unless ($type->is_active)<span class="mr-2 text-xs">Đã ẩn</span>@endunless
                                    <button type="button" class="cursor-pointer text-xs font-semibold text-brand hover:underline" data-dialog-open="type-{{ $type->id }}">@can('admin') Sửa @else Xem @endcan</button>
                                </td>
                            </tr>
                        @endforeach
                        <tr data-filter-empty hidden><td colspan="4" class="py-8 text-center text-muted">Không tìm thấy loại nào.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Brands --}}
        <div id="tab-brands" role="tabpanel" aria-labelledby="tab-brands-button" data-tab-panel="brands" data-filterable hidden>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <label class="search w-full flex-none sm:w-64"><x-icon name="search" /><input type="search" class="input" placeholder="Tìm hãng…" aria-label="Tìm hãng" data-filter-search></label>
                <label class="flex cursor-pointer items-center gap-2 px-1 text-sm text-muted"><input type="checkbox" class="accent-[var(--color-brand)]" data-filter-hidden> Hiện mục đã ẩn</label>
                <button type="button" class="btn btn-primary ml-auto" data-dialog-open="brand-new"><x-icon name="plus" />Thêm hãng</button>
            </div>

            <div class="card max-w-2xl overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Hãng</th><th class="text-right">Model</th><th class="w-px"></th></tr></thead>
                    <tbody>
                        @foreach ($brands as $brand)
                            <tr data-row data-search="{{ $searchText($brand->name) }}" data-active="{{ $brand->is_active ? 1 : 0 }}" @class(['text-muted' => ! $brand->is_active])>
                                <td class="font-medium">{{ $brand->name }}</td>
                                <td class="text-right">
                                    @if ($brand->product_models_count)
                                        <button type="button" class="cursor-pointer font-semibold text-brand hover:underline" data-filter-goto="models" data-filter-key="brand" data-filter-value="{{ $brand->id }}"
                                                title="Xem các model {{ $brand->name }}">{{ $brand->product_models_count }}</button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @unless ($brand->is_active)<span class="mr-2 text-xs">Đã ẩn</span>@endunless
                                    @can('admin')
                                        <form method="POST" action="{{ route('catalog.brands.toggle', $brand) }}" class="inline"
                                              @if ($brand->is_active) data-confirm="Ẩn hãng {{ $brand->name }}? Sẽ không chọn được khi lập phiếu, phiếu cũ vẫn giữ." @endif>
                                            @csrf @method('PATCH')
                                            <button class="cursor-pointer text-xs font-semibold text-muted hover:text-ink hover:underline">{{ $brand->is_active ? 'Ẩn' : 'Hiện lại' }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        <tr data-filter-empty hidden><td colspan="3" class="py-8 text-center text-muted">Không tìm thấy hãng nào.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <p class="mt-4 text-xs text-muted">Danh mục không xoá được, chỉ ẩn (quyền Admin), để phiếu cũ vẫn hiển thị đúng. Mục đã ẩn không hiện khi lập phiếu.</p>

    {{-- Add model --}}
    <dialog id="model-new" class="modal" aria-labelledby="model-new-title" @if ($errors->getBag('productModel')->any()) data-open @endif>
        <form method="POST" action="{{ route('catalog.product-models.store') }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <h2 id="model-new-title">Thêm model</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="model-type">Loại thiết bị <span class="req">*</span></label>
                    <select class="input" name="device_type_id" id="model-type" required>
                        <option value="">Chọn loại…</option>
                        @foreach ($activeTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('device_type_id') === (string) $type->id && $failedTab === 'models')>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="device_type_id" bag="productModel" />
                </div>
                <div>
                    <label class="label" for="model-brand">Hãng <span class="req">*</span></label>
                    <select class="input" name="brand_id" id="model-brand" required>
                        <option value="">Chọn hãng…</option>
                        @foreach ($activeBrands as $brand)
                            <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="brand_id" bag="productModel" />
                </div>
                <div>
                    <label class="label" for="model-code">Mã model <span class="req">*</span></label>
                    <input class="input font-mono" name="code" id="model-code" required maxlength="100" value="{{ $failedTab === 'models' ? old('code') : '' }}" placeholder="VD: Latitude 5440">
                    <x-field-error name="code" bag="productModel" />
                </div>
                <div>
                    <label class="label" for="model-name">Tên gọi <span class="label-hint">(tuỳ chọn)</span></label>
                    <input class="input" name="name" id="model-name" maxlength="255" value="{{ $failedTab === 'models' ? old('name') : '' }}" placeholder="VD: Máy in laser">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm model</button>
            </div>
        </form>
    </dialog>

    {{-- Add device type --}}
    <dialog id="type-new" class="modal" aria-labelledby="type-new-title" @if ($failedTab === 'types' && ! $editingType) data-open @endif>
        <form method="POST" action="{{ route('catalog.device-types.store') }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <h2 id="type-new-title">Thêm loại thiết bị</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <div>
                <label class="label" for="type-name">Tên loại <span class="req">*</span></label>
                <input class="input" name="name" id="type-name" required maxlength="100" value="{{ $failedTab === 'types' && ! $editingType ? old('name') : '' }}" placeholder="VD: Máy chiếu">
                <x-field-error name="name" bag="deviceType" />
                <p class="mt-1 text-xs text-muted">Mã viết tắt tự tạo theo tên. Danh sách "không bảo hành" cài sau ở nút Sửa.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm loại</button>
            </div>
        </form>
    </dialog>

    {{-- Add brand --}}
    <dialog id="brand-new" class="modal" aria-labelledby="brand-new-title" @if ($failedTab === 'brands') data-open @endif>
        <form method="POST" action="{{ route('catalog.brands.store') }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <h2 id="brand-new-title">Thêm hãng</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <div>
                <label class="label" for="brand-name">Tên hãng <span class="req">*</span></label>
                <input class="input uppercase" name="name" id="brand-name" required maxlength="100" value="{{ $failedTab === 'brands' ? old('name') : '' }}" placeholder="VD: LENOVO">
                <x-field-error name="name" bag="brand" />
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm hãng</button>
            </div>
        </form>
    </dialog>

    {{-- Device type: default "không bảo hành" list, hide / show --}}
    @foreach ($deviceTypes as $type)
        @php $editing = (string) $editingType === (string) $type->id; @endphp
        <dialog id="type-{{ $type->id }}" class="modal" aria-labelledby="type-{{ $type->id }}-title" @if ($editing && $failedTab === 'types') data-open @endif>
            <div class="modal-body">
                <div class="modal-head">
                    <div>
                        <h2 id="type-{{ $type->id }}-title">{{ $type->name }}</h2>
                        <p class="text-xs text-muted">Mã {{ $type->code }} · {{ $type->product_models_count }} model</p>
                    </div>
                    <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
                </div>

                @can('admin')
                    <form method="POST" action="{{ route('catalog.device-types.update', $type) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="device_type_id" value="{{ $type->id }}">
                        <div>
                            <label class="label" for="type-{{ $type->id }}-exclusions">Không bảo hành mặc định <span class="label-hint">(mỗi dòng một mục)</span></label>
                            <textarea class="input min-h-32" name="warranty_exclusions" id="type-{{ $type->id }}-exclusions" maxlength="2000"
                                      placeholder="VD:&#10;Vỡ màn hình&#10;Vào nước">{{ $editing ? old('warranty_exclusions') : $type->warranty_exclusions }}</textarea>
                            <x-field-error name="warranty_exclusions" bag="deviceType" />
                            <p class="mt-1 text-xs text-muted">Tự điền vào bước "Sửa xong" và in lên phiếu trả. Phiếu cũ không bị ảnh hưởng.</p>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                            <button class="btn btn-primary">Lưu</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('catalog.device-types.toggle', $type) }}" class="modal-section flex flex-wrap items-center justify-between gap-2"
                          @if ($type->is_active) data-confirm="Ẩn loại {{ $type->name }}? Sẽ không chọn được khi lập phiếu, phiếu cũ vẫn giữ." @endif>
                        @csrf @method('PATCH')
                        <div>
                            <h3>{{ $type->is_active ? 'Ẩn loại này' : 'Hiện lại' }}</h3>
                            <p class="text-xs text-muted">{{ $type->is_active ? 'Không chọn được khi lập phiếu nữa.' : 'Đang ẩn, không chọn được khi lập phiếu.' }}</p>
                        </div>
                        <button @class(['btn', 'btn-danger' => $type->is_active, 'btn-secondary' => ! $type->is_active])>{{ $type->is_active ? 'Ẩn' : 'Hiện lại' }}</button>
                    </form>
                @else
                    <div>
                        <h3 class="label">Không bảo hành mặc định</h3>
                        <ul class="list-disc space-y-1 pl-5 text-sm">
                            @forelse (text_lines($type->warranty_exclusions) as $line)
                                <li>{{ $line }}</li>
                            @empty
                                <li class="list-none text-muted">Chưa cài. Admin cài ở đây.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn btn-secondary" data-dialog-close>Đóng</button></div>
                @endcan
            </div>
        </dialog>
    @endforeach
</x-layouts.app>
