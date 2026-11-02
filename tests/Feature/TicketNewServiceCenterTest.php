<?php

namespace Tests\Feature;

use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketNewServiceCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RmaTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->admin()->create();
        $this->ticket = RmaTicket::factory()->ofKind(TicketKind::CarryIn)->status(TicketStatus::Inspecting)->create();
    }

    private function send(array $input)
    {
        return $this->actingAs($this->user)->post(route('tickets.actions.store', [$this->ticket, 'send-to-center']), [
            'sent_date' => today()->toDateString(),
            ...$input,
        ]);
    }

    public function test_a_new_service_center_is_created_together_with_the_send_step(): void
    {
        $this->send(['service_center_id' => 'new', 'new_center_name' => '  TTBH   LG Hải Phòng ', 'new_center_phone' => '0912345678'])
            ->assertSessionHasNoErrors();

        $center = ServiceCenter::where('name', 'TTBH LG Hải Phòng')->sole();
        $this->assertSame('0912345678', $center->phone);
        $this->assertTrue($center->is_active);
        $this->assertSame($center->id, $this->ticket->fresh()->shipments->sole()->service_center_id);
        $this->assertSame(TicketStatus::AtCenter, $this->ticket->fresh()->status);
    }

    public function test_the_new_center_needs_a_name(): void
    {
        $this->send(['service_center_id' => 'new'])->assertSessionHasErrorsIn('action', ['new_center_name']);

        $this->assertSame(0, ServiceCenter::count());
        $this->assertSame(TicketStatus::Inspecting, $this->ticket->fresh()->status);
    }

    public function test_a_center_with_the_same_name_is_reused_and_shown_again(): void
    {
        $existing = ServiceCenter::factory()->create(['name' => 'Tin học Lâm Hiếu', 'is_active' => false]);

        $this->send(['service_center_id' => 'new', 'new_center_name' => 'Tin học Lâm Hiếu'])->assertSessionHasNoErrors();

        $this->assertSame(1, ServiceCenter::count());
        $this->assertTrue($existing->fresh()->is_active);
        $this->assertSame($existing->id, $this->ticket->fresh()->shipments->sole()->service_center_id);
    }

    public function test_an_unknown_center_id_is_still_rejected(): void
    {
        $this->send(['service_center_id' => '9999'])->assertSessionHasErrorsIn('action', ['service_center_id']);
    }
}
