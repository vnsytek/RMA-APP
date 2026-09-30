<x-layouts.app title="Hãng / TTBH" subtitle="Nơi bảo hành: hãng đến tận nơi hoặc trung tâm nhận máy. Bấm vào một dòng để sửa.">
    <x-slot:actions>
        <button type="button" class="btn btn-primary" data-dialog-open="center-new">+ Thêm hãng / TTBH</button>
    </x-slot:actions>

    <div class="card overflow-x-auto">
        <table class="table table-fixed">
            <colgroup>
                <col class="w-[26%]"><col class="w-[18%]"><col class="w-[14%]"><col class="w-[26%]"><col class="w-[8%]"><col class="w-[8%]">
            </colgroup>
            <thead>
                <tr><th>Tên</th><th>Nhận bảo hành</th><th>SĐT</th><th>Địa chỉ</th><th class="text-right">Lần gửi</th><th class="text-right">Đang giữ</th></tr>
            </thead>
            <tbody>
                @forelse ($serviceCenters as $center)
                    <tr data-dialog-open="center-{{ $center->id }}" @class(['cursor-pointer hover:bg-panel', 'opacity-60' => ! $center->is_active])>
                        <td>
                            <div class="truncate font-medium" title="{{ $center->name }}">{{ $center->name }}</div>
                            @unless ($center->is_active)<x-pill>đã ẩn</x-pill>@endunless
                        </td>
                        <td><div class="truncate" title="{{ $center->brands }}">{{ $center->brands ?? '—' }}</div></td>
                        <td class="font-mono text-xs">{{ $center->phone ?? '—' }}</td>
                        <td><div class="line-clamp-2 text-sm" title="{{ $center->address }}">{{ $center->address ?? '—' }}</div></td>
                        <td class="text-right tabular-nums">{{ $center->shipments_count ?: '—' }}</td>
                        <td class="text-right tabular-nums">
                            @if ($center->pending_count)
                                <span class="font-semibold text-violet-700">{{ $center->pending_count }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-muted">Chưa có hãng / TTBH nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-muted">Không xoá, chỉ ẩn (quyền Admin) để phiếu cũ vẫn hiển thị đúng. Hãng / TTBH đã ẩn không hiện khi lập phiếu hoặc gửi TTBH.</p>

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
