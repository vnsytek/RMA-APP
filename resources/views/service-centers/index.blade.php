@php
    $active = $serviceCenters->where('is_active', true);
@endphp

<x-layouts.app title="Hãng / TTBH" subtitle="Nơi bảo hành: hãng đến tận nơi hoặc trung tâm nhận máy. Bấm vào tên để sửa.">
    <div data-filterable>
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <label class="search w-full flex-none sm:w-72"><x-icon name="search" /><input type="search" class="input" placeholder="Tìm tên, hãng nhận, SĐT, địa chỉ…" aria-label="Tìm hãng / TTBH" data-filter-search></label>
            @if ($serviceCenters->count() > $active->count())
                <label class="flex cursor-pointer items-center gap-2 px-1 text-sm text-muted"><input type="checkbox" class="accent-[var(--color-brand)]" data-filter-hidden> Hiện mục đã ẩn ({{ $serviceCenters->count() - $active->count() }})</label>
            @endif
            <span class="text-sm text-muted">{{ $active->count() }} nơi đang dùng @if ($held = $active->sum('pending_count')) · đang giữ <b class="text-ink">{{ $held }}</b> máy @endif</span>
            <button type="button" class="btn btn-primary ml-auto" data-dialog-open="center-new"><x-icon name="plus" />Thêm hãng / TTBH</button>
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Nơi bảo hành</th><th>Liên hệ</th><th class="text-right">Đã gửi / hẹn</th><th class="text-right">Đang giữ máy</th></tr>
                </thead>
                <tbody>
                    @foreach ($serviceCenters as $center)
                        <tr data-row data-active="{{ $center->is_active ? 1 : 0 }}"
                            data-search="{{ mb_strtolower(implode(' ', array_filter([$center->name, $center->brands, $center->phone, preg_replace('/\D/', '', (string) $center->phone), $center->address]))) }}"
                            @class(['text-muted' => ! $center->is_active])>
                            <td class="min-w-56">
                                <button type="button" class="cursor-pointer text-left font-semibold hover:text-brand hover:underline" data-dialog-open="center-{{ $center->id }}">{{ $center->name }}</button>
                                @unless ($center->is_active)<span class="ml-1 text-xs">· Đã ẩn</span>@endunless
                                @if ($center->brands)
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach (preg_split('/\s*,\s*/', $center->brands, -1, PREG_SPLIT_NO_EMPTY) as $brand)
                                            <span class="rounded bg-panel px-1.5 py-0.5 text-[11px] font-medium text-muted ring-1 ring-line ring-inset">{{ $brand }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="min-w-56 text-sm">
                                @if ($center->phone)
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $center->phone) }}" class="font-medium tabular-nums hover:text-brand hover:underline">{{ $center->phone }}</a>
                                @endif
                                @if ($center->address)
                                    <div class="line-clamp-2 text-muted" title="{{ $center->address }}">{{ $center->address }}</div>
                                @endif
                                @if (! $center->phone && ! $center->address)
                                    <span class="text-muted/70">Chưa có</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $center->shipments_count ?: '—' }}</td>
                            <td class="text-right">
                                @if ($center->pending_count)
                                    <span class="inline-flex min-w-7 justify-center rounded-full bg-violet-50 px-2 py-0.5 text-sm font-semibold text-violet-800 ring-1 ring-violet-600/20 ring-inset">{{ $center->pending_count }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr data-filter-empty @if ($active->isNotEmpty()) hidden @endif>
                        <td colspan="4" class="py-10 text-center text-muted">
                            @if ($serviceCenters->isEmpty())
                                Chưa có hãng / TTBH nào. Bấm <b>Thêm hãng / TTBH</b> để bắt đầu.
                            @else
                                Không tìm thấy nơi nào khớp.
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4 text-xs text-muted">Không xoá được, chỉ ẩn (quyền Admin), để phiếu cũ vẫn hiển thị đúng. Nơi đã ẩn không hiện khi lập phiếu hoặc gửi TTBH.</p>

    <dialog id="center-new" class="modal" aria-labelledby="center-new-title" @if ($errors->getBag('serviceCenter')->any()) data-open @endif>
        <form method="POST" action="{{ route('service-centers.store') }}" class="modal-body">
            @csrf
            <div class="modal-head">
                <h2 id="center-new-title">Thêm hãng / TTBH</h2>
                <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
            </div>
            <x-service-center-fields bag="serviceCenter" prefix="center-new" />
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                <button class="btn btn-primary">Thêm</button>
            </div>
        </form>
    </dialog>

    @foreach ($serviceCenters as $center)
        @php
            $bag = 'serviceCenter'.$center->id;
        @endphp
        <dialog id="center-{{ $center->id }}" class="modal" aria-labelledby="center-{{ $center->id }}-title" @if ($errors->getBag($bag)->any()) data-open @endif>
            <div class="modal-body">
                <div class="modal-head">
                    <div>
                        <h2 id="center-{{ $center->id }}-title">Sửa hãng / TTBH</h2>
                        <p class="text-xs text-muted">{{ $center->shipments_count }} lần gửi / hẹn · {{ $center->pending_count }} đang giữ máy</p>
                    </div>
                    <button type="button" class="modal-x" data-dialog-close aria-label="Đóng">×</button>
                </div>

                <form method="POST" action="{{ route('service-centers.update', $center) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <x-service-center-fields :center="$center" :bag="$bag" :prefix="'center-'.$center->id" />
                    <div class="modal-foot">
                        <button type="button" class="btn btn-secondary" data-dialog-close>Huỷ</button>
                        <button class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>

                @can('admin')
                    <form method="POST" action="{{ route('service-centers.toggle', $center) }}" class="modal-section flex flex-wrap items-center justify-between gap-2"
                          @if ($center->is_active) data-confirm="Ẩn {{ $center->name }}? Sẽ không chọn được khi lập phiếu hoặc gửi TTBH nữa." @endif>
                        @csrf @method('PATCH')
                        <div>
                            <h3>{{ $center->is_active ? 'Ẩn hãng / TTBH' : 'Hiện lại' }}</h3>
                            <p class="text-xs text-muted">{{ $center->is_active ? 'Phiếu cũ vẫn giữ tên nơi bảo hành này.' : 'Đang ẩn, không chọn được khi lập phiếu.' }}</p>
                        </div>
                        <button @class(['btn', 'btn-danger' => $center->is_active, 'btn-secondary' => ! $center->is_active])>{{ $center->is_active ? 'Ẩn' : 'Hiện lại' }}</button>
                    </form>
                @endcan
            </div>
        </dialog>
    @endforeach
</x-layouts.app>
