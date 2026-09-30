<?php

namespace Tests\Feature;

use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $huy;

    private User $tam;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->huy = User::factory()->create(['name' => 'Huy']);
        $this->tam = User::factory()->create(['name' => 'Tâm']);
    }

    public function test_staff_only_see_the_tickets_they_opened_or_are_in_charge_of(): void
    {
        $own = RmaTicket::factory()->create(['created_by' => $this->huy->id, 'technician_id' => $this->huy->id]);
        $handedToHuy = RmaTicket::factory()->create(['created_by' => $this->tam->id, 'technician_id' => $this->huy->id]);
        $tams = RmaTicket::factory()->create(['created_by' => $this->tam->id, 'technician_id' => $this->tam->id]);

        $this->actingAs($this->huy)->get(route('tickets.index'))
            ->assertOk()
            ->assertSeeText($own->ticket_no)
            ->assertSeeText($handedToHuy->ticket_no)
            ->assertDontSeeText($tams->ticket_no);

        $this->actingAs($this->huy)->get(route('tickets.show', $tams))->assertForbidden();
        $this->actingAs($this->huy)->get(route('tickets.slips.show', [$tams, 'receipt']))->assertForbidden();
        $this->actingAs($this->huy)->post(route('tickets.actions.store', [$tams, 'inspect']))->assertForbidden();
        $this->actingAs($this->huy)->get(route('devices.show', $tams->device))->assertOk()->assertDontSee(route('tickets.show', $tams));
        $this->actingAs($this->huy)->get(route('customers.show', $tams->customer))->assertOk()->assertDontSeeText($tams->ticket_no);

        $this->actingAs($this->admin)->get(route('tickets.index'))
            ->assertSeeText($own->ticket_no)
            ->assertSeeText($tams->ticket_no);
        $this->actingAs($this->admin)->get(route('tickets.index', ['technician' => $this->tam->id]))
            ->assertSeeText($tams->ticket_no)
            ->assertDontSeeText($own->ticket_no);
    }

    public function test_staff_are_always_in_charge_of_the_tickets_they_open(): void
    {
        $model = ProductModel::factory()->create();

        $this->actingAs($this->huy)->post(route('tickets.store'), [
            'warranty_status' => WarrantyStatus::OutOfWarranty->value, 'kind' => TicketKind::Repair->value, 'serial_number' => 'SN-1',
            'device_type_id' => $model->device_type_id, 'brand_id' => $model->brand_id, 'product_model_id' => $model->id,
            'customer_id' => 'new', 'customer_name' => 'Khách A', 'contact_name' => 'Chị Nga', 'contact_phone' => '0900000000',
            'fault_description' => 'Không lên nguồn', 'received_date' => today()->toDateString(),
            'technician_id' => $this->tam->id, 'photos' => [UploadedFile::fake()->image('may.jpg')],
        ])->assertSessionHasNoErrors();

        $ticket = RmaTicket::sole();
        $this->assertTrue($ticket->technician->is($this->huy));
        $this->assertTrue($ticket->creator->is($this->huy));
    }

    public function test_only_admins_reassign_and_the_new_person_in_charge_takes_over(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->huy->id, 'technician_id' => $this->huy->id]);

        $this->actingAs($this->huy)->put(route('tickets.update', $ticket), ['technician_id' => $this->tam->id, 'note' => 'Chờ linh kiện']);
        $ticket->refresh();
        $this->assertTrue($ticket->technician->is($this->huy));
        $this->assertSame('Chờ linh kiện', $ticket->note);

        $this->actingAs($this->tam)->get(route('tickets.show', $ticket))->assertForbidden();

        $this->actingAs($this->admin)->put(route('tickets.update', $ticket), ['technician_id' => $this->tam->id])->assertSessionHasNoErrors();
        $this->assertTrue($ticket->refresh()->technician->is($this->tam));

        $this->actingAs($this->tam)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($this->huy)->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_a_colleague_given_a_ticket_waiting_for_return_hands_the_device_back(): void
    {
        $ticket = RmaTicket::factory()->status(TicketStatus::Ready)->create(['created_by' => $this->huy->id, 'technician_id' => $this->huy->id]);

        $this->actingAs($this->admin)->post(route('users.handover', $this->huy), ['to_user_id' => $this->tam->id]);

        $this->actingAs($this->tam)->get(route('tickets.show', $ticket))->assertOk()->assertSeeText('Trả khách');
        $this->actingAs($this->tam)->get(route('tickets.slips.show', [$ticket, 'return']))->assertOk()->assertSeeText('Tâm');
        $this->actingAs($this->tam)->post(route('tickets.actions.store', [$ticket, 'return-to-customer']), [
            'returned_date' => today()->toDateString(),
            'photos' => [UploadedFile::fake()->image('tra-khach.jpg')],
        ])->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Returned, $ticket->status);
        $this->assertTrue($ticket->statusLogs()->latest('id')->first()->user->is($this->tam));
        $this->actingAs($this->tam)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($this->huy)->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_admin_hands_all_open_tickets_of_someone_on_leave_to_a_colleague(): void
    {
        $open = RmaTicket::factory()->count(2)->create(['created_by' => $this->huy->id, 'technician_id' => $this->huy->id]);
        $closed = RmaTicket::factory()->status(TicketStatus::Returned)->create(['created_by' => $this->huy->id, 'technician_id' => $this->huy->id]);

        $this->actingAs($this->huy)->post(route('users.handover', $this->huy), ['to_user_id' => $this->tam->id])->assertForbidden();

        $this->actingAs($this->admin)->post(route('users.handover', $this->huy), ['to_user_id' => $this->tam->id])
            ->assertSessionHas('status', 'Đã chuyển 2 phiếu đang mở của Huy sang Tâm.');

        $this->assertSame([$this->tam->id, $this->tam->id], $open->map(fn (RmaTicket $ticket) => $ticket->refresh()->technician_id)->all());
        $this->assertSame($this->huy->id, $closed->refresh()->technician_id);
    }

    public function test_only_admins_delete_tickets_and_see_reports(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->huy->id, 'technician_id' => $this->huy->id]);

        $this->actingAs($this->huy)->delete(route('tickets.destroy', $ticket))->assertForbidden();
        $this->actingAs($this->huy)->get(route('reports.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('reports.index'))->assertOk();
        $this->actingAs($this->admin)->delete(route('tickets.destroy', $ticket))->assertRedirect(route('tickets.index'));
        $this->assertSoftDeleted($ticket);
    }
}
