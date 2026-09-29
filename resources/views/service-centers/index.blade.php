<x-layouts.app title="Hãng / TTBH" subtitle="Nơi bảo hành: hãng đến tận nơi hoặc trung tâm nhận máy">
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Tên</th><th>Nhận bảo hành</th><th>SĐT</th><th>Địa chỉ</th><th class="text-right">Lần gửi / hẹn</th><th class="text-right">Đang giữ</th><th></th></tr></thead>
            <tbody>
                @foreach ($serviceCenters as $center)
                    <tr @class(['opacity-60' => ! $center->is_active])>
                        <td>
                            <details>
                                <summary class="cursor-pointer font-medium">{{ $center->name }} @unless ($center->is_active)<x-pill>đã ẩn</x-pill>@endunless</summary>
                                <form method="POST" action="{{ route('service-centers.update', $center) }}" class="mt-2 grid w-96 max-w-full gap-2">
                                    @csrf @method('PUT')
                                    <input class="input" name="name" value="{{ $center->name }}" required maxlength="255" aria-label="Tên">
                                    <input class="input" name="brands" value="{{ $center->brands }}" placeholder="Nhận hãng nào" maxlength="255" aria-label="Nhận hãng nào">
                                    <input class="input" name="phone" value="{{ $center->phone }}" placeholder="SĐT" maxlength="30" aria-label="SĐT">
                                    <input class="input" name="address" value="{{ $center->address }}" placeholder="Địa chỉ" maxlength="500" aria-label="Địa chỉ">
                                    <button class="btn btn-secondary btn-sm justify-self-start">Lưu</button>
                                </form>
                            </details>
                        </td>
                        <td>{{ $center->brands ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $center->phone ?? '—' }}</td>
                        <td>{{ $center->address ?? '—' }}</td>
                        <td class="text-right">{{ $center->shipments_count }}</td>
                        <td class="text-right">{{ $center->pending_count ?: '—' }}</td>
                        <td class="text-right">
                            @can('admin')
                                <form method="POST" action="{{ route('service-centers.toggle', $center) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-secondary btn-sm">{{ $center->is_active ? 'Ẩn' : 'Hiện lại' }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="card mt-5 p-5">
        <h2 class="mb-3 font-semibold">Thêm hãng / TTBH</h2>
        <form method="POST" action="{{ route('service-centers.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_160px_1fr_auto] lg:items-end">
            @csrf
            <div><label class="label" for="sc-name">Tên *</label><input class="input" name="name" id="sc-name" required maxlength="255"></div>
            <div><label class="label" for="sc-brands">Nhận hãng nào</label><input class="input" name="brands" id="sc-brands" placeholder="VD: DELL, HP" maxlength="255"></div>
            <div><label class="label" for="sc-phone">SĐT</label><input class="input" name="phone" id="sc-phone" maxlength="30"></div>
            <div><label class="label" for="sc-address">Địa chỉ</label><input class="input" name="address" id="sc-address" maxlength="500"></div>
            <button class="btn btn-primary">Thêm</button>
        </form>
        <x-field-error name="name" bag="serviceCenter" />
        <p class="mt-3 text-xs text-muted">Không xoá, chỉ ẩn (quyền Admin) để phiếu cũ vẫn hiển thị đúng.</p>
    </section>
</x-layouts.app>
