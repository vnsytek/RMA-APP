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
    case RepairOnsite = 'repair_onsite';

    public static function of(ServiceType $type, ?OnsiteLocation $location): self
    {
        return match ($type) {
            ServiceType::Onsite => $location === OnsiteLocation::Sangy ? self::OnsiteSangy : self::Onsite,
            ServiceType::CarryIn => self::CarryIn,
            ServiceType::Repair => self::Repair,
            ServiceType::RepairOnsite => self::RepairOnsite,
        };
    }

    public function serviceType(): ServiceType
    {
        return match ($this) {
            self::Onsite, self::OnsiteSangy => ServiceType::Onsite,
            self::CarryIn => ServiceType::CarryIn,
            self::Repair => ServiceType::Repair,
            self::RepairOnsite => ServiceType::RepairOnsite,
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
        return $this !== self::Repair && $this !== self::RepairOnsite;
    }

    /**
     * Sang Y's own technician goes to the customer's site and repairs there; no vendor is involved.
     */
    public function repairsAtCustomer(): bool
    {
        return $this === self::RepairOnsite;
    }

    /**
     * Kinds where the customer may be out of warranty. A warranty ticket is opened for a vendor to handle.
     */
    public function allowsOutOfWarranty(): bool
    {
        return $this === self::Repair || $this === self::RepairOnsite;
    }

    /**
     * The vendor visiting the customer and Sang Y repairing at the customer never take the device in.
     */
    public function takesDeviceIn(): bool
    {
        return $this !== self::Onsite && $this !== self::RepairOnsite;
    }

    public function initialStatus(): TicketStatus
    {
        return match (true) {
            $this->isOnsite() => TicketStatus::Scheduled,
            $this->repairsAtCustomer() => TicketStatus::ToVisit,
            default => TicketStatus::Received,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Hãng BH tại nhà khách',
            self::OnsiteSangy => 'Hãng BH tại Sang Y',
            self::CarryIn => 'Nhận về gửi TTBH',
            self::Repair => 'Sửa chữa',
            self::RepairOnsite => 'Sang Y sửa tại công ty khách',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Onsite => 'BH tại nhà khách',
            self::OnsiteSangy => 'BH tại Sang Y',
            self::CarryIn => 'BH gửi TTBH',
            self::Repair => 'Sửa chữa',
            self::RepairOnsite => 'Sang Y sửa tại khách',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Onsite => 'Hãng cử người đến tận nhà khách. Không mang máy về, không in phiếu nhận/trả.',
            self::OnsiteSangy => 'Mang máy về Sang Y, hẹn hãng đến Sang Y bảo hành, xong Sang Y trả máy cho khách.',
            self::CarryIn => 'IT mang máy về, kiểm tra, gửi trung tâm bảo hành nếu cần, rồi trả khách.',
            self::Repair => 'IT kiểm tra lỗi: miễn phí, báo giá, hoặc báo phế. Sửa xong nhập thời gian bảo hành sau sửa.',
            self::RepairOnsite => 'Nhân viên Sang Y đến công ty khách kiểm tra và sửa tại chỗ, không mang máy về. Còn hay hết bảo hành đều lập được. Sửa không được tại chỗ thì mang về công ty, phiếu chuyển sang Sửa chữa.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Onsite => 'violet',
            self::OnsiteSangy => 'fuchsia',
            self::CarryIn => 'blue',
            self::Repair => 'amber',
            self::RepairOnsite => 'emerald',
        };
    }
}
