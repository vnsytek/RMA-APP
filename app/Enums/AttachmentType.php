<?php

namespace App\Enums;

enum AttachmentType: string
{
    case Photo = 'photo';
    case Invoice = 'invoice';
    case WarrantyCard = 'warranty_card';
    case VendorNote = 'vendor_note';
    case CenterReturn = 'center_return';
    case ScrapRecord = 'scrap_record';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Ảnh máy',
            self::Invoice => 'Hoá đơn',
            self::WarrantyCard => 'Phiếu bảo hành',
            self::VendorNote => 'Phiếu của hãng',
            self::CenterReturn => 'Phiếu trả TTBH',
            self::ScrapRecord => 'Biên bản báo phế',
            self::Other => 'Khác',
        };
    }
}
