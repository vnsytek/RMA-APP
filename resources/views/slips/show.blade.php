<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $ticket->ticket_no }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <style>
        /* A5 landscape: 210 × 148 mm, 7 mm margins. */
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ece9; color: #111; font: 14.5px/1.4 "Times New Roman", Tinos, serif; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 16px; font: 14px system-ui, "Segoe UI", sans-serif; }
        .toolbar a, .toolbar button { border: 1px solid #1f4a3d; background: #fff; color: #1f4a3d; border-radius: 6px; padding: 8px 14px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #1f4a3d; color: #fff; }
        .toolbar p { flex-basis: 100%; margin: 0; text-align: center; color: #5b6a62; font-size: 13px; }
        .paper { background: #fff; width: 210mm; max-width: 100%; min-height: 148mm; margin: 0 auto 32px; padding: 7mm; box-shadow: 0 1px 4px rgb(0 0 0 / .15); }
        .head { display: flex; gap: 12px; align-items: center; border-bottom: 1.5px solid #111; padding-bottom: 6px; }
        .logo { width: 54px; height: 54px; flex: none; object-fit: contain; }
        .company { flex: 1; min-width: 0; }
        .company b { font-size: 15.5px; text-transform: uppercase; }
        .company div { font-size: 13px; }
        .title { text-align: right; }
        .title h1 { font-size: 19px; margin: 0; text-transform: uppercase; white-space: nowrap; }
        .title div { font-size: 14px; }
        .meta { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 2px 14px; margin: 8px 0 5px; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 1px 14px; border-top: 1px dashed #888; padding-top: 4px; }
        .details .wide { grid-column: 1 / -1; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #111; padding: 4px 7px; vertical-align: top; }
        th { text-align: center; font-size: 14px; }
        tr { break-inside: avoid; }
        .c { text-align: center; white-space: nowrap; } .r { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; }
        .note { margin: 6px 0 0; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; text-align: center; margin-top: 12px; break-inside: avoid; }
        .sign b { display: block; text-transform: uppercase; font-size: 14px; }
        .sign i { font-size: 12.5px; }
        .sign .name { margin-top: 40px; font-weight: 700; }
        .foot { font-size: 12.5px; font-style: italic; margin-top: 4px; }
        /* Many repair lines: tighten the table so the slip still fits one A5 page. */
        .dense th, .dense td { padding: 1.5px 7px; font-size: 13px; }
        .dense .sign { margin-top: 6px; }
        .dense .sign .name { margin-top: 26px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { box-shadow: none; margin: 0; width: auto; min-height: 0; padding: 0; }
            @page { size: A5 landscape; margin: 7mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">In / Lưu PDF</button>
        <a href="{{ route('tickets.slips.excel', [$ticket, $type]) }}">Tải Excel</a>
        <a href="{{ route('tickets.show', $ticket) }}">← Quay lại phiếu</a>
        <p>Khổ A5 ngang. Muốn lưu PDF: bấm "In / Lưu PDF" rồi chọn máy in "Lưu thành PDF" (Save as PDF).</p>
    </div>

    <div @class(['paper', 'dense' => $lines->count() > 5])>
        <div class="head">
            <img class="logo" src="{{ asset('images/logo-sangy-light-128.png') }}" alt="{{ $company['short_name'] }}">
            <div class="company">
                <b>{{ $company['name'] }}</b>
                <div>ĐC: {{ $company['address'] }}</div>
                <div>Tel: {{ $company['phone'] ?: '……………' }} · Email: {{ $company['email'] ?: '……………' }}</div>
            </div>
            <div class="title">
                <h1>{{ $title }}</h1>
                <div>Số phiếu: <b>{{ $ticket->ticket_no }}</b> · Ngày {{ vn_date($date) }}</div>
            </div>
        </div>

        <div class="meta">
            <div>Khách hàng: <b>{{ $ticket->customer->name }}</b></div>
            <div>Điện thoại: {{ $ticket->contact_phone ?? $ticket->customer->phone }}</div>
            <div>Hình thức: {{ $ticket->kind()->label() }}</div>
            <div>Người liên hệ: {{ $ticket->contact_name ?: '……………………' }}</div>
            <div>Địa chỉ: {{ $ticket->customer->address ?: '……………………' }}</div>
            <div>Tình trạng BH: {{ $ticket->warranty_status->label() }}</div>
        </div>

        @if ($product)
            <table>
                <thead><tr>@foreach ($product['head'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>
                    <tr>@foreach ($product['row'] as $index => $cell)<td @class(['c' => in_array($index, [0, 2], true)])>{{ $cell }}</td>@endforeach</tr>
                </tbody>
            </table>
        @endif

        @if ($details !== [])
            <div class="details">
                @foreach ($details as [$label, $value, $wide])
                    <div @class(['wide' => $wide])>{{ $label }}: <b>{{ $value }}</b></div>
                @endforeach
            </div>
        @endif

        @if ($lines->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th style="width: 8mm">STT</th>
                        <th>Nội dung sửa chữa</th>
                        <th style="width: 12mm">SL</th>
                        <th style="width: 32mm">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td class="c">{{ $loop->iteration }}</td>
                            <td>{{ $line->description }}</td>
                            <td class="c">{{ $line->quantity }}</td>
                            <td class="r">{{ money_vnd($line->lineTotal()) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total"><td colspan="3" class="r">Tổng cộng</td><td class="r">{{ money_vnd($ticket->charge_amount) }}</td></tr>
                </tbody>
            </table>
        @elseif ($isReturn)
            <p class="note">Chi phí: <b>Miễn phí</b></p>
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
