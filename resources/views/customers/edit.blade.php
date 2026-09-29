<x-layouts.app :title="$customer->name" subtitle="Thông tin khách hàng và các phiếu đã gửi">
    <x-slot:actions>
        <a href="{{ route('devices.index', ['customer' => $customer->id]) }}" class="btn btn-secondary">Thiết bị của khách</a>
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">← Danh sách khách</a>
    </x-slot:actions>

    <form method="POST" action="{{ route('customers.update', $customer) }}" class="card grid gap-4 p-5 sm:grid-cols-2">
        @csrf @method('PUT')
        <div><label class="label" for="name">Tên khách / công ty *</label><input class="input" name="name" id="name" required maxlength="255" value="{{ old('name', $customer->name) }}"><x-field-error name="name" bag="customer" /></div>
        <div><label class="label" for="contact_name">Người liên hệ</label><input class="input" name="contact_name" id="contact_name" maxlength="255" value="{{ old('contact_name', $customer->contact_name) }}"></div>
        <div><label class="label" for="phone">Số điện thoại *</label><input class="input" name="phone" id="phone" required maxlength="20" value="{{ old('phone', $customer->phone) }}"><x-field-error name="phone" bag="customer" /></div>
        <div><label class="label" for="address">Địa chỉ</label><input class="input" name="address" id="address" maxlength="500" value="{{ old('address', $customer->address) }}"></div>
        <div class="sm:col-span-2"><label class="label" for="note">Ghi chú</label><textarea class="input min-h-20" name="note" id="note" maxlength="2000">{{ old('note', $customer->note) }}</textarea></div>
        <div class="flex justify-end sm:col-span-2"><button class="btn btn-primary">Lưu</button></div>
    </form>

    <section class="card mt-5 overflow-x-auto">
        <h2 class="px-5 pt-4 pb-2 font-semibold">Các phiếu của khách ({{ $tickets->count() }})</h2>
        <table class="table">
            <thead><tr><th>Số phiếu</th><th>Hình thức</th><th>Thiết bị</th><th>Serial</th><th>Ngày nhận</th><th>Kết quả</th><th>Trạng thái</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr data-href="{{ route('tickets.show', $ticket) }}">
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="ticket-no">{{ $ticket->ticket_no }}</a></td>
                        <td><x-kind-tag :kind="$ticket->kind()" /></td>
                        <td>{{ $ticket->device->displayName() }}</td>
                        <td class="font-mono text-xs">{{ $ticket->device->serial_number }}</td>
                        <td>{{ vn_date($ticket->received_date) }}</td>
                        <td>{{ $ticket->result?->label() ?? '—' }}</td>
                        <td><x-status-pill :status="$ticket->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Khách chưa gửi phiếu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
