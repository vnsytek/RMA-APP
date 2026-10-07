<x-layouts.app title="Kho" subtitle="Tồn kho linh kiện, nhập/xuất khi sửa chữa. Bản demo: số tồn sửa trực tiếp, chưa phải sổ nhật ký đầy đủ.">
    <section class="card p-5">
        <h2 class="mb-2 font-semibold">Tồn kho</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Mã</th><th>Tên linh kiện</th><th>Đơn vị</th><th class="text-right">Tồn</th><th class="text-right">Tối thiểu</th><th class="text-right">BH mặc định</th><th>Nhập kho</th><th>Xuất kho</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($parts as $part)
                        <tr @class(['opacity-60' => ! $part->is_active])>
                            <td class="font-mono text-xs">{{ $part->code }}</td>
                            <td>{{ $part->name }} @unless ($part->is_active)<x-pill>đã ẩn</x-pill>@endunless</td>
                            <td>{{ $part->unit }}</td>
                            <td class="text-right">
                                <span class="font-semibold">{{ $part->quantity_on_hand }}</span>
                                @if ($part->isLowStock())
                                    <x-pill tone="red">sắp hết</x-pill>
                                @endif
                            </td>
                            <td class="text-right text-muted">{{ $part->min_quantity ?? '—' }}</td>
                            <td class="text-right text-muted">{{ $part->warranty_months ? $part->warranty_months.' tháng' : '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('parts.stock-in', $part) }}" class="flex gap-1">
                                    @csrf
                                    <input class="input w-20" type="number" name="quantity" min="1" placeholder="SL" required aria-label="Số lượng nhập {{ $part->name }}">
                                    <button class="btn btn-secondary btn-sm">Nhập</button>
                                </form>
                                <x-field-error name="quantity" bag="stockIn" />
                            </td>
                            <td>
                                <form method="POST" action="{{ route('parts.stock-out', $part) }}" class="flex gap-1">
                                    @csrf
                                    <input class="input w-20" type="number" name="quantity" min="1" max="{{ $part->quantity_on_hand }}" placeholder="SL" required aria-label="Số lượng xuất {{ $part->name }}">
                                    <button class="btn btn-secondary btn-sm" @disabled($part->quantity_on_hand === 0)>Xuất</button>
                                </form>
                                <x-field-error name="quantity" bag="stockOut" />
                            </td>
                            <td class="text-right">
                                @can('admin')
                                    <form method="POST" action="{{ route('parts.toggle', $part) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $part->is_active ? 'Ẩn' : 'Hiện lại' }}</button></form>
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs text-brand">Điều chỉnh kiểm kê</summary>
                                        <form method="POST" action="{{ route('parts.adjust', $part) }}" class="mt-2 space-y-1.5 text-left">
                                            @csrf
                                            <input class="input" type="number" name="quantity_on_hand" min="0" value="{{ $part->quantity_on_hand }}" required aria-label="Số lượng sau kiểm kê {{ $part->name }}">
                                            <input class="input" name="note" placeholder="Lý do (VD: kiểm kê thực tế thiếu 1)" required maxlength="255" aria-label="Lý do điều chỉnh {{ $part->name }}">
                                            <button class="btn btn-secondary btn-sm w-full">Lưu điều chỉnh</button>
                                        </form>
                                        <x-field-error name="quantity_on_hand" bag="adjust" />
                                        <x-field-error name="note" bag="adjust" />
                                    </details>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-muted">Chưa có linh kiện nào trong kho.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('parts.store') }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_2fr_1fr_1fr_1fr_auto]">
            @csrf
            <input class="input" name="code" placeholder="Mã linh kiện *" required maxlength="50" aria-label="Mã linh kiện">
            <input class="input" name="name" placeholder="Tên linh kiện *" required maxlength="255" aria-label="Tên linh kiện">
            <input class="input" name="unit" placeholder="Đơn vị (mặc định: cái)" maxlength="20" aria-label="Đơn vị tính">
            <input class="input" type="number" name="min_quantity" min="0" placeholder="Tồn tối thiểu" aria-label="Mức tồn tối thiểu">
            <input class="input" type="number" name="warranty_months" min="1" max="60" placeholder="BH mặc định (tháng)" aria-label="Số tháng bảo hành mặc định">
            <button class="btn btn-secondary">Thêm</button>
        </form>
        @foreach (['code', 'name', 'unit', 'min_quantity', 'warranty_months'] as $field)
            <x-field-error :name="$field" bag="part" />
        @endforeach
        <p class="mt-3 text-xs text-muted">Danh mục không xoá, chỉ ẩn (quyền Admin) để lịch sử nhập/xuất cũ vẫn hiển thị đúng. "BH mặc định" chỉ là số tháng gợi ý sẵn khi dùng linh kiện này để sửa chữa, có thể sửa lại cho từng phiếu.</p>
    </section>

    <section class="card mt-5 p-5">
        <h2 class="mb-2 font-semibold">Lịch sử nhập/xuất gần đây</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Thời gian</th><th>Linh kiện</th><th>Loại</th><th class="text-right">Số lượng</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="whitespace-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $movement->part->name }}</td>
                            <td><x-pill :tone="$movement->type->tone()">{{ $movement->type->label() }}</x-pill></td>
                            <td class="text-right">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                            <td>{{ $movement->user->name }}</td>
                            <td>{{ $movement->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-muted">Chưa có lần nhập/xuất nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
