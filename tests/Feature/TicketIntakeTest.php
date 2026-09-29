<?php

namespace Tests\Feature;

use App\Enums\AttachmentStage;
use App\Enums\AttachmentType;
use App\Enums\ShipmentOutcome;
use App\Enums\TicketKind;
use App\Enums\TicketStatus;
use App\Enums\WarrantyStatus;
use App\Models\Attachment;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use App\Services\TicketNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_ticket_numbers_count_up_within_a_month_and_restart_the_next_month(): void
    {
        $numbers = app(TicketNumberGenerator::class);

        $this->assertSame('26090001', $numbers->next(Carbon::parse('2026-09-03')));
        $this->assertSame('26090002', $numbers->next(Carbon::parse('2026-09-28')));
        $this->assertSame('26100001', $numbers->next(Carbon::parse('2026-10-01')));
        $this->assertSame('26090003', $numbers->peek(Carbon::parse('2026-09-30')));
    }

    public function test_repair_ticket_creates_new_customer_catalog_entries_and_stores_intake_photos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tickets.store'), [
            ...$this->validPayload(),
            'device_type_id' => 'new',
            'new_device_type' => 'Máy chiếu',
            'brand_id' => 'new',
            'new_brand' => 'epson',
            'product_model_id' => 'new',
            'new_model_code' => 'EB-X06',
            'photos' => [
                UploadedFile::fake()->image('mat-truoc.jpg'),
                UploadedFile::fake()->create('hoa-don.pdf', 120, 'application/pdf'),
            ],
        ]);

        $ticket = RmaTicket::sole();
        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertSame(TicketStatus::Received, $ticket->status);
        $this->assertSame(WarrantyStatus::OutOfWarranty, $ticket->warranty_status);
        $this->assertSame('Công ty Mới', $ticket->customer->name);
        $this->assertSame('MAY_CHIEU', $ticket->device->productModel->deviceType->code);
        $this->assertSame('EPSON', $ticket->device->productModel->brand->name);
        $this->assertSame('EB-X06', $ticket->device->productModel->code);

        $log = $ticket->statusLogs->sole();
        $this->assertNull($log->from_status);
        $this->assertSame(TicketStatus::Received, $log->to_status);
        $this->assertCount(2, $log->attachments);
        $this->assertTrue($log->attachments->every(fn (Attachment $attachment) => $attachment->stage === AttachmentStage::Intake));
        $this->assertSame([AttachmentType::Photo, AttachmentType::Other], $log->attachments->pluck('doc_type')->all());
        Storage::disk('local')->assertExists($log->attachments->pluck('file_path')->all());
    }

    public function test_a_device_taken_in_needs_a_photo_of_its_condition_but_a_vendor_visit_at_the_customer_does_not(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload();

        $this->actingAs($user)->post(route('tickets.store'), [...$payload, 'photos' => []])
            ->assertSessionHasErrors(['photos' => 'Cần chụp ít nhất 1 ảnh tình trạng máy lúc nhận máy.']);

        $this->actingAs($user)->post(route('tickets.store'), [...$payload, 'photos' => [UploadedFile::fake()->create('hoa-don.pdf', 80, 'application/pdf')]])
            ->assertSessionHasErrors('photos');

        $this->assertSame(0, RmaTicket::count());

        $this->actingAs($user)->post(route('tickets.store'), [
            ...$payload,
            'photos' => [],
            'warranty_status' => WarrantyStatus::InWarranty->value,
            'kind' => TicketKind::Onsite->value,
            'service_center_id' => ServiceCenter::factory()->create()->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(TicketKind::Onsite, RmaTicket::sole()->kind());
    }

    public function test_typing_an_existing_brand_or_type_name_reuses_it(): void
    {
        $user = User::factory()->create();
        $type = DeviceType::factory()->create(['name' => 'Màn hình']);
        $brand = Brand::factory()->create(['name' => 'DELL']);
        $payload = $this->validPayload();
        [$typesBefore, $brandsBefore] = [DeviceType::count(), Brand::count()];

        $this->actingAs($user)->post(route('tickets.store'), [
            ...$payload,
            'device_type_id' => 'new',
            'new_device_type' => 'màn hình',
            'brand_id' => 'new',
            'new_brand' => ' dell ',
            'product_model_id' => 'new',
            'new_model_code' => 'U2723QE',
        ])->assertSessionHasNoErrors();

        $this->assertSame($typesBefore, DeviceType::count());
        $this->assertSame($brandsBefore, Brand::count());
        $model = ProductModel::where('code', 'U2723QE')->sole();
        $this->assertTrue($model->deviceType->is($type));
        $this->assertTrue($model->brand->is($brand));
    }

    public function test_out_of_warranty_device_can_only_be_opened_as_a_repair(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tickets.store'), [
            ...$this->validPayload(),
            'warranty_status' => WarrantyStatus::OutOfWarranty->value,
            'kind' => TicketKind::CarryIn->value,
        ])->assertSessionHasErrors(['kind' => 'Máy hết bảo hành chỉ lập được phiếu sửa chữa.']);

        $this->assertSame(0, RmaTicket::count());
    }

    public function test_vendor_visit_at_sang_y_needs_a_service_center_and_opens_a_pending_visit(): void
    {
        $user = User::factory()->create();
        $center = ServiceCenter::factory()->create();
        $payload = [...$this->validPayload(), 'warranty_status' => WarrantyStatus::InWarranty->value, 'kind' => TicketKind::OnsiteSangy->value];

        $this->actingAs($user)->post(route('tickets.store'), $payload)->assertSessionHasErrors('service_center_id');

        $this->actingAs($user)->post(route('tickets.store'), [
            ...$payload,
            'service_center_id' => $center->id,
            'vendor_case_no' => '92471133580',
            'appointment_date' => today()->addDays(2)->toDateString(),
            'accessories' => 'Sạc',
        ])->assertSessionHasNoErrors();

        $ticket = RmaTicket::sole();
        $this->assertSame(TicketKind::OnsiteSangy, $ticket->kind());
        $this->assertSame(TicketStatus::Scheduled, $ticket->status);
        $this->assertSame('Sạc', $ticket->accessories);
        $this->assertTrue($ticket->printsSlips());
        $this->assertSame(ShipmentOutcome::Pending, $ticket->pendingShipment->outcome);
        $this->assertSame('92471133580', $ticket->pendingShipment->vendor_case_no);
    }

    public function test_an_existing_customer_and_device_are_reused(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $model = ProductModel::factory()->create();

        foreach (range(1, 2) as $round) {
            $this->actingAs($user)->post(route('tickets.store'), [
                ...$this->validPayload(),
                'customer_id' => $customer->id,
                'device_type_id' => $model->device_type_id,
                'brand_id' => $model->brand_id,
                'product_model_id' => $model->id,
                'serial_number' => 'SN-001',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $customer->tickets()->count());
        $this->assertSame(1, $model->devices()->count());
        $period = today()->format('ym');
        $this->assertSame([$period.'0001', $period.'0002'], RmaTicket::orderBy('id')->pluck('ticket_no')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $model = ProductModel::factory()->create();

        return [
            'warranty_status' => WarrantyStatus::OutOfWarranty->value,
            'kind' => TicketKind::Repair->value,
            'serial_number' => 'ABC123',
            'device_type_id' => $model->device_type_id,
            'brand_id' => $model->brand_id,
            'product_model_id' => $model->id,
            'customer_id' => 'new',
            'customer_name' => 'Công ty Mới',
            'customer_phone' => '0900000000',
            'fault_description' => 'Không lên nguồn',
            'received_date' => today()->toDateString(),
            'photos' => [UploadedFile::fake()->image('tinh-trang-may.jpg')],
        ];
    }
}
