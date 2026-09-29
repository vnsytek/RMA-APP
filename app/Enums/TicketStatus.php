<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Scheduled = 'scheduled';
    case Received = 'received';
    case Inspecting = 'inspecting';
    case AtCenter = 'at_center';
    case Quoted = 'quoted';
    case Repairing = 'repairing';
    case Ready = 'ready';
    case Done = 'done';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Chờ hãng đến',
            self::Received => 'Đã nhận máy',
            self::Inspecting => 'IT kiểm tra',
            self::AtCenter => 'Đang ở TTBH',
            self::Quoted => 'Chờ duyệt báo giá',
            self::Repairing => 'Đang sửa',
            self::Ready => 'Chờ trả khách',
            self::Done => 'Hoàn tất',
            self::Returned => 'Đã trả',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled, self::Received => 'blue',
            self::Inspecting, self::Repairing => 'amber',
            self::AtCenter => 'violet',
            self::Quoted => 'orange',
            self::Ready => 'green',
            self::Done, self::Returned => 'gray',
            self::Cancelled => 'red',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Returned, self::Cancelled], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Returned], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isClosed()));
    }
}
