@php
    use App\Enums\TicketKind;

    $flows = [
        [TicketKind::Onsite, ['Lập phiếu, mở case với hãng', 'Chờ hãng đến nhà khách', 'Hãng xử lý xong'],
            ['Không mang máy về nên không in phiếu nhận / trả.', 'Ghi hãng, mã hồ sơ, ngày hẹn, linh kiện thay, serial máy mới nếu hãng đổi máy.']],
        [TicketKind::OnsiteSangy, ['Nhận máy về Sang Y, hẹn hãng', 'Chờ hãng đến Sang Y', 'Hãng xử lý xong', 'Chờ trả khách', 'Trả khách'],
            ['In phiếu nhận khi nhận máy, phiếu trả khi trả máy.', 'Hãng đổi máy thì ghi serial mới, kể cả đổi sang sản phẩm khác (chọn model khác).']],
        [TicketKind::CarryIn, ['IT nhận máy', 'IT kiểm tra', 'Gửi TTBH', 'Nhận về công ty', 'Trả khách'],
            ['IT tự xử lý được thì bỏ qua TTBH.', 'Nhận về mà vẫn lỗi: gửi TTBH lại, mỗi lần gửi được lưu riêng.', 'TTBH từ chối bảo hành: phiếu chuyển sang Sửa chữa, giữ nguyên số phiếu.']],
        [TicketKind::Repair, ['IT nhận máy', 'IT kiểm tra lỗi', 'Miễn phí / Báo giá / Báo phế', 'Trả khách'],
            ['Báo giá một lần: khách đồng ý thì sửa (có phí, nhập ERP V223 / V233), không đồng ý thì trả máy không sửa.', 'Bấm "Sửa xong" hoặc "Miễn phí" thì nhập thời gian bảo hành sau sửa (tháng). Hạn tính từ ngày trả và in lên phiếu trả.']],
    ];
@endphp

<x-layouts.app title="Quy trình" subtitle="4 hình thức xử lý phiếu RMA">
    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($flows as [$kind, $steps, $notes])
            <section class="card p-5">
                <h2 class="flex flex-wrap items-center gap-2 font-semibold">{{ $kind->label() }} <x-kind-tag :kind="$kind" /></h2>
                <p class="mt-1 text-sm text-muted">{{ $kind->description() }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-sm">
                    @foreach ($steps as $step)
                        <span @class(['rounded-md border px-2.5 py-1', 'border-brand font-semibold text-brand' => $loop->last, 'border-line bg-panel' => ! $loop->last])>{{ $step }}</span>
                        @unless ($loop->last)<span class="text-muted">→</span>@endunless
                    @endforeach
                </div>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-muted">
                    @foreach ($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        <section class="card p-5 xl:col-span-2">
            <h2 class="font-semibold">Tình trạng bảo hành</h2>
            <p class="mt-1 text-sm text-muted">Khi lập phiếu, nhân viên tự chọn "Còn bảo hành" hoặc "Hết bảo hành"; hệ thống không tự tra hạn bảo hành của sản phẩm. Hết bảo hành chỉ lập được phiếu Sửa chữa. Nếu máy đang trong thời gian bảo hành sau sửa của Sang Y, form lập phiếu sẽ nhắc để nhân viên tự quyết định.</p>
        </section>
    </div>
</x-layouts.app>
