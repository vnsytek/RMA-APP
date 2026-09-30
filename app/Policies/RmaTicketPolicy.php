<?php

namespace App\Policies;

use App\Models\RmaTicket;
use App\Models\User;

/**
 * Admins see and manage every ticket. Other staff work on the tickets they opened
 * or that an admin handed to them (for example while a colleague is on leave).
 */
class RmaTicketPolicy
{
    public function view(User $user, RmaTicket $ticket): bool
    {
        return $user->isAdmin() || $ticket->created_by === $user->id || $ticket->technician_id === $user->id;
    }

    /**
     * Workflow steps, quotes, parts, vouchers, photos and notes.
     */
    public function update(User $user, RmaTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /**
     * Choosing who is in charge of a ticket.
     */
    public function assign(User $user, RmaTicket $ticket): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, RmaTicket $ticket): bool
    {
        return $user->isAdmin();
    }
}
