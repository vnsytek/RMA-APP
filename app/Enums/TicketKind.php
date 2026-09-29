<?php

namespace App\Enums;

/**
 * The four ways a ticket is handled, as shown to staff.
 */
enum TicketKind: string
{
    case Onsite = 'onsite';
    case OnsiteSangy = 'onsite_sangy';
    case CarryIn = 'carry_in';
    case Repair = 'repair';

    public static function of(ServiceType $type, ?OnsiteLocation $location): self
    {
        return match ($type) {
            ServiceType::Onsite => $location === OnsiteLocation::Sangy ? self::OnsiteSangy : self::Onsite,
            ServiceType::CarryIn => self::CarryIn,
            ServiceType::Repair => self::Repair,
        };
    }

    public function serviceType(): ServiceType
    {
        return match ($this) {
            self::Onsite, self::OnsiteSangy => ServiceType::Onsite,
            self::CarryIn => ServiceType::CarryIn,
            self::Repair => ServiceType::Repair,
        };
    }

    public function onsiteLocation(): ?OnsiteLocation
    {
        return match ($this) {
            self::Onsite => OnsiteLocation::Customer,
            self::OnsiteSangy => OnsiteLocation::Sangy,
            default => null,
        };
    }

    public function isOnsite(): bool
    {
        return $this === self::Onsite || $this === self::OnsiteSangy;
    }

    public function isWarranty(): bool
    {
        return $this !== self::Repair;
    }

    /**
     * Only the vendor-visits-the-customer kind never takes the device in.
     */
    public function takesDeviceIn(): bool
    {
        return $this !== self::Onsite;
    }

    public function initialStatus(): TicketStatus
    {
        return $this->isOnsite() ? TicketStatus::Scheduled : TicketStatus::Received;
    }

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Hãng BH tại nhà khách',
            self::OnsiteSangy => 'Hãng BH tại Sang Y',
            self::CarryIn => 'Nhận về gửi TTBH',
            self::Repair => 'Sửa chữa',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Onsite => 'BH tại nhà khách',
            self::OnsiteSangy => 'BH tại Sang Y',
            self::CarryIn => 'BH gửi TTBH',
            self::Repair => 'Sửa chữa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Onsite => 'Hãng cử người đến tận nhà khách. Không mang máy về, không in phiếu nhận/trả.',
            self::OnsiteSangy => 'Mang máy về Sang Y, hẹn hãng đến Sang Y bảo hành, xong Sang Y trả máy cho khách.',
            self::CarryIn => 'IT mang máy về, kiểm tra, gửi trung tâm bảo hành nếu cần, rồi trả khách.',
            self::Repair => 'IT kiểm tra lỗi: miễn phí, báo giá, hoặc báo phế. Sửa xong nhập thời gian bảo hành sau sửa.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Onsite => 'violet',
            self::OnsiteSangy => 'fuchsia',
            self::CarryIn => 'blue',
            self::Repair => 'amber',
        };
    }
}
