<?php

namespace App\Enums;

enum PartMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Nhập kho',
            self::Out => 'Xuất kho',
            self::Return => 'Hoàn trả',
            self::Adjustment => 'Điều chỉnh kiểm kê',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::In => 'green',
            self::Out => 'orange',
            self::Return => 'blue',
            self::Adjustment => 'violet',
        };
    }
}
