<?php

namespace App\Enums;

enum ShipmentOutcome: string
{
    case Pending = 'pending';
    case Repaired = 'repaired';
    case Replaced = 'replaced';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Đang chờ',
            self::Repaired => 'Đã sửa',
            self::Replaced => 'Đổi mới',
            self::Rejected => 'Từ chối BH',
            self::Cancelled => 'Hủy theo phiếu',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'violet',
            self::Repaired, self::Replaced => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Repaired || $this === self::Replaced;
    }
}
