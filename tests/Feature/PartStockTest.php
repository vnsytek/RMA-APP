<?php

namespace Tests\Feature;

use App\Enums\TicketAction;
use App\Enums\TicketStatus;
use App\Models\Part;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartStockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_adding_a_quote_line_from_the_warehouse_issues_stock_immediately(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 5]);
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create(['created_by' => $this->user->id, 'technician_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('tickets.quote-items.store', $ticket), ['part_id' => $part->id, 'description' => $part->name, 'quantity' => 2, 'unit_price' => '100000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $part->refresh()->quantity_on_hand);
        $this->assertSame('out', $part->movements()->latest()->first()->type->value);
    }

    public function test_cannot_issue_more_than_the_warehouse_has(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 1]);
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create(['created_by' => $this->user->id, 'technician_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('tickets.quote-items.store', $ticket), ['part_id' => $part->id, 'description' => $part->name, 'quantity' => 2, 'unit_price' => '100000'])
            ->assertSessionHasErrorsIn('quote', ['quantity']);

        $this->assertSame(1, $part->refresh()->quantity_on_hand);
        $this->assertSame(0, $ticket->quoteItems()->count());
    }

    public function test_deleting_a_warehouse_quote_line_restores_stock(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 5]);
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create(['created_by' => $this->user->id, 'technician_id' => $this->user->id]);
        $item = $ticket->quoteItems()->create(['part_id' => $part->id, 'description' => $part->name, 'quantity' => 2, 'unit_price' => 100000]);
        $part->decrement('quantity_on_hand', 2);

        $this->actingAs($this->user)
            ->delete(route('tickets.quote-items.destroy', [$ticket, $item]))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $part->refresh()->quantity_on_hand);
        $this->assertSame('return', $part->movements()->latest()->first()->type->value);
    }

    public function test_rejecting_the_quote_restores_warehouse_stock_without_deleting_the_lines(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 5]);
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create(['created_by' => $this->user->id, 'technician_id' => $this->user->id]);
        $ticket->quoteItems()->create(['part_id' => $part->id, 'description' => $part->name, 'quantity' => 2, 'unit_price' => 100000]);
        $part->decrement('quantity_on_hand', 2);

        $this->act($ticket, TicketAction::SendQuote);
        $this->act($ticket, TicketAction::RejectQuote);

        $this->assertSame(5, $part->refresh()->quantity_on_hand);
        $this->assertSame(1, $ticket->quoteItems()->count(), 'the quoted line stays for history');
    }

    public function test_scrap_restores_warehouse_stock_and_clears_the_quote(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 5]);
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create(['created_by' => $this->user->id, 'technician_id' => $this->user->id]);
        $ticket->quoteItems()->create(['part_id' => $part->id, 'description' => $part->name, 'quantity' => 2, 'unit_price' => 100000]);
        $part->decrement('quantity_on_hand', 2);

        $this->act($ticket, TicketAction::Scrap, ['note' => 'Hỏng mainboard']);

        $this->assertSame(5, $part->refresh()->quantity_on_hand);
        $this->assertSame(0, $ticket->quoteItems()->count());
    }

    public function test_cancelling_the_ticket_restores_warehouse_stock(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 5]);
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create(['created_by' => $this->user->id, 'technician_id' => $this->user->id]);
        $ticket->quoteItems()->create(['part_id' => $part->id, 'description' => $part->name, 'quantity' => 2, 'unit_price' => 100000]);
        $part->decrement('quantity_on_hand', 2);

        $this->act($ticket, TicketAction::Cancel, ['note' => 'Khách đổi ý']);

        $this->assertSame(5, $part->refresh()->quantity_on_hand);
    }

    public function test_only_an_admin_can_adjust_stock_after_a_stocktake(): void
    {
        $part = Part::factory()->create(['quantity_on_hand' => 10]);

        $this->actingAs($this->user)
            ->post(route('parts.adjust', $part), ['quantity_on_hand' => 8, 'note' => 'Kiểm kê thiếu 2'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('parts.adjust', $part), ['quantity_on_hand' => 8, 'note' => 'Kiểm kê thiếu 2'])
            ->assertSessionHasNoErrors();

        $part->refresh();
        $this->assertSame(8, $part->quantity_on_hand);
        $movement = $part->movements()->latest()->first();
        $this->assertSame('adjustment', $movement->type->value);
        $this->assertSame(-2, $movement->quantity);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function act(RmaTicket $ticket, TicketAction $action, array $input = []): void
    {
        if ($action->requiresPhoto()) {
            $input['photos'] ??= [UploadedFile::fake()->image('tinh-trang-may.jpg')];
        }

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket->refresh(), $action]), $input)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tickets.show', $ticket));
    }
}
