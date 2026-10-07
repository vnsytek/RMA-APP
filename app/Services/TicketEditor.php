<?php

namespace App\Services;

use App\Models\Device;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lets staff correct a mistake made at intake (wrong serial, typo'd fault description, wrong date)
 * without cancelling and re-opening the ticket. Every change is logged to rma_ticket_edits so the
 * ticket's timeline still shows what was first recorded and what was corrected, by whom and when.
 */
class TicketEditor
{
    /**
     * @param  array{serial_number: string, fault_description: string, received_date: string}  $data
     */
    public function correct(RmaTicket $ticket, array $data, User $user, ?string $note): void
    {
        DB::transaction(function () use ($ticket, $data, $user, $note): void {
            $this->correctSerial($ticket, $data['serial_number'], $user, $note);

            if ($data['fault_description'] !== $ticket->fault_description) {
                $this->log($ticket, $user, 'Lỗi khách báo', $ticket->fault_description, $data['fault_description'], $note);
                $ticket->fault_description = $data['fault_description'];
            }

            if ($data['received_date'] !== $ticket->received_date->toDateString()) {
                $label = $ticket->kind()->isOnsite() ? 'Ngày yêu cầu hãng' : 'Ngày nhận';
                $this->log($ticket, $user, $label, vn_date($ticket->received_date), vn_date(Carbon::parse($data['received_date'])), $note);
                $ticket->received_date = $data['received_date'];
            }

            $ticket->save();
        });
    }

    private function correctSerial(RmaTicket $ticket, string $serial, User $user, ?string $note): void
    {
        $serial = Str::squish($serial);
        $current = $ticket->device;

        if (strcasecmp($serial, $current->serial_number) === 0) {
            return;
        }

        $device = Device::firstOrCreate([
            'product_model_id' => $current->product_model_id,
            'serial_number' => $serial,
        ]);

        $this->log($ticket, $user, 'Serial / thiết bị', $current->serial_number, $serial, $note);
        $ticket->device_id = $device->id;
    }

    private function log(RmaTicket $ticket, User $user, string $field, ?string $old, ?string $new, ?string $note): void
    {
        $ticket->edits()->create([
            'user_id' => $user->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'note' => $note,
        ]);
    }
}
