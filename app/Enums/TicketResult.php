<?php

namespace App\Enums;

enum TicketResult: string
{
    case OnsiteDone = 'onsite_done';
    case WarrantyCenter = 'warranty_center';
    case WarrantyInhouse = 'warranty_inhouse';
    case Free = 'free';
    case Repaired = 'repaired';
    case RepairWarranty = 'repair_warranty';
    case QuoteRejected = 'quote_rejected';
    case Scrap = 'scrap';

    public function label(): string
    {
        return match ($this) {
            self::OnsiteDone => 'Hãng đã bảo hành',
            self::WarrantyCenter => 'Đã bảo hành qua TTBH',
            self::WarrantyInhouse => 'IT xử lý xong (bảo hành)',
            self::Free => 'Lỗi đơn giản – Miễn phí',
            self::Repaired => 'Đã sửa – Có tính phí',
            self::RepairWarranty => 'Bảo hành sửa chữa – Miễn phí',
            self::QuoteRejected => 'Khách không đồng ý báo giá – Không sửa',
            self::Scrap => 'Báo phế – Không sửa được',
        };
    }

    /**
     * Results after which Sang Y gives a repair warranty (when months were entered).
     * A repair done under an earlier warranty keeps that warranty's end date instead.
     */
    public function carriesRepairWarranty(): bool
    {
        return $this === self::Free || $this === self::Repaired;
    }
}
