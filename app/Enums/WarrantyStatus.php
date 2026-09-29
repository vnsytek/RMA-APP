<?php

namespace App\Enums;

/**
 * Chosen by staff when the ticket is opened; the system does not look warranties up.
 */
enum WarrantyStatus: string
{
    case InWarranty = 'in_warranty';
    case OutOfWarranty = 'out_of_warranty';

    public function label(): string
    {
        return match ($this) {
            self::InWarranty => 'Còn bảo hành',
            self::OutOfWarranty => 'Hết bảo hành',
        };
    }

    public function tone(): string
    {
        return $this === self::InWarranty ? 'green' : 'gray';
    }
}
