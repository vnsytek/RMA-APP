<x-layouts.app title="Khách hàng" subtitle="Bấm vào một khách để xem chi tiết, sửa hoặc xoá">
    <x-slot:actions>
        <button type="button" class="btn btn-primary" data-dialog-open="customer-new"><x-icon name="plus" />Thêm khách hàng</button>
    </x-slot:actions>

    <form method="GET" class="mb-4 flex gap-2" role="search">
        <label class="search"><x-icon name="search" /><input type="search" name="q" value="{{ $search }}" class="input" placeholder="Tìm mã khách, tên khách, tên viết tắt, MST, tên hoặc SĐT người liên hệ…" aria-label="Tìm khách hàng"></label>
        <button class="btn btn-secondary">Tìm</button>
    </form>

    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="table table-stack sm:table-fixed">
            <colgroup>
                <col class="w-[9%]"><col class="w-[13%]"><col class="w-[30%]"><col class="w-[15%]"><col class="w-[19%]"><col class="w-[7%]"><col class="w-[7%]">
            </colgroup>
            <thead>
                <tr><th>Mã khách</th><th>Tên viết tắt</th><th>Khách hàng</th><th>Liên hệ</th><th>Địa chỉ</th><th class="text-right">Phiếu</th><th class="text-right">Thiết bị</th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr data-href="{{ route('customers.show', $customer) }}">
                        <td data-label="Mã khách" class="truncate font-mono font-medium" title="{{ $customer->code }}">{{ $customer->code ?? '—' }}</td>
                        <td data-label="Tên viết tắt" class="stack-hide truncate" title="{{ $customer->short_name }}">{{ $customer->short_name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="block truncate font-medium" title="{{ $customer->name }}">{{ $customer->name }}</a>
                            @if ($customer->tax_code)<div class="font-mono text-xs text-muted">MST {{ $customer->tax_code }}</div>@endif
                        </td>
                        @php
                            $firstContact = $customer->contacts->first();
                        @endphp
                        <td data-label="Liên hệ" title="{{ $customer->contacts->map->label()->implode("\n") }}">
                            @if ($firstContact)
                                <div class="truncate">{{ $firstContact->name }}@if ($customer->contacts->count() > 1) <span class="text-xs text-muted">+{{ $customer->contacts->count() - 1 }} người</span>@endif</div>
                                <div class="font-mono text-xs text-muted">{{ $firstContact->phone }}</div>
                            @else
                                <span class="text-muted">—</span>
                                @if ($customer->phone)<div class="font-mono text-xs text-muted">{{ $customer->phone }}</div>@endif
                            @endif
                        </td>
                        <td data-label="Địa chỉ" class="stack-hide">
                            <div class="line-clamp-2 text-sm" title="{{ $customer->address }}">{{ $customer->address ?? '—' }}</div>
                        </td>
                        <td data-label="Phiếu" class="text-right tabular-nums">
                            {{ $customer->tickets_count ?: '—' }}
                            @if ($customer->open_tickets_count)
                                <div class="text-xs text-amber-700">{{ $customer->open_tickets_count }} đang xử lý</div>
                            @endif
                        </td>
                        <td data-label="Thiết bị" class="text-right tabular-nums">
                            {{ ($deviceCounts[$customer->id] ?? 0) ?: '—' }}
                            @if ($warrantyCounts[$customer->id] ?? 0)
                                <div class="text-xs text-emerald-700">{{ $warrantyCounts[$customer->id] }} còn BH</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><x-icon name="building" />@if ($search !== '')<strong>Không tìm thấy khách nào</strong><p>Thử tìm theo MST, số điện thoại hoặc tên viết tắt.</p>@else<strong>Chưa có khách hàng</strong><p>Khách được thêm khi lập phiếu, hoặc thêm trước ở đây.</p><button type="button" class="btn btn-primary btn-sm mt-1" data-dialog-open="customer-new"><x-icon name="plus" />Thêm khách hàng</button>@endif</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>

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
