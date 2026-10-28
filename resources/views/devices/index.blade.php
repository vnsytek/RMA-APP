@php
    use App\Http\Controllers\DeviceController;

    $active = $filters['filter'] ?? '';
    $keep = fn (array $extra) => array_filter([...array_intersect_key($filters, array_flip(['sort', 'customer', 'q'])), ...$extra], fn ($value) => $value !== null && $value !== '');
@endphp

<x-layouts.app title="Thiết bị" subtitle="Máy đang ở khách nào, lịch sử gửi, và thời hạn bảo hành sau sửa chữa">
    <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Lọc thiết bị">
        <a href="{{ route('devices.index', $keep([])) }}" @class(['chip', 'is-active' => $active === '']) @if ($active === '') aria-current="true" @endif>
            Tất cả thiết bị <b>{{ $counts[''] }}</b>
        </a>
        @foreach (DeviceController::FILTERS as $key => $label)
            <a href="{{ route('devices.index', $keep(['filter' => $key])) }}" @class(['chip', 'is-active' => $active === $key]) @if ($active === $key) aria-current="true" @endif>
                {{ $label }}@if ($key === 'expiring') (≤ {{ config('rma.repair_warranty_expiring_days') }} ngày)@endif <b>{{ $counts[$key] }}</b>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-3 flex flex-wrap gap-2">
        @if ($active) <input type="hidden" name="filter" value="{{ $active }}"> @endif
        <label class="search min-w-64"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Tìm serial, model, hãng, tên khách, SĐT…" aria-label="Tìm thiết bị"></label>
        <select name="customer" class="input max-w-64 sm:w-auto max-sm:max-w-none" aria-label="Lọc theo khách hàng" data-autosubmit>
            <option value="">Mọi khách hàng</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) ($filters['customer'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="input flex-1 sm:w-auto sm:flex-none" aria-label="Sắp xếp" data-autosubmit>
            @foreach (DeviceController::SORTS as $key => $label)
                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'recent') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary">Lọc</button>
    </form>

    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="table table-stack">
            <thead><tr><th>Thiết bị</th><th>Khách hàng</th><th>Phiếu gần nhất</th><th class="text-right">Lần gửi</th><th>Bảo hành sau sửa chữa</th></tr></thead>
            <tbody>
                @forelse ($devices as $item)
                    @php $last = $item->lastTicket(); @endphp
                    <tr data-href="{{ route('devices.show', $item->device) }}">
                        <td>
                            <div>{{ $item->device->productModel->deviceType->name }} <b>{{ $item->device->productModel->shortName() }}</b></div>
                            <a href="{{ route('devices.show', $item->device) }}" class="font-mono text-sm">{{ $item->device->serial_number }}</a>
                            @if ($item->replacedFrom)
                                <div class="text-xs text-muted">Hãng đổi từ <span class="font-mono">{{ $item->replacedFrom->serial_number }}</span></div>
                            @endif
                            @if ($item->replacements->isNotEmpty())
                                <div class="text-xs text-amber-800">Đã đổi sang <span class="font-mono">{{ $item->replacements->pluck('serial_number')->implode(', ') }}</span> · khách không còn giữ máy này</div>
                            @endif
                        </td>
                        <td data-label="Khách hàng">
                            @if ($item->customer)
                                {{ $item->customer->name }}<div class="text-xs text-muted">{{ $item->lastTicket()?->contactLabel() }}</div>
                                @if ($item->customerIds->count() > 1)
                                    <div class="text-xs text-muted">+{{ $item->customerIds->count() - 1 }} khách khác từng gửi</div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-label="Phiếu gần nhất">
                            @if ($last)
                                <x-ticket-link :ticket="$last" /> <x-kind-tag :kind="$last->kind()" />
                                <div class="mt-1 flex flex-wrap items-center gap-2"><x-status-pill :status="$last->status" /><span class="text-xs text-muted">{{ vn_date($last->received_date) }}</span></div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-label="Lần gửi" class="text-right">
                            @if ($item->tickets->count() > 1)
                                <x-pill tone="amber">{{ $item->tickets->count() }} lần</x-pill>
                            @else
                                {{ $item->tickets->count() }}
                            @endif
                        </td>
                        <td data-label="BH sau sửa"><x-repair-warranty :warranty="$item->repairWarranty" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><x-icon name="monitor" /><strong>Không có thiết bị nào khớp</strong><p>Thiết bị được thêm tự động khi lập phiếu. Thử bỏ bớt bộ lọc.</p><a href="{{ route('devices.index') }}" class="btn btn-secondary btn-sm mt-1">Xem tất cả thiết bị</a></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>

    {{ $devices->links() }}
    <p class="mt-3 text-xs text-muted">Chỉ theo dõi bảo hành sau sửa chữa của Sang Y. Bảo hành hãng do nhân viên tự chọn khi lập phiếu. Khách hàng của máy là khách ở phiếu gần nhất.</p>
</x-layouts.app>
