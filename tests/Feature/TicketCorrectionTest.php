<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_correcting_a_typo_d_serial_logs_the_old_and_new_value_and_keeps_the_same_model(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id]);
        $originalModelId = $ticket->device->product_model_id;
        $wrongSerial = $ticket->device->serial_number;

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => 'CORRECT-SN-001',
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
                'note' => 'Gõ nhầm serial',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('CORRECT-SN-001', $ticket->device->serial_number);
        $this->assertSame($originalModelId, $ticket->device->product_model_id);

        $edit = $ticket->edits()->sole();
        $this->assertSame('Serial / thiết bị', $edit->field);
        $this->assertSame($wrongSerial, $edit->old_value);
        $this->assertSame('CORRECT-SN-001', $edit->new_value);
        $this->assertSame('Gõ nhầm serial', $edit->note);
        $this->assertTrue($edit->user->is($this->user));
    }

    public function test_correcting_fault_description_and_received_date_logs_each_change_separately(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id, 'fault_description' => 'Mo ta cu', 'received_date' => today()->subDays(3)]);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => $ticket->device->serial_number,
                'fault_description' => 'Mo ta dung',
                'received_date' => today()->subDay()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame('Mo ta dung', $ticket->fault_description);
        $this->assertTrue($ticket->received_date->isSameDay(today()->subDay()));
        $this->assertSame(2, $ticket->edits()->count());
    }

    public function test_no_edit_is_logged_when_nothing_actually_changed(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => $ticket->device->serial_number,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $ticket->edits()->count());
    }

    public function test_received_date_cannot_be_moved_after_the_recorded_return_date(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id, 'received_date' => today()->subDays(5), 'returned_date' => today()->subDays(2)]);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => $ticket->device->serial_number,
                'fault_description' => $ticket->fault_description,
                'received_date' => today()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrorsIn('correct', ['received_date']);
    }

    public function test_a_closed_ticket_can_still_be_corrected(): void
    {
        $ticket = RmaTicket::factory()->status(TicketStatus::Returned)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => 'FIXED-AFTER-CLOSE',
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('FIXED-AFTER-CLOSE', $ticket->fresh()->device->serial_number);
    }
}
