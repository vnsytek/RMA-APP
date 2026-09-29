@php
    use App\Enums\ServiceType;
    use App\Enums\TicketAction;
    use App\Enums\ShipmentOutcome;
    use App\Enums\TicketKind;
    use App\Enums\TicketStatus;

    $kind = $ticket->kind();
    $atCustomer = $ticket->originalKind() === TicketKind::Onsite;
    $isOnsite = $ticket->originalKind()->isOnsite();
    $quoteEditable = $ticket->service_type === ServiceType::Repair && $ticket->status === TicketStatus::Inspecting && ! $ticket->claimPending();
    $partsEditable = ! $ticket->isClosed() && $ticket->original_service_type !== ServiceType::Repair;
    $openAction = old('_action');
    $modelGroups = $models->groupBy(fn ($model) => $model->deviceType->name);
@endphp

<x-layouts.app :title="'Phiếu '.$ticket->ticket_no" :subtitle="$kind->label().' · Lập bởi '.$ticket->creator->name.' lúc '.$ticket->created_at->format('d/m/Y H:i')">
    <x-slot:actions>
        <x-status-pill :status="$ticket->status" class="px-3 py-1 text-sm" />
        @if ($ticket->printsSlips() && $ticket->status !== TicketStatus::Cancelled)
            <a href="{{ route('tickets.slips.show', [$ticket, 'receipt']) }}" class="btn btn-secondary" target="_blank">Phiếu nhận</a>
        @endif
        @if ($ticket->printsSlips() && in_array($ticket->status, [TicketStatus::Ready, TicketStatus::Returned], true))
            <a href="{{ route('tickets.slips.show', [$ticket, 'return']) }}" class="btn btn-secondary" target="_blank">Phiếu trả</a>
        @endif
        <a href="{{ route('tickets.index') }}" class="btn btn-secondary">← Danh sách</a>
    </x-slot:actions>

    <div class="grid items-start gap-5 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-5">
            <section class="card p-5">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">Khách hàng</dt><dd class="font-medium"><a href="{{ route('customers.edit', $ticket->customer) }}" class="hover:underline">{{ $ticket->customer->name }}</a></dd></div>
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">Liên hệ</dt><dd class="font-medium">{{ collect([$ticket->customer->contact_name, $ticket->customer->phone])->filter()->implode(' · ') }}</dd></div>
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">Tình trạng bảo hành</dt><dd><x-pill :tone="$ticket->warranty_status->tone()">{{ $ticket->warranty_status->label() }}</x-pill></dd></div>
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">Thiết bị</dt><dd class="font-medium">{{ $ticket->device->displayName() }}</dd></div>
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">Serial nhận</dt><dd class="font-mono font-medium">{{ $ticket->device->serial_number }}</dd></div>
                    @if ($ticket->returnedDevice)
                        <div>
                            <dt class="text-[11px] tracking-wider text-muted uppercase">{{ $ticket->isSwappedToOtherProduct() ? 'Máy trả (hãng đổi sản phẩm khác)' : 'Serial trả (hãng đổi máy)' }}</dt>
                            <dd class="font-medium">@if ($ticket->isSwappedToOtherProduct()){{ $ticket->returnedDevice->displayName() }}<br>@endif<span class="font-mono">{{ $ticket->returnedDevice->serial_number }}</span></dd>
                        </div>
                    @endif
                    @unless ($atCustomer)
                        <div><dt class="text-[11px] tracking-wider text-muted uppercase">Phụ kiện</dt><dd class="font-medium">{{ $ticket->accessories ?: '—' }}</dd></div>
                    @endunless
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">{{ $atCustomer ? 'Ngày yêu cầu' : 'Ngày nhận' }}</dt><dd class="font-medium">{{ vn_date($ticket->received_date) }}</dd></div>
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">{{ $atCustomer ? 'Ngày hoàn tất' : 'Ngày trả' }}</dt><dd class="font-medium">{{ vn_date($ticket->returned_date, '—') }}</dd></div>
                    <div><dt class="text-[11px] tracking-wider text-muted uppercase">Phụ trách</dt><dd class="font-medium">{{ $ticket->technician?->name ?? 'Chưa phân công' }}</dd></div>
                    @if ($ticket->result)
                        <div><dt class="text-[11px] tracking-wider text-muted uppercase">Kết quả</dt><dd class="font-medium">{{ $ticket->result->label() }}</dd></div>
                    @endif
                    @if ($ticket->is_chargeable)
                        <div><dt class="text-[11px] tracking-wider text-muted uppercase">Số tiền</dt><dd class="font-medium">{{ money_vnd($ticket->charge_amount) }}</dd></div>
                    @endif
                    @if ($ticket->service_type === ServiceType::Repair && $ticket->result?->carriesRepairWarranty())
                        <div>
                            <dt class="text-[11px] tracking-wider text-muted uppercase">BH sau sửa chữa</dt>
                            <dd class="font-medium">
                                @if ($ticket->warrantyItems->isNotEmpty())
                                    {{ $ticket->warrantyItems->count() }} hạng mục · <a href="#bao-hanh" class="text-brand hover:underline">xem bên dưới</a>
                                @elseif ($ticket->repair_warranty_months)
                                    {{ $ticket->repair_warranty_months }} tháng · {{ $ticket->returned_date ? 'đến '.vn_date($ticket->repairWarrantyEndsOn()) : 'tính từ ngày trả' }}
                                @else
                                    Không bảo hành
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($ticket->wasConverted())
                        <div><dt class="text-[11px] tracking-wider text-muted uppercase">Chuyển loại</dt><dd class="font-medium">Từ {{ $ticket->originalKind()->label() }} (TTBH từ chối)</dd></div>
                    @endif
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-line bg-panel px-3 py-2 text-sm">
                    <span class="text-muted">Máy {{ $device->device->serial_number }}:</span>
                    <x-repair-warranty :warranty="$device->repairWarranty" />
                    <a href="{{ route('devices.show', $device->device) }}" class="btn btn-secondary btn-sm">Xem lịch sử máy</a>
                </div>

                <div class="section-title">Lỗi khách báo</div>
                <p class="text-sm whitespace-pre-line">{{ $ticket->fault_description }}</p>

                @if ($ticket->scrap_reason)
                    <div class="section-title">Lý do báo phế</div>
                    <p class="text-sm">{{ $ticket->scrap_reason }}</p>
                @endif

                @if ($ticket->note)
                    <div class="section-title">Ghi chú</div>
                    <p class="text-sm whitespace-pre-line">{{ $ticket->note }}</p>
                @endif

                @if ($history->isNotEmpty())
                    <p class="mt-4 text-sm text-amber-800">Máy này đã từng gửi:
                        @foreach ($history as $previous)
                            <a href="{{ route('tickets.show', $previous) }}" class="ticket-no">{{ $previous->ticket_no }}</a> ({{ vn_date($previous->received_date) }})@if (! $loop->last), @endif
                        @endforeach
                    </p>
                @endif
            </section>

            @if ($ticket->claimTicket)
                <section class="card border-amber-300 p-5" id="yeu-cau-bao-hanh">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">Khách yêu cầu bảo hành sửa chữa theo phiếu <a href="{{ route('tickets.show', $ticket->claimTicket) }}" class="ticket-no">{{ $ticket->claimTicket->ticket_no }}</a></h2>
                        @if ($ticket->claim_result)
                            <x-pill :tone="$ticket->claim_result->tone()">{{ $ticket->claim_result->label() }}</x-pill>
                        @else
                            <x-pill tone="amber">Chờ IT kết luận</x-pill>
                        @endif
                    </div>
                    <p class="mb-2 text-xs text-muted">Phiếu gốc trả ngày {{ vn_date($ticket->claimTicket->returned_date) }} · {{ $ticket->claimTicket->result?->label() }}</p>
                    <x-warranty-items :ticket="$ticket->claimTicket" :highlight="$ticket->claim_item_id" />
                    @if ($ticket->claim_note)
                        <p class="mt-3 text-sm"><span class="font-semibold">{{ $ticket->claim_result === \App\Enums\ClaimResult::Rejected ? 'Lý do ngoài phạm vi' : 'Ghi chú' }}:</span> {{ $ticket->claim_note }}</p>
                    @endif
                    @if ($ticket->claimPending() && $ticket->status === TicketStatus::Inspecting)
                        <p class="mt-3 text-sm text-amber-800">Sau khi kiểm tra, chọn "Trong phạm vi BH" hoặc "Ngoài phạm vi BH" ở Bước tiếp theo. Báo giá chỉ mở khi ngoài phạm vi.</p>
                    @endif
                    @if ($ticket->claim_result === \App\Enums\ClaimResult::Covered)
                        <p class="mt-3 text-sm text-muted">Sửa miễn phí, không cộng thêm thời gian bảo hành: hạn vẫn tính theo phiếu {{ $ticket->claimTicket->ticket_no }}.</p>
                    @endif
                </section>
            @endif

            @if ($ticket->shipments->isNotEmpty())
                <section class="card overflow-x-auto">
                    <h2 class="px-5 pt-4 pb-2 font-semibold">{{ $isOnsite ? ($atCustomer ? 'Hãng đến nhà khách bảo hành' : 'Hãng đến Sang Y bảo hành') : 'Các lần gửi TTBH' }}</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th><th>Hãng / TTBH</th><th>Mã hồ sơ hãng</th><th>{{ $isOnsite ? 'Ngày yêu cầu' : 'Ngày gửi' }}</th>
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
                </section>
            @endif

            @if ($ticket->original_service_type !== ServiceType::Repair)
                <section class="card p-5" id="linh-kien">
                    <h2 class="mb-2 font-semibold">Linh kiện hãng / TTBH thay</h2>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Mã linh kiện</th><th>Mô tả</th><th class="text-right">SL</th><th>Lần gửi</th>@if ($partsEditable)<th></th>@endif</tr></thead>
                            <tbody>
                                @forelse ($ticket->replacedParts as $part)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $part->part_code ?? '—' }}</td>
                                        <td>{{ $part->description }}</td>
                                        <td class="text-right">{{ $part->quantity }}</td>
                                        <td>{{ $part->shipment ? '#'.($ticket->shipments->search(fn ($shipment) => $shipment->is($part->shipment)) + 1) : '—' }}</td>
                                        @if ($partsEditable)
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('tickets.replaced-parts.destroy', [$ticket, $part]) }}" data-confirm="Xoá linh kiện này?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-secondary btn-sm">Xoá</button>
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
                        <form method="POST" action="{{ route('tickets.replaced-parts.store', $ticket) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <input class="input w-40 font-mono" name="part_code" placeholder="Mã (VD: 5JKJK)" maxlength="50" aria-label="Mã linh kiện">
                            <input class="input min-w-56 flex-1" name="description" placeholder="Mô tả *" required maxlength="255" aria-label="Mô tả linh kiện">
                            <input class="input w-20" type="number" name="quantity" value="1" min="1" required aria-label="Số lượng">
                            <button class="btn btn-secondary">Thêm linh kiện</button>
                        </form>
                        <x-field-error name="description" bag="parts" />
                    @endif
                </section>
            @endif

            @if ($ticket->service_type === ServiceType::Repair && ($ticket->quoteItems->isNotEmpty() || $quoteEditable))
                <section class="card p-5" id="bao-gia">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">Báo giá</h2>
                        @if ($ticket->quote_status)
                            <x-pill :tone="$ticket->quote_status->tone()">{{ $ticket->quote_status->label() }}</x-pill>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Nội dung</th><th class="text-right">SL</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th>@if ($quoteEditable)<th></th>@endif</tr></thead>
                            <tbody>
                                @forelse ($ticket->quoteItems as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-right">{{ $item->quantity }}</td>
                                        <td class="text-right">{{ money_vnd($item->unit_price) }}</td>
                                        <td class="text-right">{{ money_vnd($item->lineTotal()) }}</td>
                                        @if ($quoteEditable)
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('tickets.quote-items.destroy', [$ticket, $item]) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-secondary btn-sm">Xoá</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted">Chưa có dòng nào.</td></tr>
                                @endforelse
                                @if ($ticket->quoteItems->isNotEmpty())
                                    <tr class="bg-panel font-semibold"><td colspan="3">Tổng cộng</td><td class="text-right">{{ money_vnd($ticket->quoteTotal()) }}</td>@if ($quoteEditable)<td></td>@endif</tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    @if ($quoteEditable)
                        <form method="POST" action="{{ route('tickets.quote-items.store', $ticket) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <input class="input min-w-56 flex-1" name="description" placeholder="Nội dung (linh kiện / công) *" required maxlength="255" aria-label="Nội dung báo giá">
                            <input class="input w-20" type="number" name="quantity" value="1" min="1" required aria-label="Số lượng">
                            <input class="input w-40" name="unit_price" placeholder="Đơn giá *" required inputmode="numeric" aria-label="Đơn giá">
                            <button class="btn btn-secondary">Thêm dòng</button>
                        </form>
                        <x-field-error name="description" bag="quote" />
                        <x-field-error name="unit_price" bag="quote" />
                        <p class="mt-2 text-xs text-muted">Lỗi cần thay / sửa: lập bảng giá rồi bấm "Gửi báo giá cho khách". Giá chỉ báo một lần: khách đồng ý thì sửa, không đồng ý thì không sửa. Lỗi đơn giản chọn "Miễn phí", không sửa được chọn "Báo phế".</p>
                    @endif
                    @if ($ticket->quoted_at)
                        <p class="mt-2 text-xs text-muted">Gửi báo giá {{ $ticket->quoted_at->format('d/m/Y H:i') }}@if ($ticket->quote_decided_at) · khách trả lời {{ $ticket->quote_decided_at->format('d/m/Y H:i') }}@endif</p>
                    @endif
                </section>
            @endif

            @if ($ticket->warrantyItems->isNotEmpty() || $ticket->warranty_exclusions || $ticket->claims->isNotEmpty())
                <section class="card p-5" id="bao-hanh">
                    <h2 class="mb-3 font-semibold">Bảo hành sau sửa chữa <span class="text-sm font-normal text-muted">(tính từ ngày trả máy)</span></h2>
                    <x-warranty-items :ticket="$ticket" />
                    @if ($ticket->claims->isNotEmpty())
                        <div class="mt-3 text-sm">
                            <span class="font-semibold">Khách đã quay lại bảo hành:</span>
                            @foreach ($ticket->claims as $claim)
                                <a href="{{ route('tickets.show', $claim) }}" class="ticket-no">{{ $claim->ticket_no }}</a>
                                ({{ $claim->claim_result?->label() ?? 'đang kiểm tra' }})@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            @if ($ticket->is_chargeable)
                <section class="card p-5" id="erp">
                    <h2 class="mb-3 font-semibold">Chứng từ ERP <span class="text-sm font-normal text-muted">(phiếu có phí)</span></h2>
                    <form method="POST" action="{{ route('tickets.erp.update', $ticket) }}" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf @method('PUT')
                        <div>
                            <label class="label" for="erp_receipt_no">Số chứng từ V223 <span class="label-hint">(nhận hàng)</span></label>
                            <input class="input font-mono" name="erp_receipt_no" id="erp_receipt_no" value="{{ old('erp_receipt_no', $ticket->erp_receipt_no) }}" maxlength="50" @disabled($ticket->isClosed())>
                        </div>
                        <div>
                            <label class="label" for="erp_return_no">Số chứng từ V233 <span class="label-hint">(trả hàng)</span></label>
                            <input class="input font-mono" name="erp_return_no" id="erp_return_no" value="{{ old('erp_return_no', $ticket->erp_return_no) }}" maxlength="50" @disabled($ticket->isClosed())>
                        </div>
                        @unless ($ticket->isClosed())
                            <button class="btn btn-secondary">Lưu số chứng từ</button>
                        @endunless
                    </form>
                    <p class="mt-2 text-xs text-muted">Phải có đủ V223 và V233 thì mới bấm được "Trả khách".</p>
                </section>
            @endif

            @if ($actions !== [])
                <section class="card border-brand p-5" id="buoc-tiep-theo">
                    <h2 class="mb-3 font-semibold">Bước tiếp theo</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($actions as $action)
                            <button type="button" data-action-toggle="{{ $action->value }}" aria-expanded="{{ $openAction === $action->value ? 'true' : 'false' }}"
                                    @class(['btn', 'btn-primary' => $action->style() === 'primary', 'btn-secondary' => $action->style() === 'secondary', 'btn-danger' => $action->style() === 'danger'])>
                                {{ $action->label() }}
                            </button>
                        @endforeach
                    </div>

                    @if ($errors->action->any())
                        <div class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                            @foreach ($errors->action->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    @foreach ($actions as $action)
                        @php
                            $photoStage = $action->photoStage();
                        @endphp
                        <form method="POST" action="{{ route('tickets.actions.store', [$ticket, $action]) }}" data-action-panel="{{ $action->value }}"
                              @if ($photoStage) enctype="multipart/form-data" @endif
                              class="mt-4 rounded-lg border border-brand p-4" @if ($openAction !== $action->value) hidden @endif>
                            @csrf
                            <input type="hidden" name="_action" value="{{ $action->value }}">
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
                                        <label class="label" for="{{ $id }}">{{ $field['label'] }}@if ($field['required']) *@else <span class="label-hint">(tuỳ chọn)</span>@endif</label>
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
                                                <div class="overflow-x-auto rounded-md border border-line" id="{{ $id }}">
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
                                            <p class="mt-1 text-xs text-muted">{{ $field['hint'] }}</p>
                                        @endisset
                                    </div>
                                @endforeach
                                @if ($photoStage)
                                    <div class="sm:col-span-2">
                                        <label class="label" for="{{ $action->value }}-photos">
                                            @if ($action->requiresPhoto())
                                                Ảnh tình trạng máy {{ mb_strtolower($photoStage->label()) }} * <span class="label-hint">(ít nhất 1 ảnh)</span>
                                            @else
                                                Ảnh / chứng từ <span class="label-hint">(tuỳ chọn)</span>
                                            @endif
                                        </label>
                                        <input type="file" name="photos[]" id="{{ $action->value }}-photos" multiple accept="image/*,application/pdf" class="input py-1.5"
                                               data-file-preview="{{ $action->value }}-photos-preview" @required($action->requiresPhoto())>
                                        <p class="mt-1 text-xs text-muted">{{ $photoStage->hint() }}</p>
                                        <div id="{{ $action->value }}-photos-preview" class="mt-2 flex flex-wrap gap-2"></div>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" data-action-toggle="{{ $action->value }}">Bỏ qua</button>
                                <button class="btn btn-sm {{ $action->style() === 'danger' ? 'btn-danger' : 'btn-primary' }}">Xác nhận: {{ $action->label() }}</button>
                            </div>
                        </form>
                    @endforeach
                </section>
            @endif

            <section class="card p-5">
                <h2 class="mb-3 font-semibold">Ảnh và chứng từ <span class="text-sm font-normal text-muted">(theo giai đoạn)</span></h2>
                <x-attachments :ticket="$ticket" />
            </section>
        </div>

        <aside class="space-y-5">
            @unless ($ticket->isClosed())
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="card space-y-3 p-5">
                    @csrf @method('PUT')
                    <h2 class="font-semibold">Phân công & ghi chú</h2>
                    <div>
                        <label class="label" for="technician_id">Nhân viên phụ trách</label>
                        <select class="input" name="technician_id" id="technician_id">
                            <option value="">Chưa phân công</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected($ticket->technician_id === $technician->id)>{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="note">Ghi chú</label>
                        <textarea class="input min-h-20" name="note" id="note" maxlength="2000">{{ $ticket->note }}</textarea>
                    </div>
                    <button class="btn btn-secondary w-full">Lưu</button>
                </form>
            @endunless

            <section class="card p-5">
                <h2 class="mb-3 font-semibold">Lịch sử</h2>
                <ol class="space-y-3">
                    @foreach ($ticket->statusLogs as $log)
                        <li class="grid grid-cols-[12px_1fr] gap-3 text-sm">
                            <span class="mt-1.5 size-2.5 rounded-full bg-brand"></span>
                            <div>
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($log->from_status)
                                        <x-status-pill :status="$log->from_status" /> <span class="text-muted">→</span>
                                    @endif
                                    <x-status-pill :status="$log->to_status" />
                                </div>
                                <div class="mt-0.5 text-xs text-muted">{{ $log->user->name }} · {{ $log->created_at->format('d/m/Y H:i') }}</div>
                                @if ($log->note)
                                    <div class="mt-0.5">{{ $log->note }}</div>
                                @endif
                                @if ($log->attachments->isNotEmpty())
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($log->attachments as $attachment)
                                            <a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" target="_blank" rel="noopener"
                                               class="grid size-12 place-items-center overflow-hidden rounded border border-line bg-white font-mono text-[10px] font-semibold text-muted"
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
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.app>
