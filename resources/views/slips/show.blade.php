<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $ticket->ticket_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ece9; color: #111; font: 15px/1.45 "Times New Roman", Tinos, serif; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 16px; font: 14px system-ui, "Segoe UI", sans-serif; }
        .toolbar a, .toolbar button { border: 1px solid #1f4a3d; background: #fff; color: #1f4a3d; border-radius: 6px; padding: 8px 14px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #1f4a3d; color: #fff; }
        .toolbar p { flex-basis: 100%; margin: 0; text-align: center; color: #5b6a62; font-size: 13px; }
        .paper { background: #fff; width: 210mm; max-width: 100%; min-height: 148mm; margin: 0 auto 32px; padding: 14mm 16mm; box-shadow: 0 1px 4px rgb(0 0 0 / .15); }
        .head { display: flex; gap: 16px; align-items: center; border-bottom: 1.5px solid #111; padding-bottom: 10px; }
        .logo { width: 64px; height: 64px; border: 2px solid #1f4a3d; border-radius: 50%; display: grid; place-items: center; color: #1f4a3d; font: 700 14px system-ui, sans-serif; letter-spacing: .04em; flex: none; text-align: center; }
        .company b { font-size: 17px; text-transform: uppercase; }
        .company div { font-size: 13px; }
        h1 { text-align: center; font-size: 21px; margin: 18px 0 2px; text-transform: uppercase; }
        .meta-center { text-align: center; font-size: 14px; margin-bottom: 14px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 24px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
        ul.exclusions { margin: 4px 0 0; padding-left: 20px; }
        th, td { border: 1px solid #111; padding: 6px 8px; vertical-align: top; }
        th { text-align: center; }
        .c { text-align: center; } .r { text-align: right; }
        .total td { font-weight: 700; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; text-align: center; margin-top: 26px; }
        .sign b { display: block; text-transform: uppercase; }
        .sign i { font-size: 13px; }
        .sign .name { margin-top: 64px; font-weight: 700; }
        .foot { font-size: 13px; font-style: italic; margin-top: 14px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { box-shadow: none; margin: 0; width: auto; min-height: 0; padding: 0; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">In / Lưu PDF</button>
        <a href="{{ route('tickets.slips.excel', [$ticket, $type]) }}">Tải Excel</a>
        <a href="{{ route('tickets.show', $ticket) }}">← Quay lại phiếu</a>
        <p>Muốn lưu PDF: bấm "In / Lưu PDF" rồi chọn máy in "Lưu thành PDF" (Save as PDF).</p>
    </div>

    <div class="paper">
        <div class="head">
            <div class="logo">{{ $company['short_name'] }}</div>
            <div class="company">
                <b>{{ $company['name'] }}</b>
                <div>ĐC: {{ $company['address'] }}</div>
                <div>Tel: {{ $company['phone'] ?: '……………' }} · Email: {{ $company['email'] ?: '……………' }}</div>
            </div>
        </div>

        <h1>{{ $title }}</h1>
        <div class="meta-center">Số phiếu: <b>{{ $ticket->ticket_no }}</b> · Ngày {{ vn_date($date) }}</div>

        <div class="meta">
            <div>Khách hàng: <b>{{ $ticket->customer->name }}</b></div>
            <div>Điện thoại: {{ $ticket->customer->phone }}</div>
            <div>Người liên hệ: {{ $ticket->customer->contact_name ?: '……………………' }}</div>
            <div>Địa chỉ: {{ $ticket->customer->address ?: '……………………………' }}</div>
            <div>Hình thức: {{ $ticket->kind()->label() }}</div>
            <div>Tình trạng bảo hành: {{ $ticket->warranty_status->label() }}</div>
            @if ($isReturn)
                <div>Ngày nhận: {{ vn_date($ticket->received_date) }}</div>
            @endif
        </div>

        <table>
            <thead><tr>@foreach ($head as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                <tr>@foreach ($row as $index => $cell)<td @class(['c' => in_array($index, [0, 2], true)])>{{ $cell }}</td>@endforeach</tr>
            </tbody>
        </table>

        @if ($swapLine)
            <p>{{ $swapLine }}</p>
        @endif

        @if ($isReturn && $ticket->scrap_reason)
            <p>Lý do báo phế: {{ $ticket->scrap_reason }}</p>
        @endif

        @if ($lines->isNotEmpty())
            <table>
                <thead><tr><th>STT</th><th>Nội dung sửa chữa</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td class="c">{{ $loop->iteration }}</td>
                            <td>{{ $line->description }}</td>
                            <td class="c">{{ $line->quantity }}</td>
                            <td class="r">{{ money_vnd($line->unit_price) }}</td>
                            <td class="r">{{ money_vnd($line->lineTotal()) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total"><td colspan="4" class="r">Tổng cộng</td><td class="r">{{ money_vnd($ticket->charge_amount) }}</td></tr>
                </tbody>
            </table>
        @elseif ($isReturn)
            <p>Chi phí: <b>Miễn phí</b></p>
        @endif

        @if ($warrantyLine)
            <p>{{ $warrantyLine }}</p>
        @endif

        @if ($warrantyRows !== [])
            <table>
                <thead><tr><th>STT</th><th>Hạng mục bảo hành</th><th>Thời gian</th><th>Đến ngày</th></tr></thead>
                <tbody>
                    @foreach ($warrantyRows as $row)
                        <tr>
                            <td class="c">{{ $loop->iteration }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="c">{{ $row['months'] }} tháng</td>
                            <td class="c">{{ vn_date($row['ends_on']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($exclusions !== [])
            <p><b>Không bảo hành:</b></p>
            <ul class="exclusions">
                @foreach ($exclusions as $exclusion)
                    <li>{{ $exclusion }}</li>
                @endforeach
            </ul>
        @endif

        <div class="sign">
            <div><b>Khách hàng</b><i>(Ký, ghi rõ họ tên)</i></div>
            <div><b>{{ $isReturn ? 'Nhân viên trả' : 'Nhân viên nhận' }}</b><i>(Ký, ghi rõ họ tên)</i><div class="name">{{ $staff?->name }}</div></div>
        </div>

        @unless ($isReturn)
            <div class="foot">Quý khách vui lòng giữ phiếu này và mang theo khi nhận lại hàng.</div>
        @endunless
    </div>
</body>
</html>
