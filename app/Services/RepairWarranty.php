<?php

namespace App\Services;

use App\Enums\RepairWarrantyLevel;
use App\Models\RmaTicket;
use Carbon\CarbonInterface;

/**
 * Sang Y's warranty on the latest repair of a device.
 */
final readonly class RepairWarranty
{
    public function __construct(
        public RepairWarrantyLevel $level,
        public ?RmaTicket $ticket = null,
        public ?CarbonInterface $endsOn = null,
        public ?int $daysLeft = null,
        public ?int $totalDays = null,
    ) {}

    /**
     * @param  iterable<RmaTicket>  $tickets
     */
    public static function fromTickets(iterable $tickets, ?CarbonInterface $today = null): self
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $latest = null;

        foreach ($tickets as $ticket) {
            $endsOn = $ticket->repairWarrantyEndsOn();

            if ($endsOn !== null && ($latest === null || $endsOn->gt($latest[1]))) {
                $latest = [$ticket, $endsOn];
            }
        }

        if ($latest === null) {
            return new self(RepairWarrantyLevel::None);
        }

        [$ticket, $endsOn] = $latest;
        $daysLeft = (int) $today->diffInDays($endsOn, false);
        $totalDays = max(1, (int) $ticket->returned_date->diffInDays($endsOn));

        $level = match (true) {
            $daysLeft < 0 => RepairWarrantyLevel::Expired,
            $daysLeft <= config('rma.repair_warranty_expiring_days') => RepairWarrantyLevel::Expiring,
            default => RepairWarrantyLevel::Active,
        };

        return new self($level, $ticket, $endsOn, $daysLeft, $totalDays);
    }

    public function label(): string
    {
        return match ($this->level) {
            RepairWarrantyLevel::Active => "Còn {$this->daysLeft} ngày",
            RepairWarrantyLevel::Expiring => "Sắp hết · còn {$this->daysLeft} ngày",
            default => $this->level->label(),
        };
    }

    /**
     * Share of the warranty period still remaining, 0–100.
     */
    public function remainingPercent(): int
    {
        if (! $this->level->isValid()) {
            return 0;
        }

        return max(4, min(100, (int) round($this->daysLeft / $this->totalDays * 100)));
    }
}
