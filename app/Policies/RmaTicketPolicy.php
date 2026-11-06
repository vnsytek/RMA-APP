<?php

namespace App\Policies;

use App\Models\RmaTicket;
use App\Models\User;

/**
 * Admins see and manage every ticket. Other staff work on the tickets they opened
 * or that an admin handed to them (for example while a colleague is on leave).
 * Staff who already did a step on a ticket (received it, inspected it…) can still read it
 * after it was handed to someone else, so the whole chain stays visible to them.
 */
class RmaTicketPolicy
{
    public function view(User $user, RmaTicket $ticket): bool
    {
        return $this->update($user, $ticket) || $this->workedOn($user, $ticket);
    }

    /**
     * Workflow steps, quotes, parts, vouchers, photos and notes.
     */
    public function update(User $user, RmaTicket $ticket): bool
    {
        return $user->isAdmin() || $ticket->created_by === $user->id || $ticket->technician_id === $user->id;
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

    private function workedOn(User $user, RmaTicket $ticket): bool
    {
        return $ticket->relationLoaded('statusLogs')
            ? $ticket->statusLogs->contains('user_id', $user->id)
            : $ticket->statusLogs()->where('user_id', $user->id)->exists();
    }
}
