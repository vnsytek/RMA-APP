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

    public function isWarranty(): bool
    {
        return $this !== self::Repair;
    }
}
