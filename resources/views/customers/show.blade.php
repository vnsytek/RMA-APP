<x-layouts.app :title="$customer->name" :subtitle="$customer->tax_code ? 'MST '.$customer->tax_code : 'Khách hàng'">
    <x-slot:actions>
        <a href="{{ route('tickets.create', ['customer' => $customer->id]) }}" class="btn btn-primary">+ Lập phiếu cho khách</a>
        <button type="button" class="btn btn-secondary" data-dialog-open="customer-edit">Sửa</button>
        @can('admin')
            @if ($ticketCount === 0)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="Xoá khách hàng {{ $customer->name }}? Không khôi phục được.">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger">Xoá</button>
                </form>
            @else
                <button type="button" class="btn btn-secondary" disabled title="Khách đã có {{ $ticketCount }} phiếu nên không xoá được, để giữ lịch sử.">Xoá</button>
            @endif
        @endcan
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">← Danh sách</a>
    </x-slot:actions>

    <section class="card p-5">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2"><dt class="text-[11px] tracking-wider text-muted uppercase">Tên khách / công ty</dt><dd class="font-medium">{{ $customer->name }}</dd></div>
            <div><dt class="text-[11px] tracking-wider text-muted uppercase">Mã số thuế</dt><dd class="font-mono font-medium">{{ $customer->tax_code ?? '—' }}</dd></div>
            <div><dt class="text-[11px] tracking-wider text-muted uppercase">Phiếu</dt><dd class="font-medium">{{ $ticketCount }}@if ($openCount) <span class="text-amber-700">· {{ $openCount }} đang xử lý</span>@endif</dd></div>
            <div><dt class="text-[11px] tracking-wider text-muted uppercase">SĐT công ty</dt><dd class="font-mono font-medium">{{ $customer->phone ?? '—' }}</dd></div>
            <div><dt class="text-[11px] tracking-wider text-muted uppercase">Người liên hệ</dt><dd class="font-medium">{{ $contacts->where('is_active', true)->count() }} người</dd></div>
            <div class="sm:col-span-2"><dt class="text-[11px] tracking-wider text-muted uppercase">Địa chỉ</dt><dd class="font-medium">{{ $customer->address ?? '—' }}</dd></div>
            @if ($customer->note)
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[11px] tracking-wider text-muted uppercase">Ghi chú</dt><dd class="whitespace-pre-line">{{ $customer->note }}</dd></div>
            @endif
        </dl>
        <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-3 text-sm">
            <a href="{{ route('devices.index', ['customer' => $customer->id]) }}" class="btn btn-secondary btn-sm">Thiết bị của khách ({{ $deviceCount }})</a>
        </div>
    </section>

    <section class="card mt-5 overflow-x-auto" id="nguoi-lien-he">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-4 pb-2">
            <h2 class="font-semibold">Người liên hệ <span class="text-sm font-normal text-muted">· mỗi phiếu lưu lại người đã gửi máy</span></h2>
            <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="contact-new">+ Thêm người liên hệ</button>
        </div>
        <table class="table">
            <thead><tr><th>Tên</th><th>Số điện thoại</th><th class="text-right">Số phiếu</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr @class(['opacity-60' => ! $contact->is_active])>
                        <td class="font-medium">{{ $contact->name }}</td>
                        <td class="font-mono text-xs">{{ $contact->phone ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $contact->tickets_count ?: '—' }}</td>
                        <td><x-pill :tone="$contact->is_active ? 'green' : 'gray'">{{ $contact->is_active ? 'Đang dùng' : 'Ngừng dùng' }}</x-pill></td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="contact-{{ $contact->id }}">Sửa</button>
                            <form method="POST" action="{{ route('customers.contacts.toggle', [$customer, $contact]) }}" class="inline"
                                  @if ($contact->is_active) data-confirm="Ngừng dùng {{ $contact->name }}? Người này không hiện khi lập phiếu nữa, các phiếu cũ vẫn giữ nguyên." @endif>
                                @csrf @method('PATCH')
                                <button class="btn btn-secondary btn-sm">{{ $contact->is_active ? 'Ngừng dùng' : 'Dùng lại' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Chưa có người liên hệ. Lập phiếu đầu tiên hoặc bấm "+ Thêm người liên hệ".</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card mt-5 overflow-x-auto">
        <h2 class="px-5 pt-4 pb-2 font-semibold">Các phiếu của khách
            @if ($tickets->count() < $ticketCount)
                <span class="text-sm font-normal text-muted">· hiện {{ $tickets->count() }}/{{ $ticketCount }} (chỉ phiếu bạn lập hoặc phụ trách)</span>
            @endif
        </h2>
        <table class="table">
            <thead><tr><th>Số phiếu</th><th>Người liên hệ</th><th>Hình thức</th><th>Thiết bị</th><th>Serial</th><th>Ngày nhận</th><th>Trạng thái</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr data-href="{{ route('tickets.show', $ticket) }}" class="cursor-pointer hover:bg-panel">
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="ticket-no">{{ $ticket->ticket_no }}</a></td>
                        <td>{{ $ticket->contact_name ?? '—' }}<div class="font-mono text-xs text-muted">{{ $ticket->contact_phone }}</div></td>
                        <td><x-kind-tag :kind="$ticket->kind()" /></td>
                        <td>{{ $ticket->device->displayName() }}</td>
                        <td class="font-mono text-xs">{{ $ticket->device->serial_number }}</td>
                        <td>{{ vn_date($ticket->received_date) }}</td>
                        <td><x-status-pill :status="$ticket->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Chưa có phiếu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <dialog id="contact-new" class="modal" aria-labelledby="contact-new-title" @if ($errors->getBag('contact')->any()) data-open @endif>
        <form method="POST" action="{{ route('customers.contacts.store', $customer) }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <h2 id="contact-new-title">Thêm người liên hệ</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <x-contact-fields bag="contact" prefix="contact-new" />
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm</button>
            </div>
        </form>
    </dialog>

    @foreach ($contacts as $contact)
        <dialog id="contact-{{ $contact->id }}" class="modal" aria-labelledby="contact-{{ $contact->id }}-title" @if ($errors->getBag('contact'.$contact->id)->any()) data-open @endif>
            <form method="POST" action="{{ route('customers.contacts.update', [$customer, $contact]) }}" class="modal-body">
                @csrf @method('PUT')
                <div class="modal-head">
                    <h2 id="contact-{{ $contact->id }}-title">Sửa người liên hệ</h2>
                    <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
                </div>
                <x-contact-fields :contact="$contact" :bag="'contact'.$contact->id" :prefix="'contact-'.$contact->id" />
                <p class="text-xs text-muted">Sửa ở đây không làm đổi các phiếu đã lập; phiếu cũ vẫn giữ tên và số lúc gửi máy.</p>
                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                    <button class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </dialog>
    @endforeach

    <dialog id="customer-edit" class="modal" aria-labelledby="customer-edit-title" @if ($errors->getBag('customer')->any()) data-open @endif>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="modal-body">
            @csrf @method('PUT')
            <div class="modal-head">
                <h2 id="customer-edit-title">Sửa khách hàng</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <x-customer-fields :customer="$customer" prefix="edit" />
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </dialog>
</x-layouts.app>
