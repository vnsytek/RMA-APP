<?php

namespace Tests\Feature;

use App\Enums\AttachmentStage;
use App\Enums\AttachmentType;
use App\Enums\ClaimResult;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Enums\ShipmentOutcome;
use App\Enums\TicketAction;
use App\Enums\TicketKind;
use App\Enums\TicketResult;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Device;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use App\Services\DeviceTracker;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->admin()->create();
    }

    public function test_carry_in_ticket_goes_to_the_service_center_and_comes_back_as_another_product(): void
    {
        $ticket = RmaTicket::factory()->ofKind(TicketKind::CarryIn)->create();
        $center = ServiceCenter::factory()->create();
        $otherModel = ProductModel::factory()->create();

        $this->act($ticket, TicketAction::Inspect);
        $this->act($ticket, TicketAction::SendToCenter, ['service_center_id' => $center->id, 'sent_date' => today()->toDateString(), 'vendor_case_no' => 'CASE-1']);
        $this->act($ticket, TicketAction::ReceiveFromCenter, [
            'back_date' => today()->toDateString(), 'center_return_no' => 'LH-01', 'new_serial' => 'NEW-SN', 'new_model_id' => $otherModel->id,
        ]);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Ready, $ticket->status);
        $this->assertSame(TicketResult::WarrantyCenter, $ticket->result);
        $this->assertSame('NEW-SN', $ticket->returnedDevice->serial_number);
        $this->assertTrue($ticket->returnedDevice->productModel->is($otherModel));
        $this->assertTrue($ticket->returnedDevice->replacedFrom->is($ticket->device));
        $this->assertTrue($ticket->isSwappedToOtherProduct());
        $this->assertSame(ShipmentOutcome::Replaced, $ticket->shipments->sole()->outcome);

        $this->actingAs($this->user)->get(route('tickets.slips.show', [$ticket, 'return']))
            ->assertOk()
            ->assertSeeText('hãng đã đổi sang sản phẩm khác')
            ->assertSeeText('NEW-SN');

        $this->act($ticket, TicketAction::ReturnToCustomer, ['returned_date' => today()->toDateString()]);
        $this->assertSame(TicketStatus::Returned, $ticket->refresh()->status);
    }

    public function test_new_serial_is_required_when_a_replacement_model_is_chosen(): void
    {
        $ticket = RmaTicket::factory()->ofKind(TicketKind::Onsite)->create();
        $ticket->shipments()->create(['service_center_id' => ServiceCenter::factory()->create()->id, 'sent_date' => today(), 'outcome' => ShipmentOutcome::Pending, 'created_by' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::VendorDoneAtCustomer]), ['done_date' => today()->toDateString(), 'new_model_id' => ProductModel::factory()->create()->id])
            ->assertSessionHasErrorsIn('action', 'new_serial');

        $this->assertSame(TicketStatus::Scheduled, $ticket->refresh()->status);
    }

    public function test_vendor_visit_at_the_customer_closes_the_ticket_while_a_visit_at_sang_y_waits_for_return(): void
    {
        $center = ServiceCenter::factory()->create();
        $atCustomer = RmaTicket::factory()->ofKind(TicketKind::Onsite)->create();
        $atSangy = RmaTicket::factory()->ofKind(TicketKind::OnsiteSangy)->create();

        foreach ([$atCustomer, $atSangy] as $ticket) {
            $ticket->shipments()->create(['service_center_id' => $center->id, 'sent_date' => today(), 'outcome' => ShipmentOutcome::Pending, 'created_by' => $this->user->id]);
        }

        $this->act($atCustomer, TicketAction::VendorDoneAtCustomer, ['done_date' => today()->toDateString()]);
        $this->act($atSangy, TicketAction::VendorDoneAtSangy, ['done_date' => today()->toDateString()]);

        $this->assertSame(TicketStatus::Done, $atCustomer->refresh()->status);
        $this->assertNotNull($atCustomer->returned_date);
        $this->assertSame(TicketStatus::Ready, $atSangy->refresh()->status);
        $this->assertNull($atSangy->returned_date);

        $this->act($atSangy, TicketAction::ReturnToCustomer, ['returned_date' => today()->toDateString()]);
        $this->assertSame(TicketStatus::Returned, $atSangy->refresh()->status);
    }

    public function test_returning_the_device_requires_a_photo_of_its_condition(): void
    {
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create();
        $this->act($ticket, TicketAction::MarkFree);

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::ReturnToCustomer]), [
                'returned_date' => today()->toDateString(),
                'photos' => [UploadedFile::fake()->create('phieu-tra.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionHasErrorsIn('action', ['photos' => 'Cần chụp ít nhất 1 ảnh tình trạng máy lúc trả máy.']);

        $this->assertSame(TicketStatus::Ready, $ticket->refresh()->status);

        $this->act($ticket, TicketAction::ReturnToCustomer, [
            'returned_date' => today()->toDateString(),
            'photos' => [UploadedFile::fake()->image('giao-khach.jpg'), UploadedFile::fake()->create('phieu-tra.pdf', 50, 'application/pdf')],
        ]);

        $log = $ticket->refresh()->statusLogs->last();
        $this->assertSame(TicketStatus::Returned, $log->to_status);
        $this->assertSame([AttachmentType::Photo, AttachmentType::Other], $log->attachments->pluck('doc_type')->all());
        $this->assertTrue($log->attachments->every(fn (Attachment $attachment) => $attachment->stage === AttachmentStage::Return));
    }

    public function test_photos_at_other_steps_are_optional_and_filed_under_the_step(): void
    {
        $ticket = RmaTicket::factory()->ofKind(TicketKind::CarryIn)->status(TicketStatus::Inspecting)->create();

        $this->act($ticket, TicketAction::SendToCenter, ['service_center_id' => ServiceCenter::factory()->create()->id, 'sent_date' => today()->toDateString()]);
        $this->assertSame(0, $ticket->attachments()->count());

        $this->act($ticket, TicketAction::ReceiveFromCenter, [
            'back_date' => today()->toDateString(),
            'photos' => [UploadedFile::fake()->image('nhan-ve.jpg'), UploadedFile::fake()->create('phieu-tra-ttbh.pdf', 40, 'application/pdf')],
        ]);

        $log = $ticket->refresh()->statusLogs->last();
        $this->assertSame(TicketStatus::Ready, $log->to_status);
        $this->assertSame([AttachmentType::Photo, AttachmentType::CenterReturn], $log->attachments->pluck('doc_type')->all());
        $this->assertTrue($log->attachments->every(fn (Attachment $attachment) => $attachment->stage === AttachmentStage::BackFromCenter));

        $this->actingAs($this->user)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSeeText('Lúc nhận về từ TTBH')
            ->assertSeeText('Phiếu chưa có ảnh tình trạng máy');
    }

    public function test_service_center_rejection_turns_the_ticket_into_a_repair(): void
    {
        $ticket = RmaTicket::factory()->ofKind(TicketKind::CarryIn)->status(TicketStatus::Inspecting)->create();
        $this->act($ticket, TicketAction::SendToCenter, ['service_center_id' => ServiceCenter::factory()->create()->id, 'sent_date' => today()->toDateString()]);

        $this->act($ticket, TicketAction::CenterRejected, ['back_date' => today()->toDateString(), 'note' => 'Máy vào nước']);

        $ticket->refresh();
        $this->assertSame(ServiceType::Repair, $ticket->service_type);
        $this->assertSame(ServiceType::CarryIn, $ticket->original_service_type);
        $this->assertSame(TicketStatus::Inspecting, $ticket->status);
        $this->assertSame(ShipmentOutcome::Rejected, $ticket->shipments->sole()->outcome);
    }

    public function test_paid_repair_needs_a_quote_and_both_erp_vouchers_and_keeps_the_entered_warranty(): void
    {
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create();

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::SendQuote]))
            ->assertSessionHasErrorsIn('action', ['quote' => 'Thêm ít nhất một dòng vào bảng báo giá trước khi gửi.']);

        $this->actingAs($this->user)->post(route('tickets.quote-items.store', $ticket), ['description' => 'Panel', 'quantity' => 2, 'unit_price' => '1.500.000']);
        $this->act($ticket, TicketAction::SendQuote);

        $this->actingAs($this->user)
            ->post(route('tickets.quote-items.store', $ticket), ['description' => 'Công', 'quantity' => 1, 'unit_price' => '100000'])
            ->assertForbidden();

        $this->act($ticket, TicketAction::AcceptQuote);
        $panel = $ticket->quoteItems()->sole();
        $this->act($ticket, TicketAction::FinishRepair, [
            'warranty_items' => [
                ['quote_item_id' => $panel->id, 'description' => 'Panel', 'months' => 6],
                ['description' => 'Cài lại phần mềm', 'months' => 1],
                ['description' => 'Dòng bỏ trống số tháng thì không bảo hành'],
            ],
            'warranty_exclusions' => "Vỡ màn\n\n  Vào nước  ",
        ]);

        $ticket->refresh();
        $this->assertSame(QuoteStatus::Accepted, $ticket->quote_status);
        $this->assertTrue($ticket->is_chargeable);
        $this->assertSame(3000000, $ticket->charge_amount);
        $this->assertSame(6, $ticket->repair_warranty_months);
        $this->assertSame([['Panel', 6, $panel->id], ['Cài lại phần mềm', 1, null]], $ticket->warrantyItems->map(fn ($item) => [$item->description, $item->months, $item->rma_quote_item_id])->all());
        $this->assertSame(['Vỡ màn', 'Vào nước'], $ticket->warrantyExclusionList());

        $returnedOn = today()->toDateString();
        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::ReturnToCustomer]), ['returned_date' => $returnedOn, 'photos' => [UploadedFile::fake()->image('tra.jpg')]])
            ->assertSessionHasErrorsIn('action', ['erp_receipt_no', 'erp_return_no']);

        $this->actingAs($this->user)->put(route('tickets.erp.update', $ticket), ['erp_receipt_no' => 'V223-1', 'erp_return_no' => 'V233-1']);
        $this->act($ticket, TicketAction::ReturnToCustomer, ['returned_date' => $returnedOn]);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Returned, $ticket->status);
        $this->assertTrue($ticket->repairWarrantyEndsOn()->isSameDay(today()->addMonthsNoOverflow(6)));
        $this->assertTrue($ticket->warrantyItems->last()->endsOn()->isSameDay(today()->addMonthsNoOverflow(1)));

        $this->actingAs($this->user)->get(route('tickets.slips.show', [$ticket, 'return']))
            ->assertOk()
            ->assertSeeText('Panel')
            ->assertDontSeeText('Không bảo hành')
            ->assertDontSeeText('Kết quả');
    }

    public function test_free_repair_without_months_has_no_warranty(): void
    {
        $ticket = RmaTicket::factory()->status(TicketStatus::Inspecting)->create();

        $this->act($ticket, TicketAction::MarkFree, ['note' => 'Vệ sinh']);
        $this->act($ticket, TicketAction::ReturnToCustomer, ['returned_date' => today()->toDateString()]);

        $ticket->refresh();
        $this->assertSame(TicketResult::Free, $ticket->result);
        $this->assertFalse($ticket->hasRepairWarranty());
        $this->assertNull($ticket->repairWarrantyEndsOn());
    }

    public function test_warranty_months_must_be_within_the_allowed_range(): void
    {
        $ticket = RmaTicket::factory()->status(TicketStatus::Repairing)->create();

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::FinishRepair]), ['warranty_items' => [['description' => 'Board nguồn', 'months' => 61], ['months' => 3]]])
            ->assertSessionHasErrorsIn('action', ['warranty_items.0.months', 'warranty_items.1.description']);

        $this->assertSame(TicketStatus::Repairing, $ticket->refresh()->status);
    }

    public function test_a_claim_within_the_warranted_item_is_repaired_free_without_extending_the_warranty(): void
    {
        [$original, $claim] = $this->claimOn(returnedDaysAgo: 40);
        $covered = $original->warrantyItems->first();

        $this->act($claim, TicketAction::Inspect);

        $this->actingAs($this->user)
            ->post(route('tickets.quote-items.store', $claim), ['description' => 'Board', 'quantity' => 1, 'unit_price' => '500000'])
            ->assertForbidden();
        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$claim, TicketAction::MarkFree]))
            ->assertSessionHasErrorsIn('action', 'action');
        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$claim, TicketAction::ClaimCovered]), ['claim_item_id' => $original->warrantyItems->last()->id])
            ->assertSessionHasErrorsIn('action', 'claim_item_id');

        $this->act($claim, TicketAction::ClaimCovered, ['claim_item_id' => $covered->id, 'note' => 'Thay lại board nguồn']);
        $this->act($claim, TicketAction::ReturnToCustomer, ['returned_date' => today()->toDateString()]);

        $claim->refresh();
        $this->assertSame(ClaimResult::Covered, $claim->claim_result);
        $this->assertSame(TicketResult::RepairWarranty, $claim->result);
        $this->assertTrue($claim->claimItem->is($covered));
        $this->assertFalse($claim->is_chargeable);
        $this->assertNull($claim->repairWarrantyEndsOn());

        $warranty = app(DeviceTracker::class)->for($original->device)->repairWarranty;
        $this->assertTrue($warranty->ticket->is($original));
        $this->assertTrue($warranty->endsOn->isSameDay($original->repairWarrantyEndsOn()));

        $this->actingAs($this->user)->get(route('tickets.slips.show', [$claim, 'return']))
            ->assertOk()
            ->assertSeeText('Chi phí: Miễn phí');
    }

    public function test_a_claim_outside_the_warranty_needs_a_reason_and_then_follows_the_normal_quote(): void
    {
        [$original, $claim] = $this->claimOn(returnedDaysAgo: 40);
        $this->act($claim, TicketAction::Inspect);

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$claim, TicketAction::ClaimRejected]))
            ->assertSessionHasErrorsIn('action', 'note');

        $this->act($claim, TicketAction::ClaimRejected, ['note' => 'Hỏng panel, phiếu cũ chỉ bảo hành board nguồn']);

        $claim->refresh();
        $this->assertSame(ClaimResult::Rejected, $claim->claim_result);
        $this->assertSame(TicketStatus::Inspecting, $claim->status);
        $this->assertContains(TicketAction::SendQuote, app(TicketWorkflow::class)->availableActions($claim));

        $this->actingAs($this->user)
            ->post(route('tickets.quote-items.store', $claim), ['description' => 'Panel', 'quantity' => 1, 'unit_price' => '2500000'])
            ->assertRedirect();
        $this->act($claim, TicketAction::SendQuote);

        $this->actingAs($this->user)->get(route('tickets.show', $original))
            ->assertOk()
            ->assertSeeText('Khách đã quay lại bảo hành')
            ->assertSeeText($claim->ticket_no);
    }

    public function test_a_claim_is_opened_as_a_repair_of_the_same_device_while_its_warranty_runs(): void
    {
        [$original] = $this->claimOn(returnedDaysAgo: 40, open: false);
        $model = $original->device->productModel;
        $payload = [
            'warranty_status' => 'out_of_warranty', 'kind' => TicketKind::Repair->value, 'serial_number' => $original->device->serial_number,
            'device_type_id' => $model->device_type_id, 'brand_id' => $model->brand_id, 'product_model_id' => $model->id,
            'customer_id' => $original->customer_id, 'contact_name' => 'Chị Hà', 'contact_phone' => '0912888999', 'fault_description' => 'Lại tự tắt', 'received_date' => today()->toDateString(),
            'photos' => [UploadedFile::fake()->image('nhan.jpg')], 'claim_ticket_id' => $original->id,
        ];

        $this->actingAs($this->user)->post(route('tickets.store'), [...$payload, 'serial_number' => 'KHAC-123'])
            ->assertSessionHasErrors('claim_ticket_id');

        $this->actingAs($this->user)->getJson(route('lookup.devices', ['serial' => $original->device->serial_number]))
            ->assertOk()
            ->assertJsonPath('0.warranty_claims.0.ticket_no', $original->ticket_no)
            ->assertJsonPath('0.warranty_claims.0.items.0.description', 'Board nguồn')
            ->assertJsonPath('0.warranty_claims.0.exclusions.0', 'Vỡ màn');

        $this->actingAs($this->user)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors();
        $this->assertTrue(RmaTicket::latest('id')->first()->claimTicket->is($original));

        $original->update(['returned_date' => today()->subMonths(7)]);
        $this->actingAs($this->user)->post(route('tickets.store'), $payload)
            ->assertSessionHasErrors(['claim_ticket_id' => "Bảo hành sửa chữa của phiếu {$original->ticket_no} đã hết hạn."]);
    }

    public function test_a_step_that_does_not_fit_the_current_status_is_refused(): void
    {
        $ticket = RmaTicket::factory()->create();

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::FinishRepair]))
            ->assertSessionHasErrorsIn('action', 'action');

        $this->assertSame(TicketStatus::Received, $ticket->refresh()->status);
        $this->assertSame(0, $ticket->statusLogs()->count());
    }

    public function test_cancelling_requires_a_reason_and_closes_a_pending_vendor_visit(): void
    {
        $ticket = RmaTicket::factory()->ofKind(TicketKind::Onsite)->create();
        $ticket->shipments()->create(['service_center_id' => ServiceCenter::factory()->create()->id, 'sent_date' => today(), 'outcome' => ShipmentOutcome::Pending, 'created_by' => $this->user->id]);

        $this->actingAs($this->user)
            ->post(route('tickets.actions.store', [$ticket, TicketAction::Cancel]))
            ->assertSessionHasErrorsIn('action', 'note');

        $this->act($ticket, TicketAction::Cancel, ['note' => 'Khách đổi ý']);

        $this->assertSame(TicketStatus::Cancelled, $ticket->refresh()->status);
        $this->assertSame(ShipmentOutcome::Cancelled, $ticket->shipments->sole()->outcome);
    }

    public function test_device_page_counts_the_repair_warranty_of_a_returned_ticket(): void
    {
        $device = Device::factory()->create();
        RmaTicket::factory()->create([
            'device_id' => $device->id, 'status' => TicketStatus::Returned, 'result' => TicketResult::Repaired,
            'repair_warranty_months' => 3, 'returned_date' => today()->subDays(10),
        ]);

        $this->actingAs($this->user)->get(route('devices.index', ['filter' => 'active']))
            ->assertOk()
            ->assertSeeText($device->serial_number);

        $this->actingAs($this->user)->get(route('devices.index', ['filter' => 'expired']))
            ->assertOk()
            ->assertDontSeeText($device->serial_number);
    }

    /**
     * A repair returned some days ago, warranting a board for 6 months and cleaning for 1, plus a new ticket claiming it.
     *
     * @return array{0: RmaTicket, 1: ?RmaTicket}
     */
    private function claimOn(int $returnedDaysAgo, bool $open = true): array
    {
        $original = RmaTicket::factory()->create([
            'status' => TicketStatus::Returned, 'result' => TicketResult::Repaired, 'repair_warranty_months' => 6,
            'returned_date' => today()->subDays($returnedDaysAgo), 'warranty_exclusions' => "Vỡ màn\nVào nước",
        ]);
        $original->warrantyItems()->createMany([
            ['description' => 'Board nguồn', 'months' => 6],
            ['description' => 'Vệ sinh', 'months' => 1],
        ]);

        $claim = $open
            ? RmaTicket::factory()->create(['device_id' => $original->device_id, 'customer_id' => $original->customer_id, 'claim_ticket_id' => $original->id])
            : null;

        return [$original->refresh(), $claim];
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
