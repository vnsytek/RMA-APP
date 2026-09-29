<x-layouts.app title="Loại · Hãng · Model" subtitle="Danh mục thiết bị. Có thể thêm nhanh ngay trong form lập phiếu.">
    <div class="grid items-start gap-5 xl:grid-cols-2">
        <section class="card p-5">
            <h2 class="mb-2 font-semibold">Loại thiết bị</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Mã</th><th>Tên</th><th class="text-right">Số model</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($deviceTypes as $type)
                            <tr @class(['opacity-60' => ! $type->is_active])>
                                <td class="font-mono text-xs">{{ $type->code }}</td>
                                <td>
                                    {{ $type->name }} @unless ($type->is_active)<x-pill>đã ẩn</x-pill>@endunless
                                    <details class="mt-1" @if ((string) old('device_type_id') === (string) $type->id) open @endif>
                                        <summary class="cursor-pointer text-xs text-brand">Không bảo hành mặc định ({{ count(text_lines($type->warranty_exclusions)) }} mục)</summary>
                                        @can('admin')
                                            <form method="POST" action="{{ route('catalog.device-types.update', $type) }}" class="mt-2 space-y-2">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="device_type_id" value="{{ $type->id }}">
                                                <textarea class="input min-h-24 text-xs" name="warranty_exclusions" maxlength="2000" aria-label="Không bảo hành mặc định của {{ $type->name }}"
                                                          placeholder="Mỗi dòng một mục, VD: Vỡ màn, vào nước">{{ (string) old('device_type_id') === (string) $type->id ? old('warranty_exclusions') : $type->warranty_exclusions }}</textarea>
                                                <p class="text-xs text-muted">Tự điền vào bước "Sửa xong" và in lên phiếu trả. Phiếu cũ không bị ảnh hưởng.</p>
                                                <button class="btn btn-secondary btn-sm">Lưu</button>
                                            </form>
                                        @else
                                            <ul class="mt-1 list-disc pl-5 text-xs text-muted">
                                                @forelse (text_lines($type->warranty_exclusions) as $line)
                                                    <li>{{ $line }}</li>
                                                @empty
                                                    <li>Chưa có. Admin cài ở đây.</li>
                                                @endforelse
                                            </ul>
                                        @endcan
                                    </details>
                                </td>
                                <td class="text-right">{{ $type->product_models_count }}</td>
                                <td class="text-right">
                                    @can('admin')
                                        <form method="POST" action="{{ route('catalog.device-types.toggle', $type) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $type->is_active ? 'Ẩn' : 'Hiện lại' }}</button></form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('catalog.device-types.store') }}" class="mt-3 flex gap-2">
                @csrf
                <input class="input" name="name" placeholder="Tên loại mới, VD: Máy chiếu" required maxlength="100" aria-label="Tên loại mới">
                <button class="btn btn-secondary">Thêm</button>
            </form>
            <x-field-error name="name" bag="deviceType" />
        </section>

        <section class="card p-5">
            <h2 class="mb-2 font-semibold">Thương hiệu</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tên</th><th class="text-right">Số model</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($brands as $brand)
                            <tr @class(['opacity-60' => ! $brand->is_active])>
                                <td>{{ $brand->name }} @unless ($brand->is_active)<x-pill>đã ẩn</x-pill>@endunless</td>
                                <td class="text-right">{{ $brand->product_models_count }}</td>
                                <td class="text-right">
                                    @can('admin')
                                        <form method="POST" action="{{ route('catalog.brands.toggle', $brand) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $brand->is_active ? 'Ẩn' : 'Hiện lại' }}</button></form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('catalog.brands.store') }}" class="mt-3 flex gap-2">
                @csrf
                <input class="input" name="name" placeholder="Tên hãng mới, VD: LENOVO" required maxlength="100" aria-label="Tên hãng mới">
                <button class="btn btn-secondary">Thêm</button>
            </form>
            <x-field-error name="name" bag="brand" />
        </section>
    </div>

    <section class="card mt-5 p-5">
        <h2 class="mb-2 font-semibold">Model sản phẩm</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Mã model</th><th>Hãng</th><th>Loại</th><th>Tên</th><th class="text-right">Số máy</th><th></th></tr></thead>
                <tbody>
                    @foreach ($productModels as $model)
                        <tr @class(['opacity-60' => ! $model->is_active])>
                            <td class="font-mono text-xs">{{ $model->code }}</td>
                            <td>{{ $model->brand->name }}</td>
                            <td>{{ $model->deviceType->name }}</td>
                            <td>{{ $model->name ?? '—' }} @unless ($model->is_active)<x-pill>đã ẩn</x-pill>@endunless</td>
                            <td class="text-right">{{ $model->devices_count }}</td>
                            <td class="text-right">
                                @can('admin')
                                    <form method="POST" action="{{ route('catalog.product-models.toggle', $model) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $model->is_active ? 'Ẩn' : 'Hiện lại' }}</button></form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('catalog.product-models.store') }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_1fr_1fr_auto]">
            @csrf
            <select class="input" name="device_type_id" required aria-label="Loại thiết bị">
                <option value="">Loại *</option>
                @foreach ($deviceTypes->where('is_active', true) as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            <select class="input" name="brand_id" required aria-label="Hãng">
                <option value="">Hãng *</option>
                @foreach ($brands->where('is_active', true) as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            <input class="input" name="code" placeholder="Mã model *" required maxlength="100" aria-label="Mã model">
            <input class="input" name="name" placeholder="Tên (tuỳ chọn)" maxlength="255" aria-label="Tên model">
            <button class="btn btn-secondary">Thêm</button>
        </form>
        @foreach (['device_type_id', 'brand_id', 'code'] as $field)
            <x-field-error :name="$field" bag="productModel" />
        @endforeach
        <p class="mt-3 text-xs text-muted">Danh mục không xoá, chỉ ẩn (quyền Admin) để phiếu cũ vẫn hiển thị đúng.</p>
    </section>
</x-layouts.app>
