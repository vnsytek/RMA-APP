<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use App\Models\ProductModel;
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
                'warranty_status' => $ticket->warranty_status->value,
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
                'warranty_status' => $ticket->warranty_status->value,
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
                'warranty_status' => $ticket->warranty_status->value,
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
                'warranty_status' => $ticket->warranty_status->value,
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
                'warranty_status' => $ticket->warranty_status->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('FIXED-AFTER-CLOSE', $ticket->fresh()->device->serial_number);
    }

    public function test_warranty_status_can_be_corrected_to_in_warranty_and_is_logged(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id, 'warranty_status' => WarrantyStatus::OutOfWarranty]);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => $ticket->device->serial_number,
                'warranty_status' => WarrantyStatus::InWarranty->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(WarrantyStatus::InWarranty, $ticket->fresh()->warranty_status);

        $edit = $ticket->edits()->sole();
        $this->assertSame('Tình trạng bảo hành', $edit->field);
        $this->assertSame('Hết bảo hành', $edit->old_value);
        $this->assertSame('Còn bảo hành', $edit->new_value);
    }

    public function test_only_a_repair_ticket_can_be_corrected_to_out_of_warranty(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id, 'warranty_status' => WarrantyStatus::InWarranty, 'service_type' => 'onsite', 'original_service_type' => 'onsite', 'onsite_location' => 'sangy']);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'serial_number' => $ticket->device->serial_number,
                'warranty_status' => WarrantyStatus::OutOfWarranty->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasErrorsIn('correct', ['warranty_status']);

        $this->assertSame(WarrantyStatus::InWarranty, $ticket->fresh()->warranty_status);
    }

    public function test_the_device_model_can_be_corrected_and_is_logged(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id]);
        $other = ProductModel::factory()->create();
        $oldName = $ticket->device->displayName();

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'product_model_id' => $other->id,
                'serial_number' => $ticket->device->serial_number,
                'warranty_status' => $ticket->warranty_status->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame($other->id, $ticket->device->product_model_id);

        $edit = $ticket->edits()->sole();
        $this->assertSame('Thiết bị', $edit->field);
        $this->assertStringStartsWith($oldName, $edit->old_value);
        $this->assertStringStartsWith($other->fresh()->load('brand')->displayName(), $edit->new_value);
    }

    public function test_a_new_model_can_be_created_from_the_correction_form(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id]);
        $model = $ticket->device->productModel;

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'product_model_id' => 'new',
                'device_type_id' => $model->device_type_id,
                'brand_id' => $model->brand_id,
                'new_model_code' => 'NEW-MODEL-9',
                'serial_number' => $ticket->device->serial_number,
                'warranty_status' => $ticket->warranty_status->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('NEW-MODEL-9', $ticket->fresh()->device->productModel->code);

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'product_model_id' => 'new',
                'serial_number' => 'X',
                'warranty_status' => $ticket->warranty_status->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasErrorsIn('correct', ['device_type_id', 'brand_id', 'new_model_code']);
    }

    public function test_a_model_outside_the_chosen_type_or_brand_is_rejected(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id]);
        $other = ProductModel::factory()->create();

        $this->actingAs($this->user)
            ->put(route('tickets.correct', $ticket), [
                'device_type_id' => $ticket->device->productModel->device_type_id,
                'brand_id' => $ticket->device->productModel->brand_id,
                'product_model_id' => $other->id,
                'serial_number' => $ticket->device->serial_number,
                'warranty_status' => $ticket->warranty_status->value,
                'fault_description' => $ticket->fault_description,
                'received_date' => $ticket->received_date->toDateString(),
            ])
            ->assertSessionHasErrorsIn('correct', ['product_model_id']);

        $this->assertSame(0, $ticket->edits()->count());
    }

    public function test_the_ticket_page_has_the_correction_dialog_with_type_brand_and_model(): void
    {
        $ticket = RmaTicket::factory()->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('id="correct-dialog"', false)
            ->assertSee('data-correct-type', false)
            ->assertSee('data-correct-brand', false)
            ->assertSee('data-correct-model', false);
    }
}
