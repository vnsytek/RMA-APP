<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Chờ khách duyệt',
            self::Accepted => 'Khách đồng ý – sửa',
            self::Rejected => 'Khách không đồng ý – không sửa',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sent => 'orange',
            self::Accepted => 'green',
            self::Rejected => 'red',
        };
    }
}
