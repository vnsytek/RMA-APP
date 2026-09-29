@php
    $device = $summary->device;
    $model = $device->productModel;
@endphp

<x-layouts.app :title="$model->shortName().' '.$device->serial_number" :subtitle="$model->deviceType->name.($model->name ? ' · '.$model->name : '')">
    <x-slot:actions>
        @if ($summary->replacements->isEmpty())
            <a href="{{ route('tickets.create', array_filter(['serial' => $device->serial_number, 'customer' => $summary->customer?->id])) }}" class="btn btn-primary">+ Lập phiếu mới cho máy này</a>
        @endif
        <a href="{{ route('devices.index') }}" class="btn btn-secondary">← Danh sách thiết bị</a>
    </x-slot:actions>

    <section class="card p-5">
        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-[11px] tracking-wider text-muted uppercase">Khách hàng gần nhất</dt>
                <dd class="font-medium">
                    @if ($summary->customer)
                        <a href="{{ route('customers.edit', $summary->customer) }}" class="hover:underline">{{ $summary->customer->name }}</a>
                        <div class="font-mono text-xs text-muted">{{ collect([$summary->customer->contact_name, $summary->customer->phone])->filter()->implode(' · ') }}</div>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div><dt class="text-[11px] tracking-wider text-muted uppercase">Số lần gửi</dt><dd class="font-medium">{{ $summary->tickets->count() }}</dd></div>
            <div>
                <dt class="text-[11px] tracking-wider text-muted uppercase">Đang xử lý</dt>
                <dd class="font-medium">
                    @if ($summary->openTicket)
                        <a href="{{ route('tickets.show', $summary->openTicket) }}" class="ticket-no">{{ $summary->openTicket->ticket_no }}</a> <x-status-pill :status="$summary->openTicket->status" />
                    @else
                        Không
                    @endif
                </dd>
            </div>
            <div><dt class="text-[11px] tracking-wider text-muted uppercase">Bảo hành sau sửa chữa</dt><dd><x-repair-warranty :warranty="$summary->repairWarranty" /></dd></div>
        </dl>

        @if ($summary->replacedFrom)
            <p class="mt-4 text-sm">Máy này là máy hãng đổi, thay cho serial <a href="{{ route('devices.show', $summary->replacedFrom) }}" class="font-mono text-brand underline">{{ $summary->replacedFrom->serial_number }}</a>. Lịch sử bên dưới gồm cả các phiếu của máy cũ.</p>
        @endif
        @if ($summary->replacements->isNotEmpty())
            <p class="mt-4 text-sm text-amber-800">Máy này đã được hãng đổi sang
                @foreach ($summary->replacements as $replacement)
                    <a href="{{ route('devices.show', $replacement) }}" class="font-mono underline">{{ $replacement->serial_number }}</a> ({{ $replacement->productModel->shortName() }})@if (! $loop->last), @endif
                @endforeach
                . Khách không còn giữ máy này.
            </p>
        @endif
        @if ($summary->customerIds->count() > 1)
            <p class="mt-2 text-sm text-muted">Máy từng được {{ $summary->customerIds->count() }} khách gửi: {{ $summary->tickets->pluck('customer')->unique('id')->pluck('name')->implode(', ') }}.</p>
        @endif
    </section>

    <section class="card mt-5 overflow-x-auto">
        <h2 class="px-5 pt-4 pb-2 font-semibold">Lịch sử phiếu của máy</h2>
        <table class="table">
            <thead><tr><th>Số phiếu</th><th>Hình thức</th><th>Khách hàng</th><th>Ngày nhận</th><th>Ngày trả</th><th>Kết quả</th><th>BH sau sửa</th><th>Trạng thái</th></tr></thead>
            <tbody>
                @forelse ($summary->tickets as $ticket)
                    <tr data-href="{{ route('tickets.show', $ticket) }}">
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="ticket-no">{{ $ticket->ticket_no }}</a></td>
                        <td><x-kind-tag :kind="$ticket->kind()" /><div class="text-xs text-muted">{{ $ticket->warranty_status->label() }}</div></td>
                        <td>{{ $ticket->customer->name }}</td>
                        <td>{{ vn_date($ticket->received_date) }}</td>
                        <td>{{ vn_date($ticket->returned_date, '—') }}</td>
                        <td>
                            {{ $ticket->result?->label() ?? '—' }}
                            @if ($ticket->returnedDevice && $ticket->device_id === $device->id)
                                <div class="text-xs text-muted">→ đổi sang <span class="font-mono">{{ $ticket->returnedDevice->serial_number }}</span></div>
                            @endif
                        </td>
                        <td>
                            @if ($ticket->warrantyItems->isNotEmpty())
                                @foreach ($ticket->warrantyItems as $item)
                                    <div class="flex flex-wrap items-center gap-1 text-xs">
                                        <span>{{ $item->description }} · {{ $item->months }} th</span>
                                        <x-pill :tone="$item->level()->tone()">{{ $item->statusLabel() }}</x-pill>
                                    </div>
                                @endforeach
                            @elseif ($ticket->hasRepairWarranty())
                                {{ $ticket->repair_warranty_months }} tháng{{ $ticket->returned_date ? ' · đến '.vn_date($ticket->repairWarrantyEndsOn()) : '' }}
                            @elseif ($ticket->result === \App\Enums\TicketResult::RepairWarranty)
                                <span class="text-xs">BH theo phiếu {{ $summary->tickets->firstWhere('id', $ticket->claim_ticket_id)?->ticket_no }}</span>
                            @elseif ($ticket->result?->carriesRepairWarranty())
                                Không bảo hành
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><x-status-pill :status="$ticket->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Máy chưa từng gửi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
