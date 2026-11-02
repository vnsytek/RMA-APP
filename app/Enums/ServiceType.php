<?php

namespace App\Enums;

/**
 * How the ticket is handled, as stored in the database. Onsite tickets additionally
 * record where the vendor comes ({@see OnsiteLocation}); {@see TicketKind} combines both.
 */
enum ServiceType: string
{
    case Onsite = 'onsite';
    case CarryIn = 'carry_in';
    case Repair = 'repair';
    case RepairOnsite = 'repair_onsite';

    /**
     * Sang Y's own repair, whether the device is brought in or Sang Y goes to the customer.
     */
    public function isSangyRepair(): bool
    {
        return $this === self::Repair || $this === self::RepairOnsite;
    }
}
