<x-layouts.app title="Khách hàng" subtitle="Người gửi máy, số phiếu và thiết bị của từng khách">
    <form method="GET" class="mb-3 flex gap-2">
        <input type="search" name="q" value="{{ $search }}" class="input" placeholder="Tìm tên khách, người liên hệ, SĐT…" aria-label="Tìm khách hàng">
        <button class="btn btn-secondary">Tìm</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Tên khách / công ty</th><th>Người liên hệ</th><th>SĐT</th><th>Địa chỉ</th><th class="text-right">Phiếu</th><th class="text-right">Đang xử lý</th><th class="text-right">Thiết bị</th><th class="text-right">Còn BH sau sửa</th><th></th></tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td><a href="{{ route('customers.edit', $customer) }}" class="font-medium hover:underline">{{ $customer->name }}</a></td>
                        <td>{{ $customer->contact_name ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $customer->phone }}</td>
                        <td>{{ $customer->address ?? '—' }}</td>
                        <td class="text-right">{{ $customer->tickets_count }}</td>
                        <td class="text-right">{{ $customer->open_tickets_count ?: '—' }}</td>
                        <td class="text-right">{{ $deviceCounts[$customer->id] ?? 0 }}</td>
                        <td class="text-right">{{ ($warrantyCounts[$customer->id] ?? 0) ?: '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('devices.index', ['customer' => $customer->id]) }}" class="btn btn-secondary btn-sm">Xem thiết bị</a>
                            <a href="{{ route('tickets.index', ['q' => $customer->phone]) }}" class="btn btn-secondary btn-sm">Xem phiếu</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-muted">Chưa có khách hàng.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}

    <section class="card mt-5 p-5">
        <h2 class="mb-3 font-semibold">Thêm khách hàng</h2>
        <form method="POST" action="{{ route('customers.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_160px_1fr_auto] lg:items-end">
            @csrf
            <div><label class="label" for="name">Tên khách / công ty *</label><input class="input" name="name" id="name" required maxlength="255" value="{{ old('name') }}"></div>
            <div><label class="label" for="contact_name">Người liên hệ</label><input class="input" name="contact_name" id="contact_name" maxlength="255" value="{{ old('contact_name') }}"></div>
            <div><label class="label" for="phone">Số điện thoại *</label><input class="input" name="phone" id="phone" required maxlength="20" value="{{ old('phone') }}"></div>
            <div><label class="label" for="address">Địa chỉ</label><input class="input" name="address" id="address" maxlength="500" value="{{ old('address') }}"></div>
            <button class="btn btn-primary">Thêm</button>
        </form>
        @foreach (['name', 'phone'] as $field)
            <x-field-error :name="$field" bag="customer" />
        @endforeach
    </section>
</x-layouts.app>
