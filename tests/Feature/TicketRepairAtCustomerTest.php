<?php

namespace Tests\Feature;

use App\Enums\TicketAction;
use App\Enums\TicketKind;
use App\Enums\TicketResult;
use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use App\Models\RmaTicket;
use App\Models\User;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketRepairAtCustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->admin()->create();
    }

    private function ticket(WarrantyStatus $warranty = WarrantyStatus::InWarranty): RmaTicket
    {
        return RmaTicket::factory()->ofKind(TicketKind::RepairOnsite)->create(['warranty_status' => $warranty]);
    }

    private function act(RmaTicket $ticket, TicketAction $action, array $input = []): void
    {
        if ($action->requiresPhoto()) {
            $input['photos'] ??= [UploadedFile::fake()->image('may.jpg')];
        }

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket->refresh(), $action]), $input)
            ->assertSessionHasNoErrors();
    }

    public function test_in_warranty_visit_is_inspected_then_closed_as_free_without_a_return_step(): void
    {
        $ticket = $this->ticket();

        $this->act($ticket, TicketAction::Inspect);
        $this->assertSame(TicketStatus::Inspecting, $ticket->refresh()->status);

        $this->act($ticket, TicketAction::MarkFree, ['note' => 'Thay RAM']);
        $ticket->refresh();

        $this->assertSame(TicketStatus::Done, $ticket->status);
        $this->assertSame(TicketResult::Free, $ticket->result);
        $this->assertTrue($ticket->returned_date->isToday());
        $this->assertNotContains(TicketAction::ReturnToCustomer, app(TicketWorkflow::class)->availableActions($ticket));
    }

    public function test_out_of_warranty_visit_is_quoted_accepted_and_finished_on_the_spot(): void
    {
        $ticket = $this->ticket(WarrantyStatus::OutOfWarranty);
        $ticket->quoteItems()->create(['description' => 'Thay nguồn', 'quantity' => 1, 'unit_price' => 500000]);

        $this->act($ticket, TicketAction::Inspect);
        $this->act($ticket, TicketAction::SendQuote);
        $this->act($ticket, TicketAction::AcceptQuote);
        $this->assertSame(TicketStatus::Repairing, $ticket->refresh()->status);

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::FinishRepair]), ['note' => 'Đã thay'])
            ->assertSessionHasErrorsIn('action', ['erp_return_no']);

        $ticket->update(['erp_return_no' => 'V233-1']);
        $this->act($ticket, TicketAction::FinishRepair, ['note' => 'Đã thay']);

        $this->assertSame(TicketStatus::Done, $ticket->refresh()->status);
        $this->assertSame(500000, (int) $ticket->charge_amount);
    }

    public function test_taking_the_device_back_turns_the_ticket_into_a_repair_with_slips(): void
    {
        $ticket = $this->ticket();
        $this->act($ticket, TicketAction::Inspect);

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket->refresh(), TicketAction::TakeBackToCompany]), ['note' => 'Hỏng main'])
            ->assertSessionHasErrorsIn('action', ['photos']);

        $this->act($ticket, TicketAction::TakeBackToCompany, ['note' => 'Hỏng main']);
        $ticket->refresh();

        $this->assertSame(TicketKind::Repair, $ticket->kind());
        $this->assertSame(TicketKind::RepairOnsite, $ticket->originalKind());
        $this->assertSame(TicketStatus::Inspecting, $ticket->status);
        $this->assertTrue($ticket->printsSlips());
    }

    public function test_parts_are_recorded_with_serial_and_source(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->user)
            ->post(route('tickets.replaced-parts.store', $ticket), ['description' => 'RAM 8GB', 'quantity' => 1, 'serial_number' => 'RAM-123', 'source' => 'vendor'])
            ->assertSessionHasNoErrors();

        $part = $ticket->replacedParts()->sole();
        $this->assertSame('RAM-123', $part->serial_number);
        $this->assertSame('vendor', $part->source);

        $this->actingAs($this->user)
            ->post(route('tickets.replaced-parts.store', $ticket), ['description' => 'SSD', 'quantity' => 1, 'source' => 'other'])
            ->assertSessionHasErrorsIn('parts', ['source']);
    }

    public function test_the_on_site_record_prints_with_parts_and_serials_but_receipt_and_return_do_not(): void
    {
        $ticket = $this->ticket();
        $ticket->replacedParts()->create(['description' => 'RAM 8GB', 'quantity' => 1, 'serial_number' => 'RAM-123', 'source' => 'company']);

        $this->actingAs($this->user)->get(route('tickets.slips.show', [$ticket, 'visit']))
            ->assertOk()
            ->assertSee('Biên bản sửa chữa tại chỗ')
            ->assertSee('RAM 8GB (serial RAM-123, công ty mang đi)', false)
            ->assertSee('Khách hàng xác nhận');

        $this->actingAs($this->user)->get(route('tickets.slips.excel', [$ticket, 'visit']))->assertOk();
        $this->actingAs($this->user)->get(route('tickets.slips.show', [$ticket, 'receipt']))->assertNotFound();
    }

    public function test_the_ticket_page_and_lists_show_the_new_kind(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->user)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Biên bản tại chỗ')->assertSee('Chờ đến khách');
        $this->actingAs($this->user)->get(route('tickets.index'))->assertOk();
        $this->actingAs($this->user)->get(route('reports.index'))->assertOk();
    }

    public function test_the_warranty_status_can_be_corrected_either_way_on_this_kind(): void
    {
        $ticket = $this->ticket(WarrantyStatus::InWarranty);

        $this->actingAs($this->user)->put(route('tickets.correct', $ticket), [
            'serial_number' => $ticket->device->serial_number,
            'warranty_status' => WarrantyStatus::OutOfWarranty->value,
            'fault_description' => $ticket->fault_description,
            'received_date' => $ticket->received_date->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(WarrantyStatus::OutOfWarranty, $ticket->fresh()->warranty_status);
    }
}
