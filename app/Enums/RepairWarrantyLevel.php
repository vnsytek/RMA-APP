<?php

namespace App\Enums;

enum RepairWarrantyLevel: string
{
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Còn BH sau sửa',
            self::Expiring => 'Sắp hết BH sau sửa',
            self::Expired => 'Đã hết BH sau sửa',
            self::None => 'Chưa có BH sửa chữa',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Expiring => 'amber',
            self::Expired => 'red',
            self::None => 'gray',
        };
    }

    public function isValid(): bool
    {
        return $this === self::Active || $this === self::Expiring;
    }
}
