<?php

namespace App\Enums;

/**
 * IT's verdict on a customer's claim under Sang Y's repair warranty.
 */
enum ClaimResult: string
{
    case Covered = 'covered';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Covered => 'Trong phạm vi bảo hành',
            self::Rejected => 'Ngoài phạm vi bảo hành',
        };
    }

    public function tone(): string
    {
        return $this === self::Covered ? 'green' : 'red';
    }
}
