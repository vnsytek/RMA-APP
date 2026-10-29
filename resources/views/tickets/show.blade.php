@php
    use App\Enums\ServiceType;
    use App\Enums\TicketAction;
    use App\Enums\ShipmentOutcome;
    use App\Enums\TicketKind;
    use App\Enums\TicketStatus;

    $kind = $ticket->kind();
    $atCustomer = $ticket->originalKind() === TicketKind::Onsite;
    $isOnsite = $ticket->originalKind()->isOnsite();
    $quoteEditable = $ticket->service_type === ServiceType::Repair && in_array($ticket->status, [TicketStatus::Inspecting, TicketStatus::Quoted], true) && ! $ticket->claimPending();
    $partsEditable = ! $ticket->isClosed() && $ticket->original_service_type !== ServiceType::Repair;
    $openAction = old('_action');
    $modelGroups = $models->groupBy(fn ($model) => $model->deviceType->name);
    $visited = $timeline->where('type', 'status')->map(fn ($event) => $event['data']->to_status)->filter()->values();
    $correctFailed = $errors->correct->any();
@endphp

<x-layouts.app :title="'Phiếu '.$ticket->ticket_no">
    <x-slot:header>
        <div class="mb-5">
            <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-[13px] font-medium text-muted hover:text-brand">
                <x-icon name="chevron-left" class="size-4" />Phiếu RMA
            </a>

            {{-- The ticket tag: number, what it is, where it stands. --}}
            <section class="tag">
                <div class="tag-stub" aria-hidden="true"></div>
                <div class="min-w-0 p-5 sm:pl-6">
                    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                <h1 class="font-mono text-[1.75rem] leading-none font-semibold tracking-tight text-ink sm:text-[2rem]"><span class="sr-only">Phiếu </span>{{ $ticket->ticket_no }}</h1>
                                <x-status-pill :status="$ticket->status" class="px-3 py-1 text-[13px]" />
                            </div>
                            <p class="mt-2.5 text-[15px] font-medium">{{ $ticket->device->displayName() }} <span class="font-mono text-[13px] font-normal text-muted">{{ $ticket->device->serial_number }}</span></p>
                            <p class="mt-1 text-[13px] text-muted">
                                <x-kind-tag :kind="$kind" class="mr-1 align-[1px]" />
                                Lập bởi {{ $ticket->creator->name }} lúc {{ $ticket->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        @if ($ticket->printsSlips() && $ticket->status !== TicketStatus::Cancelled)
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('tickets.slips.show', [$ticket, 'receipt']) }}" class="btn btn-secondary" target="_blank"><x-icon name="printer" />Phiếu nhận</a>
                                @if (in_array($ticket->status, [TicketStatus::Ready, TicketStatus::Returned], true))
                                    <a href="{{ route('tickets.slips.show', [$ticket, 'return']) }}" class="btn btn-secondary" target="_blank"><x-icon name="printer" />Phiếu trả</a>
                                @endif
                            </div>
                        @endif
                    </div>
                    {{-- Who the ticket is for and when: the first thing staff and managers look for. --}}
                    <dl class="customer-box mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)]" aria-label="Khách hàng và ngày">
                        <div class="min-w-0">
                            <dt class="meta-label">Khách hàng</dt>
                            <dd class="meta-value font-semibold break-words"><a href="{{ route('customers.show', $ticket->customer) }}" class="text-red-700 hover:underline">{{ $ticket->customer->name }}</a></dd>
                            @if ($address = $ticket->customer->shippingAddress())
                                <dd class="mt-0.5 text-[13px] text-muted">{{ $address }}</dd>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <dt class="meta-label">Người liên hệ</dt>
                            <dd class="meta-value font-semibold">{{ $ticket->contact_name ?: '—' }}</dd>
                            @if ($phone = $ticket->contact_phone ?? $ticket->customer->phone)
                                <dd class="mt-0.5 font-mono text-[13px] text-muted">{{ $phone }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="meta-label">{{ $atCustomer ? 'Ngày yêu cầu' : 'Ngày nhận' }}</dt>
                            <dd class="meta-value text-base font-semibold tabular-nums">{{ vn_date($ticket->received_date) }}</dd>
                            <dd class="mt-1"><x-ticket-age :ticket="$ticket" /></dd>
                        </div>
                        <div>
                            <dt class="meta-label">{{ $atCustomer ? 'Ngày hoàn tất' : 'Ngày trả' }}</dt>
                            <dd class="meta-value text-base font-semibold tabular-nums">{{ $ticket->returned_date ? vn_date($ticket->returned_date) : ($atCustomer ? 'Chưa xong' : 'Chưa trả') }}</dd>
                            @php
                                $pending = $ticket->shipments->firstWhere('outcome', ShipmentOutcome::Pending);
                                $dateNote = match (true) {
                                    $ticket->status === TicketStatus::Cancelled => 'Phiếu đã hủy',
                                    $ticket->status === TicketStatus::AtCenter && $pending !== null => 'Gửi TTBH ngày '.vn_date($pending->sent_date),
                                    $ticket->status === TicketStatus::Scheduled && $pending?->appointment_date !== null => 'Hẹn hãng đến '.vn_date($pending->appointment_date),
                                    default => null,
                                };
                            @endphp
                            @if ($dateNote)
                                <dd class="mt-0.5 text-[13px] text-muted">{{ $dateNote }}</dd>
                            @endif
                        </div>
                    </dl>

                    <x-ticket-steps :ticket="$ticket" :visited="$visited" class="mt-5 border-t border-line pt-5" />
                </div>
            </section>
        </div>
    </x-slot:header>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-5">

            @if ($ticket->service_type === ServiceType::Repair && ($ticket->quoteItems->isNotEmpty() || $quoteEditable))
                <section class="card overflow-hidden" id="bao-gia">
                    <div class="card-head">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2>Báo giá</h2>
                            @if ($ticket->quote_status)
                                <x-pill :tone="$ticket->quote_status->tone()">{{ $ticket->quote_status->label() }}</x-pill>
                            @endif
                        </div>
                        @if ($ticket->quoted_at)
                            <p class="text-xs text-muted">Gửi báo giá {{ $ticket->quoted_at->format('d/m/Y H:i') }}@if ($ticket->quote_decided_at), khách trả lời {{ $ticket->quote_decided_at->format('d/m/Y H:i') }}@endif</p>
                        @endif
                    </div>
                    <div class="overflow-x-auto border-t border-line">
                        <table class="table">
                            <thead><tr><th>Nội dung</th><th class="text-right">SL</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th>@if ($quoteEditable)<th><span class="sr-only">Thao tác</span></th>@endif</tr></thead>
                            <tbody>
                                @forelse ($ticket->quoteItems as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-right">{{ $item->quantity }}</td>
                                        <td class="text-right">{{ money_vnd($item->unit_price) }}</td>
                                        <td class="text-right">{{ money_vnd($item->lineTotal()) }}</td>
                                        @if ($quoteEditable)
                                            <td class="w-12 text-right">
                                                <form method="POST" action="{{ route('tickets.quote-items.destroy', [$ticket, $item]) }}" data-confirm="Xoá dòng &quot;{{ $item->description }}&quot; khỏi báo giá?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-ghost btn-sm hover:text-red-700" aria-label="Xoá dòng {{ $item->description }}" title="Xoá dòng"><x-icon name="trash" /></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Chưa có dòng nào. Thêm linh kiện hoặc tiền công ở ô bên dưới.</td></tr>
                                @endforelse
                                @if ($ticket->quoteItems->isNotEmpty())
                                    <tr class="bg-panel font-semibold"><td colspan="3">Tổng cộng</td><td class="text-right">{{ money_vnd($ticket->quoteTotal()) }}</td>@if ($quoteEditable)<td></td>@endif</tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    @if ($quoteEditable)
                        <div class="border-t border-line px-5 py-4">
                            <form method="POST" action="{{ route('tickets.quote-items.store', $ticket) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input class="input min-w-56 flex-1" name="description" placeholder="Nội dung: linh kiện hoặc tiền công" required maxlength="255" aria-label="Nội dung báo giá">
                                <input class="input w-20" type="number" name="quantity" value="1" min="1" required aria-label="Số lượng">
                                <input class="input w-40" name="unit_price" placeholder="Đơn giá" required inputmode="numeric" autocomplete="off" data-money aria-label="Đơn giá">
                                <button class="btn btn-secondary"><x-icon name="plus" />Thêm dòng</button>
                            </form>
                            <x-field-error name="description" bag="quote" />
                            <x-field-error name="quantity" bag="quote" />
                            <x-field-error name="unit_price" bag="quote" />
                            <p class="hint mt-3">Lỗi cần thay / sửa: lập bảng giá rồi bấm "Gửi báo giá cho khách". Giá chỉ báo một lần: khách đồng ý thì sửa, không đồng ý thì không sửa. Lỗi đơn giản chọn "Miễn phí", không sửa được chọn "Báo phế". Vẫn sửa được bảng này sau khi đã gửi nếu cần đính chính — mỗi lần sửa sẽ ghi vào Lịch sử.</p>
                        </div>
                    @endif
                </section>
            @endif

            @if ($ticket->is_chargeable)
                <section class="card p-5" id="erp">
                    <h2 class="card-title">Chứng từ ERP <span class="card-note">(phiếu có phí)</span></h2>
                    <form method="POST" action="{{ route('tickets.erp.update', $ticket) }}" class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf @method('PUT')
                        <div>
                            <span class="label">Số chứng từ V223 <span class="label-hint">(nhận hàng, chính là số phiếu)</span></span>
                            <p class="input bg-panel font-mono">{{ $ticket->ticket_no }}</p>
                        </div>
                        <div>
                            <label class="label" for="erp_return_no">Số chứng từ V233 <span class="label-hint">(trả hàng)</span></label>
                            <input class="input font-mono" name="erp_return_no" id="erp_return_no" value="{{ old('erp_return_no', $ticket->erp_return_no) }}" maxlength="50" @disabled($ticket->isClosed())>
                            <x-field-error name="erp_return_no" bag="erp" />
                        </div>
                        @unless ($ticket->isClosed())
                            <button class="btn btn-secondary">Lưu V233</button>
                        @endunless
                    </form>
                    <p class="hint mt-3">Phiếu có phí phải nhập V233 (không trùng phiếu khác) thì mới bấm được "Trả khách".</p>
                </section>
            @endif

            <section class="card p-5" aria-labelledby="thong-tin-title">
                <h2 id="thong-tin-title" class="sr-only">Thông tin phiếu</h2>
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="meta-label">Tình trạng bảo hành</dt><dd class="mt-1"><x-pill :tone="$ticket->warranty_status->tone()">{{ $ticket->warranty_status->label() }}</x-pill></dd></div>
                    <div><dt class="meta-label">Thiết bị</dt><dd class="meta-value">{{ $ticket->device->displayName() }}</dd></div>
                    <div><dt class="meta-label">Serial nhận</dt><dd class="meta-value font-mono">{{ $ticket->device->serial_number }}</dd></div>
                    @if ($ticket->returnedDevice)
                        <div>
                            <dt class="meta-label">{{ $ticket->isSwappedToOtherProduct() ? 'Máy trả (hãng đổi sản phẩm khác)' : 'Serial trả (hãng đổi máy)' }}</dt>
                            <dd class="meta-value">@if ($ticket->isSwappedToOtherProduct()){{ $ticket->returnedDevice->displayName() }}<br>@endif<span class="font-mono">{{ $ticket->returnedDevice->serial_number }}</span></dd>
                        </div>
                    @endif
                    @unless ($atCustomer)
                        <div><dt class="meta-label">Phụ kiện</dt><dd class="meta-value">{{ $ticket->accessories ?: '—' }}</dd></div>
                    @endunless
                    <div><dt class="meta-label">Phụ trách</dt><dd class="meta-value">{{ $ticket->technician?->name ?? 'Chưa phân công' }}</dd></div>
                    @if ($ticket->result)
                        <div><dt class="meta-label">Kết quả</dt><dd class="meta-value">{{ $ticket->result->label() }}</dd></div>
                    @endif
                    @if ($ticket->is_chargeable)
                        <div><dt class="meta-label">Số tiền</dt><dd class="meta-value">{{ money_vnd($ticket->charge_amount) }}</dd></div>
                    @endif
                    @if ($ticket->service_type === ServiceType::Repair && $ticket->result?->carriesRepairWarranty())
                        <div>
                            <dt class="meta-label">BH sau sửa chữa</dt>
                            <dd class="meta-value">
                                @if ($ticket->warrantyItems->isNotEmpty())
                                    {{ $ticket->warrantyItems->count() }} hạng mục · <a href="#bao-hanh" class="link">xem bên dưới</a>
                                @elseif ($ticket->repair_warranty_months)
                                    {{ $ticket->repair_warranty_months }} tháng · {{ $ticket->returned_date ? 'đến '.vn_date($ticket->repairWarrantyEndsOn()) : 'tính từ ngày trả' }}
                                @else
                                    Không bảo hành
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($ticket->wasConverted())
                        <div><dt class="meta-label">Chuyển loại</dt><dd class="meta-value">Từ {{ $ticket->originalKind()->label() }} (TTBH từ chối)</dd></div>
                    @endif
                </dl>

                <div class="section-title">Lỗi khách báo</div>
                <p class="whitespace-pre-line">{{ $ticket->fault_description }}</p>

                @if ($ticket->scrap_reason)
                    <div class="section-title">Lý do báo phế</div>
                    <p>{{ $ticket->scrap_reason }}</p>
                @endif

                @if ($ticket->note)
                    <div class="section-title">Ghi chú</div>
                    <p class="whitespace-pre-line">{{ $ticket->note }}</p>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg bg-panel px-4 py-3">
                    <span class="text-muted">Máy <span class="font-mono">{{ $device->device->serial_number }}</span></span>
                    <x-repair-warranty :warranty="$device->repairWarranty" />
                    <a href="{{ route('devices.show', $device->device) }}" class="btn btn-secondary btn-sm sm:ml-auto"><x-icon name="history" />Lịch sử máy</a>
                </div>

                @if ($history->isNotEmpty())
                    <p class="alert alert-warning mt-3">
                        <x-icon name="info" />
                        <span>Máy này đã từng gửi:
                            @foreach ($history as $previous)
                                <x-ticket-link :ticket="$previous" /> ({{ vn_date($previous->received_date) }})@if (! $loop->last), @endif
                            @endforeach
                        </span>
                    </p>
                @endif
            </section>

            @if ($ticket->claimTicket)
                <section class="card border-amber-300 p-5" id="yeu-cau-bao-hanh">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <h2 class="card-title">Khách yêu cầu bảo hành sửa chữa theo phiếu <x-ticket-link :ticket="$ticket->claimTicket" /></h2>
                        @if ($ticket->claim_result)
                            <x-pill :tone="$ticket->claim_result->tone()">{{ $ticket->claim_result->label() }}</x-pill>
                        @else
                            <x-pill tone="amber">Chờ IT kết luận</x-pill>
                        @endif
                    </div>
                    <p class="mb-3 text-xs text-muted">Phiếu gốc trả ngày {{ vn_date($ticket->claimTicket->returned_date) }} · {{ $ticket->claimTicket->result?->label() }}</p>
                    <x-warranty-items :ticket="$ticket->claimTicket" :highlight="$ticket->claim_item_id" />
                    @if ($ticket->claim_note)
                        <p class="mt-3"><span class="font-semibold">{{ $ticket->claim_result === \App\Enums\ClaimResult::Rejected ? 'Lý do ngoài phạm vi' : 'Ghi chú' }}:</span> {{ $ticket->claim_note }}</p>
                    @endif
                    @if ($ticket->claimPending() && $ticket->status === TicketStatus::Inspecting)
                        <p class="mt-3 text-amber-800">Sau khi kiểm tra, chọn "Trong phạm vi BH" hoặc "Ngoài phạm vi BH" ở Bước tiếp theo. Báo giá chỉ mở khi ngoài phạm vi.</p>
                    @endif
                    @if ($ticket->claim_result === \App\Enums\ClaimResult::Covered)
                        <p class="mt-3 text-muted">Sửa miễn phí, không cộng thêm thời gian bảo hành: hạn vẫn tính theo phiếu {{ $ticket->claimTicket->ticket_no }}.</p>
                    @endif
                </section>
            @endif

            @if ($ticket->shipments->isNotEmpty())
                <section class="card overflow-hidden">
                    <div class="card-head"><h2>{{ $isOnsite ? ($atCustomer ? 'Hãng đến nhà khách bảo hành' : 'Hãng đến Sang Y bảo hành') : 'Các lần gửi TTBH' }}</h2></div>
                    <div class="overflow-x-auto border-t border-line">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Lần</th><th>Hãng / TTBH</th><th>Mã hồ sơ hãng</th><th>{{ $isOnsite ? 'Ngày yêu cầu' : 'Ngày gửi' }}</th>
                                    @if ($isOnsite) <th>Ngày hẹn</th> @endif
                                    <th>{{ $isOnsite ? 'Ngày xong' : 'Ngày về' }}</th>
                                    @unless ($isOnsite) <th>Phiếu trả TTBH</th> @endunless
                                    <th>Kết quả</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ticket->shipments as $shipment)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $shipment->serviceCenter->name }}</td>
                                        <td class="font-mono text-xs">{{ $shipment->vendor_case_no ?? '—' }}</td>
                                        <td>{{ vn_date($shipment->sent_date) }}</td>
                                        @if ($isOnsite) <td>{{ vn_date($shipment->appointment_date, '—') }}</td> @endif
                                        <td>{{ vn_date($shipment->back_date, '—') }}</td>
                                        @unless ($isOnsite) <td class="font-mono text-xs">{{ $shipment->center_return_no ?? '—' }}</td> @endunless
                                        <td>
                                            @if ($shipment->outcome === ShipmentOutcome::Pending)
                                                <x-pill tone="violet">{{ $isOnsite ? 'Chờ hãng đến' : 'Đang ở TTBH' }}</x-pill>
                                                <span class="text-xs text-muted">{{ (int) $shipment->sent_date->diffInDays(today()) }} ngày</span>
                                            @else
                                                <x-pill :tone="$shipment->outcome->tone()">{{ $shipment->outcome->label() }}</x-pill>
                                            @endif
                                            @if ($shipment->note)
                                                <div class="mt-1 text-xs text-muted">{{ $shipment->note }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($ticket->original_service_type !== ServiceType::Repair)
                <section class="card overflow-hidden" id="linh-kien">
                    <div class="card-head"><h2>Linh kiện hãng / TTBH thay</h2></div>
                    <div class="overflow-x-auto border-t border-line">
                        <table class="table">
                            <thead><tr><th>Mã linh kiện</th><th>Mô tả</th><th class="text-right">SL</th><th>Lần gửi</th>@if ($partsEditable)<th><span class="sr-only">Thao tác</span></th>@endif</tr></thead>
                            <tbody>
                                @forelse ($ticket->replacedParts as $part)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $part->part_code ?? '—' }}</td>
                                        <td>{{ $part->description }}</td>
                                        <td class="text-right">{{ $part->quantity }}</td>
                                        <td>{{ $part->shipment ? '#'.($ticket->shipments->search(fn ($shipment) => $shipment->is($part->shipment)) + 1) : '—' }}</td>
                                        @if ($partsEditable)
                                            <td class="w-12 text-right">
                                                <form method="POST" action="{{ route('tickets.replaced-parts.destroy', [$ticket, $part]) }}" data-confirm="Xoá linh kiện &quot;{{ $part->description }}&quot;?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-ghost btn-sm hover:text-red-700" aria-label="Xoá linh kiện {{ $part->description }}" title="Xoá linh kiện"><x-icon name="trash" /></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Chưa có linh kiện nào.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($partsEditable)
                        <div class="border-t border-line px-5 py-4">
                            <form method="POST" action="{{ route('tickets.replaced-parts.store', $ticket) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input class="input w-40 font-mono" name="part_code" placeholder="Mã, VD: 5JKJK" maxlength="50" aria-label="Mã linh kiện (tuỳ chọn)">
                                <input class="input min-w-56 flex-1" name="description" placeholder="Mô tả linh kiện" required maxlength="255" aria-label="Mô tả linh kiện">
                                <input class="input w-20" type="number" name="quantity" value="1" min="1" required aria-label="Số lượng">
                                <button class="btn btn-secondary"><x-icon name="plus" />Thêm linh kiện</button>
                            </form>
                            <x-field-error name="description" bag="parts" />
                        </div>
                    @endif
                </section>
            @endif

            @if ($ticket->warrantyItems->isNotEmpty() || $ticket->warranty_exclusions || $ticket->claims->isNotEmpty())
                <section class="card p-5" id="bao-hanh">
                    <h2 class="card-title mb-3">Bảo hành sau sửa chữa <span class="card-note">(tính từ ngày trả máy)</span></h2>
                    <x-warranty-items :ticket="$ticket" />
                    @if ($ticket->claims->isNotEmpty())
                        <div class="mt-3">
                            <span class="font-semibold">Khách đã quay lại bảo hành:</span>
                            @foreach ($ticket->claims as $claim)
                                <x-ticket-link :ticket="$claim" />
                                ({{ $claim->claim_result?->label() ?? 'đang kiểm tra' }})@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            <section class="card p-5">
                <h2 class="card-title mb-4">Ảnh và chứng từ <span class="card-note">(theo giai đoạn)</span></h2>
                <x-attachments :ticket="$ticket" />
            </section>
        </div>

        <aside class="space-y-5" aria-label="Phân công và lịch sử">
            @if (! $ticket->isClosed() || auth()->user()->isAdmin())
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="card space-y-4 p-5">
                    @csrf @method('PUT')
                    <h2 class="card-title">{{ auth()->user()->can('assign', $ticket) ? 'Phân công & ghi chú' : 'Ghi chú' }}</h2>
                    <div>
                        <label class="label" for="technician_id">Nhân viên phụ trách</label>
                        @can('assign', $ticket)
                            <select class="input" name="technician_id" id="technician_id">
                                <option value="">Chưa phân công</option>
                                @foreach ($technicians as $technician)
                                    <option value="{{ $technician->id }}" @selected($ticket->technician_id === $technician->id)>{{ $technician->name }}</option>
                                @endforeach
                            </select>
                            <p class="hint">Người lập phiếu: {{ $ticket->creator->name }}. Người được phân công sẽ thấy và xử lý tiếp phiếu này.</p>
                        @else
                            <p class="font-medium">{{ $ticket->technician?->name ?? 'Chưa phân công' }}</p>
                            <p class="hint">Chỉ Admin đổi được người phụ trách.</p>
                        @endcan
                    </div>
                    <div>
                        <label class="label" for="note">Ghi chú</label>
                        <textarea class="input min-h-20" name="note" id="note" maxlength="2000">{{ $ticket->note }}</textarea>
                    </div>
                    <button class="btn btn-secondary w-full">{{ auth()->user()->can('assign', $ticket) ? 'Lưu phân công & ghi chú' : 'Lưu ghi chú' }}</button>
                </form>
            @endif

            <section class="card p-5">
                <h2 class="card-title mb-4">Lịch sử</h2>
                <ol class="relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[4.5px] before:w-px before:bg-line">
                    @foreach ($timeline as $event)
                        <li class="relative grid grid-cols-[10px_1fr] gap-3">
                            @if ($event['type'] === 'status')
                                @php $log = $event['data']; @endphp
                                <span class="mt-1.5 size-2.5 rounded-full bg-brand ring-3 ring-white"></span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1">
                                        @if ($log->from_status)
                                            <x-status-pill :status="$log->from_status" /> <span class="text-muted" aria-label="sang">→</span>
                                        @endif
                                        <x-status-pill :status="$log->to_status" />
                                    </div>
                                    <div class="mt-1 text-xs text-muted">{{ $log->user->name }}, {{ $log->created_at->format('d/m/Y H:i') }}</div>
                                    @if ($log->note)
                                        <div class="mt-1">{{ $log->note }}</div>
                                    @endif
                                    @if ($log->attachments->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($log->attachments as $attachment)
                                                <a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" target="_blank" rel="noopener"
                                                   class="grid size-12 place-items-center overflow-hidden rounded-md border border-line bg-white font-mono text-[10px] font-semibold text-muted hover:border-brand"
                                                   title="{{ $attachment->stage->label() }} · {{ $attachment->file_name }}">
                                                    @if ($attachment->isImage())
                                                        <img src="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" alt="{{ $attachment->stage->label() }}" class="size-full object-cover" loading="lazy">
                                                    @else
                                                        PDF
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                @php $edit = $event['data']; @endphp
                                <span class="mt-1.5 size-2.5 rounded-full bg-amber-500 ring-3 ring-white"></span>
                                <div class="min-w-0">
                                    <div class="font-medium">Sửa: {{ $edit->field }}</div>
                                    <div class="mt-1 text-xs text-muted">{{ $edit->user->name }}, {{ $edit->created_at->format('d/m/Y H:i') }}</div>
                                    <div class="mt-1 break-words">
                                        @if ($edit->old_value !== null && $edit->new_value !== null)
                                            <span class="text-muted line-through">{{ $edit->old_value }}</span> → <span class="font-medium">{{ $edit->new_value }}</span>
                                        @elseif ($edit->new_value !== null)
                                            <span class="font-medium">+ {{ $edit->new_value }}</span>
                                        @else
                                            <span class="text-muted line-through">− {{ $edit->old_value }}</span>
                                        @endif
                                    </div>
                                    @if ($edit->note)
                                        <div class="mt-1 text-xs">Lý do: {{ $edit->note }}</div>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <details class="card group" @if ($correctFailed) open @endif>
                <summary class="flex cursor-pointer list-none items-start gap-3 p-5 [&::-webkit-details-marker]:hidden">
                    <x-icon name="pencil" class="mt-0.5 size-4 shrink-0 text-muted" />
                    <span class="flex-1">
                        <span class="block font-semibold">Sửa thông tin phiếu</span>
                        <span class="mt-0.5 block text-xs text-muted">Đính chính serial, lỗi khách báo hoặc ngày nhận bị gõ nhầm.</span>
                    </span>
                    <x-icon name="chevron-right" class="mt-0.5 size-4 shrink-0 text-muted transition-transform group-open:rotate-90" />
                </summary>
                <form method="POST" action="{{ route('tickets.correct', $ticket) }}" class="space-y-4 border-t border-line p-5">
                    @csrf @method('PUT')
                    <p class="text-xs text-muted">Mọi thay đổi ở đây được ghi vào Lịch sử, không bị xoá dấu vết — kể cả khi phiếu đã đóng.</p>
                    <div>
                        <label class="label" for="correct-serial">Serial</label>
                        <input class="input font-mono" name="serial_number" id="correct-serial" required maxlength="100" value="{{ old('serial_number', $ticket->device->serial_number) }}">
                        <x-field-error name="serial_number" bag="correct" />
                    </div>
                    <div>
                        <label class="label" for="correct-fault">Lỗi khách báo</label>
                        <textarea class="input min-h-16" name="fault_description" id="correct-fault" required maxlength="2000">{{ old('fault_description', $ticket->fault_description) }}</textarea>
                        <x-field-error name="fault_description" bag="correct" />
                    </div>
                    <div>
                        <label class="label" for="correct-date">{{ $atCustomer ? 'Ngày yêu cầu' : 'Ngày nhận' }}</label>
                        <input class="input" type="date" name="received_date" id="correct-date" required max="{{ today()->toDateString() }}" value="{{ old('received_date', $ticket->received_date->toDateString()) }}">
                        <x-field-error name="received_date" bag="correct" />
                    </div>
                    <div>
                        <label class="label" for="correct-note">Lý do sửa <span class="label-hint">(tuỳ chọn)</span></label>
                        <input class="input" name="note" id="correct-note" maxlength="500" placeholder="VD: gõ nhầm số cuối serial">
                    </div>
                    <button class="btn btn-secondary w-full">Lưu đính chính</button>
                </form>
            </details>

            @can('delete', $ticket)
                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="rounded-xl border border-red-200 p-5" data-confirm="Xoá phiếu {{ $ticket->ticket_no }}? Phiếu sẽ không còn trong danh sách, số phiếu không được dùng lại.">
                    @csrf @method('DELETE')
                    <h2 class="font-semibold text-red-800">Xoá phiếu</h2>
                    <p class="mt-1 mb-3 text-xs text-muted">Dùng khi lập nhầm phiếu. Chỉ Admin thấy mục này.</p>
                    <button class="btn btn-danger w-full"><x-icon name="trash" />Xoá phiếu {{ $ticket->ticket_no }}</button>
                </form>
            @endcan
        </aside>
    </div>

    {{-- Next step: a bar pinned to the bottom of the screen, so it is at hand wherever staff have scrolled to.
         Each step's form opens in a dialog instead of pushing the page down. --}}
    @if ($actions !== [])
        <section class="sticky bottom-0 z-20 -mx-4 mt-6 -mb-16 border-t border-line bg-white/95 px-4 py-3 shadow-[0_-6px_16px_-10px_rgb(0_0_0/0.25)] backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
                 id="buoc-tiep-theo" aria-labelledby="buoc-tiep-theo-title">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <div class="mr-auto">
                    <h2 id="buoc-tiep-theo-title" class="text-sm font-semibold">Bước tiếp theo</h2>
                    <p class="text-xs text-muted">Chọn việc vừa làm xong để điền thông tin và chuyển phiếu sang bước sau.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($actions as $action)
                        <button type="button" data-dialog-open="action-{{ $action->value }}"
                                @class(['btn', 'btn-primary' => $action->style() === 'primary', 'btn-secondary' => $action->style() === 'secondary', 'btn-danger' => $action->style() === 'danger'])>
                            {{ $action->label() }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

                    @foreach ($actions as $action)
                        @php
                            $photoStage = $action->photoStage();
                        @endphp
                        <dialog id="action-{{ $action->value }}" class="modal max-w-2xl" aria-labelledby="action-{{ $action->value }}-title" @if ($openAction === $action->value) data-open @endif>
                        <form method="POST" action="{{ route('tickets.actions.store', [$ticket, $action]) }}" class="modal-body"
                              @if ($photoStage) enctype="multipart/form-data" @endif>
                            @csrf
                            <input type="hidden" name="_action" value="{{ $action->value }}">
                            <div class="modal-head">
                                <div>
                                    <h2 id="action-{{ $action->value }}-title">{{ $action->label() }}</h2>
                                    <p class="text-xs text-muted">Phiếu {{ $ticket->ticket_no }} · {{ $ticket->device->displayName() }}</p>
                                </div>
                                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
                            </div>
                            @if ($openAction === $action->value && $errors->action->any())
                                <div class="alert alert-error" role="alert">
                                    <x-icon name="alert" />
                                    <div>
                                        @foreach ($errors->action->all() as $error)
                                            <p>{{ $error }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($action->fields() as $name => $field)
                                    @php
                                        $id = $action->value.'-'.$name;
                                        $default = match ($name) {
                                            'sent_date', 'back_date', 'returned_date', 'done_date' => today()->toDateString(),
                                            'warranty_exclusions' => $ticket->warranty_exclusions ?? $ticket->device->productModel->deviceType->warranty_exclusions,
                                            'vendor_case_no' => $ticket->shipments->last()?->vendor_case_no,
                                            'service_center_id' => $ticket->shipments->last()?->service_center_id,
                                            default => null,
                                        };
                                        $value = $openAction === $action->value ? old($name, $default) : $default;
                                    @endphp
                                    <div @class(['sm:col-span-2' => in_array($field['type'], ['textarea', 'lines', 'warranty_items'], true)])>
                                        <label class="label" for="{{ $id }}">{{ $field['label'] }}@if ($field['required']) <span class="req">*</span>@else <span class="label-hint">(tuỳ chọn)</span>@endif</label>
                                        @switch($field['type'])
                                            @case('service_center')
                                                <select class="input" name="{{ $name }}" id="{{ $id }}" required>
                                                    <option value="">Chọn…</option>
                                                    @foreach ($serviceCenters as $center)
                                                        <option value="{{ $center->id }}" @selected((string) $value === (string) $center->id)>{{ $center->name }}</option>
                                                    @endforeach
                                                </select>
                                                @break
                                            @case('product_model')
                                                <select class="input" name="{{ $name }}" id="{{ $id }}">
                                                    <option value="">Cùng model máy cũ ({{ $ticket->device->productModel->shortName() }})</option>
                                                    @foreach ($modelGroups as $typeName => $group)
                                                        <optgroup label="{{ $typeName }}">
                                                            @foreach ($group as $model)
                                                                <option value="{{ $model->id }}" @selected((string) $value === (string) $model->id)>{{ $model->shortName() }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                @break
                                            @case('warranty_items')
                                                @php
                                                    $rows = $action === TicketAction::FinishRepair
                                                        ? $ticket->quoteItems->map(fn ($item) => ['quote_item_id' => $item->id, 'description' => $item->description, 'price' => $item->lineTotal()])->all()
                                                        : [];
                                                    $rows = [...$rows, ...array_fill(0, 3, ['quote_item_id' => null, 'description' => null, 'price' => null])];
                                                    $isOpen = $openAction === $action->value;
                                                @endphp
                                                <div class="overflow-x-auto rounded-lg border border-line bg-white" id="{{ $id }}">
                                                    <table class="table">
                                                        <thead><tr><th>Hạng mục</th><th class="w-36">BH (tháng)</th></tr></thead>
                                                        <tbody>
                                                            @foreach ($rows as $i => $row)
                                                                <tr>
                                                                    <td>
                                                                        @if ($row['quote_item_id'])
                                                                            <input type="hidden" name="{{ $name }}[{{ $i }}][quote_item_id]" value="{{ $row['quote_item_id'] }}">
                                                                            <input type="hidden" name="{{ $name }}[{{ $i }}][description]" value="{{ $row['description'] }}">
                                                                            {{ $row['description'] }}
                                                                            <div class="text-xs text-muted">Báo giá · {{ money_vnd($row['price']) }}</div>
                                                                        @else
                                                                            <input class="input" name="{{ $name }}[{{ $i }}][description]" value="{{ $isOpen ? old("{$name}.{$i}.description") : '' }}" maxlength="255"
                                                                                   placeholder="{{ $loop->first && $rows[0]['quote_item_id'] === null ? 'VD: Vệ sinh bao lụa, hết kẹt giấy' : 'Hạng mục khác, VD: Cài Windows 11' }}" aria-label="Hạng mục bảo hành {{ $i + 1 }}">
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <input class="input" type="number" name="{{ $name }}[{{ $i }}][months]" value="{{ $isOpen ? old("{$name}.{$i}.months") : '' }}"
                                                                               min="1" max="{{ config('rma.max_repair_warranty_months') }}" placeholder="Không BH" aria-label="Số tháng bảo hành hạng mục {{ $i + 1 }}">
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                @break
                                            @case('lines')
                                                <textarea class="input min-h-20" name="{{ $name }}" id="{{ $id }}" maxlength="2000">{{ $value }}</textarea>
                                                @break
                                            @case('claim_item')
                                                @php
                                                    $claimItems = $ticket->claimTicket?->activeWarrantyItems() ?? collect();
                                                @endphp
                                                @if ($ticket->claimTicket?->warrantyItems->isEmpty())
                                                    <p class="text-sm">Phiếu {{ $ticket->claimTicket->ticket_no }} là phiếu cũ, chưa ghi hạng mục: bảo hành chung đến {{ vn_date($ticket->claimTicket->repairWarrantyEndsOn()) }}.</p>
                                                @elseif ($claimItems->isEmpty())
                                                    <p class="text-sm text-red-700">Không còn hạng mục nào trong hạn bảo hành, hãy chọn "Ngoài phạm vi BH".</p>
                                                @else
                                                    <select class="input" name="{{ $name }}" id="{{ $id }}" required>
                                                        <option value="">Chọn…</option>
                                                        @foreach ($claimItems as $item)
                                                            <option value="{{ $item->id }}" @selected((string) $value === (string) $item->id || $claimItems->count() === 1)>{{ $item->description }} · {{ $item->statusLabel() }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                                @break
                                            @case('date')
                                                <input class="input" type="date" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" max="{{ today()->toDateString() }}" required>
                                                @break
                                            @case('months')
                                                <input class="input w-40" type="number" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" min="1" max="{{ config('rma.max_repair_warranty_months') }}" placeholder="VD: 3">
                                                @break
                                            @case('textarea')
                                                <textarea class="input min-h-16" name="{{ $name }}" id="{{ $id }}" maxlength="1000" @required($field['required'])>{{ $value }}</textarea>
                                                @break
                                            @default
                                                <input class="input" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" maxlength="100">
                                        @endswitch
                                        @isset($field['hint'])
                                            <p class="hint">{{ $field['hint'] }}</p>
                                        @endisset
                                    </div>
                                @endforeach
                                @if ($photoStage)
                                    <div class="sm:col-span-2">
                                        <label class="label" for="{{ $action->value }}-photos">
                                            @if ($action->requiresPhoto())
                                                Ảnh tình trạng máy {{ mb_strtolower($photoStage->label()) }} <span class="req">*</span> <span class="label-hint">(ít nhất 1 ảnh)</span>
                                            @else
                                                Ảnh / chứng từ <span class="label-hint">(tuỳ chọn)</span>
                                            @endif
                                        </label>
                                        <input type="file" name="photos[]" id="{{ $action->value }}-photos" multiple accept="image/*,application/pdf" class="input py-1.5"
                                               data-file-preview="{{ $action->value }}-photos-preview" @required($action->requiresPhoto())>
                                        <p class="hint">{{ $photoStage->hint() }}</p>
                                        <div id="{{ $action->value }}-photos-preview" class="mt-2 flex flex-wrap gap-2"></div>
                                    </div>
                                @endif
                            </div>
                            <div class="modal-foot">
                                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                                <button class="btn {{ $action->style() === 'danger' ? 'btn-danger-solid' : 'btn-primary' }}">Xác nhận: {{ $action->label() }}</button>
                            </div>
                        </form>
                        </dialog>
                    @endforeach
    @endif
</x-layouts.app>
