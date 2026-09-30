@php
    use App\Enums\TicketKind;
    use App\Enums\TicketStatus;
@endphp

<x-layouts.app title="Phiếu RMA" subtitle="Lập phiếu, xử lý theo quy trình, in phiếu nhận / phiếu trả">
    <x-slot:actions>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary">+ Lập phiếu mới</a>
    </x-slot:actions>

    <div class="segmented mb-3">
        <a href="{{ route('tickets.index', array_filter(['q' => $search, 'technician' => $technician])) }}" @class(['is-active' => $kind === null, 'hover:bg-panel' => $kind !== null])>
            Tất cả <span class="opacity-70">{{ $kindCounts->sum() }}</span>
        </a>
        @foreach (TicketKind::cases() as $type)
            <a href="{{ route('tickets.index', array_filter(['kind' => $type->value, 'q' => $search, 'technician' => $technician])) }}" @class(['is-active' => $kind === $type, 'hover:bg-panel' => $kind !== $type])>
                {{ $type->label() }} <span class="opacity-70">{{ $kindCounts[$type->value] }}</span>
            </a>
        @endforeach
    </div>

    <div class="mb-3 flex flex-wrap gap-2">
        <a href="{{ route('tickets.index', array_filter(['kind' => $kind?->value, 'q' => $search, 'technician' => $technician])) }}" @class(['stat-chip', 'is-active' => $status === null])>
            <b class="block text-lg tabular-nums">{{ $statusCounts->sum() }}</b><span class="text-xs text-muted">Mọi trạng thái</span>
        </a>
        @foreach (TicketStatus::cases() as $option)
            @continue(! isset($statusCounts[$option->value]))
            <a href="{{ route('tickets.index', array_filter(['kind' => $kind?->value, 'status' => $option->value, 'q' => $search, 'technician' => $technician])) }}" @class(['stat-chip', 'is-active' => $status === $option])>
                <b class="block text-lg tabular-nums">{{ $statusCounts[$option->value] }}</b><span class="text-xs text-muted">{{ $option->label() }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-3 flex gap-2">
        @if ($kind) <input type="hidden" name="kind" value="{{ $kind->value }}"> @endif
        @if ($status) <input type="hidden" name="status" value="{{ $status->value }}"> @endif
        @can('admin')
            <select name="technician" class="input w-auto" aria-label="Lọc theo nhân viên phụ trách" data-autosubmit>
                <option value="">Mọi nhân viên</option>
                @foreach ($technicians as $person)
                    <option value="{{ $person->id }}" @selected((string) $technician === (string) $person->id)>{{ $person->name }}@unless ($person->is_active) (đã khoá)@endunless</option>
                @endforeach
            </select>
        @endcan
        <input type="search" name="q" value="{{ $search }}" class="input" placeholder="Tìm số phiếu, khách, SĐT, serial, model, mã hồ sơ hãng, số V223/V233…" aria-label="Tìm phiếu">
        <button class="btn btn-secondary">Tìm</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>Số phiếu</th><th>Hình thức</th><th>Khách hàng</th><th>Thiết bị</th><th>Serial</th><th>Phụ trách</th><th>Ngày nhận</th><th>Kết quả / Phí</th><th>Trạng thái</th></tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr data-href="{{ route('tickets.show', $ticket) }}">
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="ticket-no">{{ $ticket->ticket_no }}</a></td>
                        <td>
                            <x-kind-tag :kind="$ticket->kind()" />
                            <div class="text-xs text-muted">{{ $ticket->warranty_status->label() }}@if ($ticket->wasConverted()) · từ {{ $ticket->originalKind()->shortLabel() }}@endif</div>
                        </td>
                        <td>{{ $ticket->customer->name }}<div class="text-xs text-muted">{{ $ticket->contactLabel() }}</div></td>
                        <td>{{ $ticket->device->productModel->deviceType->name }}<div class="text-xs text-muted">{{ $ticket->device->productModel->shortName() }}</div></td>
                        <td class="font-mono text-xs">
                            {{ $ticket->device->serial_number }}
                            @if ($ticket->returnedDevice)
                                <div class="text-muted">→ {{ $ticket->returnedDevice->serial_number }}@if ($ticket->isSwappedToOtherProduct()) <span class="rounded border border-line px-1">{{ $ticket->returnedDevice->productModel->shortName() }}</span>@endif</div>
                            @endif
                        </td>
                        <td>{{ $ticket->technician?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ vn_date($ticket->received_date) }}</td>
                        <td class="min-w-40">
                            @if ($ticket->is_chargeable)
                                <span class="rounded border border-orange-500 px-1.5 text-[11px] font-semibold text-orange-800">Có phí</span> {{ money_vnd($ticket->charge_amount) }}
                            @elseif ($ticket->status === TicketStatus::Quoted)
                                <span class="rounded border border-orange-500 px-1.5 text-[11px] font-semibold text-orange-800">Báo giá</span> {{ money_vnd($ticket->quoteTotal()) }}
                            @else
                                {{ $ticket->result?->label() ?? '—' }}
                            @endif
                        </td>
                        <td><x-status-pill :status="$ticket->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-muted">Không có phiếu nào khớp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</x-layouts.app>
