<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    |
    | Printed on the header of receipt / return slips and reports.
    |
    */

    'company' => [
        'name' => env('RMA_COMPANY_NAME', 'Công ty TNHH Dịch Vụ và Kỹ Thuật Sang Y'),
        'short_name' => env('RMA_COMPANY_SHORT_NAME', 'SANG Y'),
        'address' => env('RMA_COMPANY_ADDRESS', 'Số 303 Lê Duẩn, Kiến An, Hải Phòng'),
        'phone' => env('RMA_COMPANY_PHONE', ''),
        'email' => env('RMA_COMPANY_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket rules
    |--------------------------------------------------------------------------
    */

    'ticket_type' => 'RMA',

    'max_tickets_per_month' => 9999,

    'max_repair_warranty_months' => 60,

    'repair_warranty_expiring_days' => 15,

    /*
    | Default "không bảo hành" list per device type code, one item per line. New device types
    | with a matching code start with it; admins edit it on the catalog page afterwards.
    */
    'warranty_exclusions' => [
        'LAPTOP' => "Lỗi phần cứng khác ngoài hạng mục đã sửa / thay\nVirus, phần mềm do khách tự cài\nRơi vỡ, vào nước, cháy nổ",
        'PC' => "Lỗi phần cứng khác ngoài hạng mục đã sửa / thay\nVirus, phần mềm do khách tự cài\nHỏng do sét, điện áp không ổn định",
        'PRINTER' => "Mực, băng mực, trống, bao lụa, rulo (vật tư tiêu hao)\nLỗi do giấy ẩm, giấy kém chất lượng\nKẹt giấy do dị vật (ghim, kẹp)",
        'MAY_CHAM_CONG' => "Mất dữ liệu chấm công, cài đặt ca do khách thao tác\nHỏng do sét, điện áp không ổn định\nAdapter nguồn đi kèm",
        'TV' => "Vỡ màn, cấn màn do va đập\nVào nước, côn trùng\nLinh kiện khác ngoài hạng mục đã thay",
        'MONITOR' => "Panel, điểm chết, hở sáng (khi không thay panel)\nVỡ màn, cấn màn\nChân đế, cáp tín hiệu",
        'PSU' => "Hỏng do sét, điện áp không ổn định\nCháy nổ do quá tải",
    ],

    'overdue_days' => 7,

    /*
    | Public tax-code (MST) lookup used to fill a company's registered name and address.
    | Providers are tried in order; both are free and rate-limited, xinvoice allows more with its own keys.
    */
    'tax_lookup' => [
        'providers' => array_filter(explode(',', (string) env('RMA_TAX_LOOKUP_PROVIDERS', 'xinvoice,vietqr'))),
        'xinvoice' => [
            'url' => env('XINVOICE_TAX_URL', 'https://api.xinvoice.vn/gdt-api/tax-payer'),
            'client_id' => env('XINVOICE_CLIENT_ID'),
            'api_key' => env('XINVOICE_API_KEY'),
        ],
        'vietqr' => [
            'url' => env('VIETQR_TAX_URL', 'https://api.vietqr.io/v2/business'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('RMA_ATTACHMENT_DISK', 'local'),
        'max_kb' => 10240,
        'max_files' => 10,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

];
