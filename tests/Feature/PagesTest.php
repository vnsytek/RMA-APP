<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\RmaTicket;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Renders every screen against the demo data so a broken view or query shows up immediately.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'long.vu@sangy.vn')->sole());
    }

    public function test_list_and_report_pages_render(): void
    {
        foreach ([
            route('tickets.index'), route('tickets.index', ['kind' => 'onsite_sangy']), route('tickets.index', ['q' => 'R7NRKD']),
            route('tickets.create'), route('devices.index'), route('devices.index', ['filter' => 'active', 'sort' => 'expiring']),
            route('customers.index'), route('service-centers.index'), route('catalog.index'), route('users.index'),
            route('sequences.index'), route('flows'), route('profile.edit'),
            route('reports.index'), route('reports.index', ['preset' => 'year', 'kind' => 'repair']),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_every_ticket_device_and_customer_page_renders(): void
    {
        foreach (RmaTicket::all() as $ticket) {
            $this->get(route('tickets.show', $ticket))->assertOk()->assertSeeText($ticket->ticket_no);
        }

        foreach (Device::all() as $device) {
            $this->get(route('devices.show', $device))->assertOk()->assertSeeText($device->serial_number);
        }

        $this->get(route('customers.show', RmaTicket::first()->customer))->assertOk();
    }

    public function test_a_returned_ticket_shows_its_photos_by_stage(): void
    {
        $returned = RmaTicket::where('status', 'returned')->firstOrFail();

        $this->get(route('tickets.show', $returned))
            ->assertOk()
            ->assertSeeText('Lúc nhận máy')
            ->assertSeeText('Lúc trả máy')
            ->assertDontSeeText('Phiếu chưa có ảnh');

        Storage::disk('local')->assertExists($returned->attachments->pluck('file_path')->all());

        $this->get(route('tickets.slips.show', [$returned, 'return']))
            ->assertOk()
            ->assertSeeText('Serial: '.$returned->device->serial_number)
            ->assertDontSeeText('Seri trả')
            ->assertSeeText('Vệ sinh, thay keo tản nhiệt')
            ->assertDontSeeText('Không bảo hành')
            ->assertDontSeeText('Kết quả');
    }

    public function test_slips_and_excel_exports_download(): void
    {
        $ready = RmaTicket::where('status', 'ready')->whereNotNull('returned_device_id')->firstOrFail();

        $this->get(route('tickets.slips.show', [$ready, 'receipt']))->assertOk()->assertSeeText('Phiếu nhận hàng bảo hành')->assertSeeText('Tình trạng / lỗi khách báo')->assertSeeText('Phụ kiện kèm theo');
        $this->get(route('tickets.slips.show', [$ready, 'return']))->assertOk()->assertSeeText('Seri trả');
        $this->get(route('tickets.slips.excel', [$ready, 'return']))->assertOk()->assertDownload('PhieuTra_'.$ready->ticket_no.'.xlsx');
        $this->get(route('reports.export', ['preset' => 'year']))->assertOk()->assertDownload();
    }

    public function test_vendor_visit_at_the_customer_has_no_slips(): void
    {
        $atCustomer = RmaTicket::where('onsite_location', 'customer')->firstOrFail();

        $this->get(route('tickets.slips.show', [$atCustomer, 'receipt']))->assertNotFound();
    }

    public function test_serial_lookup_returns_the_device_and_its_repair_warranty(): void
    {
        $this->getJson(route('lookup.devices', ['serial' => 'R7NRKD012345']))
            ->assertOk()
            ->assertJsonPath('0.tickets_count', 3)
            ->assertJsonPath('0.repair_warranty.ticket_no', '26080001')
            ->assertJsonPath('0.warranty_claims.0.items.0.description', 'Vệ sinh, thay keo tản nhiệt')
            ->assertJsonPath('0.warranty_claims.0.items.1.level', 'expired');
    }
}
