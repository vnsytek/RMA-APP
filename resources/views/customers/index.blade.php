<x-layouts.app title="Khách hàng" subtitle="Bấm vào một khách để xem chi tiết, sửa hoặc xoá">
    <x-slot:actions>
        <button type="button" class="btn btn-primary" data-dialog-open="customer-new">+ Thêm khách hàng</button>
    </x-slot:actions>

    <form method="GET" class="mb-3 flex gap-2">
        <input type="search" name="q" value="{{ $search }}" class="input" placeholder="Tìm tên khách, MST, tên hoặc SĐT người liên hệ…" aria-label="Tìm khách hàng">
        <button class="btn btn-secondary">Tìm</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table table-fixed">
            <colgroup>
                <col class="w-[34%]"><col class="w-[18%]"><col class="w-[30%]"><col class="w-[9%]"><col class="w-[9%]">
            </colgroup>
            <thead>
                <tr><th>Khách hàng</th><th>Liên hệ</th><th>Địa chỉ</th><th class="text-right">Phiếu</th><th class="text-right">Thiết bị</th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr data-href="{{ route('customers.show', $customer) }}" class="cursor-pointer hover:bg-panel">
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="block truncate font-medium" title="{{ $customer->name }}">{{ $customer->name }}</a>
                            <div class="flex items-center gap-1.5 text-xs text-muted">
                                @if ($customer->tax_code)<span class="font-mono">MST {{ $customer->tax_code }}</span>@endif
                                @if ($customer->note)
                                    <span class="truncate" title="{{ $customer->note }}">{{ $customer->tax_code ? '· ' : '' }}{{ $customer->note }}</span>
                                @endif
                            </div>
                        </td>
                        @php
                            $firstContact = $customer->contacts->first();
                        @endphp
                        <td title="{{ $customer->contacts->map->label()->implode("\n") }}">
                            @if ($firstContact)
                                <div class="truncate">{{ $firstContact->name }}@if ($customer->contacts->count() > 1) <span class="text-xs text-muted">+{{ $customer->contacts->count() - 1 }} người</span>@endif</div>
                                <div class="font-mono text-xs text-muted">{{ $firstContact->phone }}</div>
                            @else
                                <span class="text-muted">—</span>
                                @if ($customer->phone)<div class="font-mono text-xs text-muted">{{ $customer->phone }}</div>@endif
                            @endif
                        </td>
                        <td>
                            <div class="line-clamp-2 text-sm" title="{{ $customer->address }}">{{ $customer->address ?? '—' }}</div>
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $customer->tickets_count ?: '—' }}
                            @if ($customer->open_tickets_count)
                                <div class="text-xs text-amber-700">{{ $customer->open_tickets_count }} đang xử lý</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            {{ ($deviceCounts[$customer->id] ?? 0) ?: '—' }}
                            @if ($warrantyCounts[$customer->id] ?? 0)
                                <div class="text-xs text-emerald-700">{{ $warrantyCounts[$customer->id] }} còn BH</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-muted">{{ $search !== '' ? 'Không tìm thấy khách nào.' : 'Chưa có khách hàng.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}

    <dialog id="customer-new" class="modal" aria-labelledby="customer-new-title" @if ($errors->getBag('customer')->any()) data-open @endif>
        <form method="POST" action="{{ route('customers.store') }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <div>
                    <h2 id="customer-new-title">Thêm khách hàng</h2>
                    <p class="text-xs text-muted">Có MST thì bấm "Tra MST" để tự điền tên và địa chỉ.</p>
                </div>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <x-customer-fields prefix="new" />
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm khách hàng</button>
            </div>
        </form>
    </dialog>
</x-layouts.app>
