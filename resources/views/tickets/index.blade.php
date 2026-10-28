@php
    use App\Enums\TicketKind;
    use App\Enums\TicketStatus;

    $filtered = $search !== '' || $status || $technician;
@endphp

<x-layouts.app title="Phiếu RMA" subtitle="Lập phiếu, xử lý theo quy trình, in phiếu nhận và phiếu trả">
    <x-slot:actions>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary"><x-icon name="plus" />Lập phiếu mới</a>
    </x-slot:actions>

    <nav class="tabbar mb-4" aria-label="Hình thức xử lý">
        <a href="{{ route('tickets.index', array_filter(['q' => $search, 'technician' => $technician])) }}" @class(['is-active' => $kind === null]) @if ($kind === null) aria-current="page" @endif>
            Tất cả <span class="tab-count">{{ $kindCounts->sum() }}</span>
        </a>
        @foreach (TicketKind::cases() as $type)
            <a href="{{ route('tickets.index', array_filter(['kind' => $type->value, 'q' => $search, 'technician' => $technician])) }}" @class(['is-active' => $kind === $type]) @if ($kind === $type) aria-current="page" @endif>
                {{ $type->shortLabel() }} <span class="tab-count">{{ $kindCounts[$type->value] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center">
        <form method="GET" class="flex min-w-0 flex-1 gap-2" role="search">
            @if ($kind) <input type="hidden" name="kind" value="{{ $kind->value }}"> @endif
            @if ($status) <input type="hidden" name="status" value="{{ $status->value }}"> @endif
            @if ($technician) <input type="hidden" name="technician" value="{{ $technician }}"> @endif
            <label class="search">
                <x-icon name="search" />
                <input type="search" name="q" value="{{ $search }}" class="input" placeholder="Quét mã vạch, hoặc gõ số phiếu, khách, SĐT, serial, model…" aria-label="Tìm phiếu" autofocus>
            </label>
            <button class="btn btn-secondary">Tìm</button>
        </form>
        @can('admin')
            <form method="GET" class="shrink-0">
                @if ($kind) <input type="hidden" name="kind" value="{{ $kind->value }}"> @endif
                @if ($status) <input type="hidden" name="status" value="{{ $status->value }}"> @endif
                @if ($search !== '') <input type="hidden" name="q" value="{{ $search }}"> @endif
                <select name="technician" class="input xl:w-56" aria-label="Lọc theo nhân viên phụ trách" data-autosubmit>
                    <option value="">Mọi nhân viên phụ trách</option>
                    @foreach ($technicians as $person)
                        <option value="{{ $person->id }}" @selected((string) $technician === (string) $person->id)>{{ $person->name }}@unless ($person->is_active) (đã khoá)@endunless</option>
                    @endforeach
                </select>
            </form>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2" role="group" aria-label="Lọc theo trạng thái">
        <a href="{{ route('tickets.index', array_filter(['kind' => $kind?->value, 'q' => $search, 'technician' => $technician])) }}" @class(['chip', 'is-active' => $status === null]) @if ($status === null) aria-current="true" @endif>
            Mọi trạng thái <b>{{ $statusCounts->sum() }}</b>
        </a>
        @foreach (TicketStatus::cases() as $option)
            @continue(! isset($statusCounts[$option->value]))
            <a href="{{ route('tickets.index', array_filter(['kind' => $kind?->value, 'status' => $option->value, 'q' => $search, 'technician' => $technician])) }}" @class(['chip', 'is-active' => $status === $option]) @if ($status === $option) aria-current="true" @endif>
                {{ $option->label() }} <b>{{ $statusCounts[$option->value] }}</b>
            </a>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-stack">
                <thead>
                    <tr><th>Số phiếu</th><th>Ngày nhận</th><th>Khách hàng</th><th>Thiết bị</th><th>Serial</th><th>Phụ trách</th><th>Kết quả / Phí</th><th>Trạng thái</th></tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr data-href="{{ route('tickets.show', $ticket) }}">
                            <td>
                                <div class="flex items-center justify-between gap-2 sm:block">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="ticket-no">{{ $ticket->ticket_no }}</a>
                                    <span class="sm:hidden"><x-status-pill :status="$ticket->status" /></span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                    <x-kind-tag :kind="$ticket->kind()" />
                                    <span class="text-xs text-muted">{{ $ticket->warranty_status->label() }}@if ($ticket->wasConverted()), từ {{ $ticket->originalKind()->shortLabel() }}@endif</span>
                                </div>
                            </td>
                            <td data-label="Ngày nhận" class="whitespace-nowrap">
                                <div>{{ vn_date($ticket->received_date) }}<div class="mt-1"><x-ticket-age :ticket="$ticket" /></div></div>
                            </td>
                            <td data-label="Khách hàng" class="sm:min-w-56">
                                <div>{{ $ticket->customer->name }}<div class="text-xs text-muted">{{ $ticket->contactLabel() }}</div></div>
                            </td>
                            <td data-label="Thiết bị">
                                <div>{{ $ticket->device->productModel->deviceType->name }}<div class="text-xs text-muted">{{ $ticket->device->productModel->shortName() }}</div></div>
                            </td>
                            <td data-label="Serial" class="font-mono text-xs">
                                <div>
                                    {{ $ticket->device->serial_number }}
                                    @if ($ticket->returnedDevice)
                                        <div class="text-muted" title="Serial máy trả">→ {{ $ticket->returnedDevice->serial_number }}@if ($ticket->isSwappedToOtherProduct()) <span class="rounded border border-line px-1">{{ $ticket->returnedDevice->productModel->shortName() }}</span>@endif</div>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Phụ trách">{{ $ticket->technician?->name ?? 'Chưa phân công' }}</td>
                            <td data-label="Kết quả / Phí" class="sm:min-w-36">
                                @if ($ticket->is_chargeable)
                                    <span><span class="rounded border border-orange-400 px-1.5 text-[11px] font-semibold text-orange-800">Có phí</span> {{ money_vnd($ticket->charge_amount) }}</span>
                                @elseif ($ticket->status === TicketStatus::Quoted)
                                    <span><span class="rounded border border-orange-400 px-1.5 text-[11px] font-semibold text-orange-800">Báo giá</span> {{ money_vnd($ticket->quoteTotal()) }}</span>
                                @else
                                    {{ $ticket->result?->label() ?? '—' }}
                                @endif
                            </td>
                            <td data-label="Trạng thái" class="stack-hide"><x-status-pill :status="$ticket->status" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty">
                                    <x-icon name="inbox" />
                                    @if ($filtered || $kind)
                                        <strong>Không có phiếu nào khớp</strong>
                                        <p>Thử bỏ bớt bộ lọc, hoặc tìm theo số khác.</p>
                                        <a href="{{ route('tickets.index') }}" class="btn btn-secondary btn-sm mt-1">Xem tất cả phiếu</a>
                                    @else
                                        <strong>Chưa có phiếu nào</strong>
                                        <p>Khi khách gửi máy, lập phiếu để bắt đầu theo dõi.</p>
                                        <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm mt-1"><x-icon name="plus" />Lập phiếu mới</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $tickets->links() }}
</x-layouts.app>
