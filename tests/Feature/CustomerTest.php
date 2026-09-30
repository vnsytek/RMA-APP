<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_can_be_saved_with_its_tax_code_and_no_phone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('customers.store'), ['name' => 'Công ty A', 'tax_code' => ' 0801379534-001 '])->assertSessionHasNoErrors();
        $this->assertSame('0801379534-001', Customer::sole()->tax_code);
        $this->assertNull(Customer::sole()->phone);

        $this->actingAs($user)->post(route('customers.store'), ['name' => 'Công ty B', 'tax_code' => '0801379534-001'])
            ->assertSessionHasErrorsIn('customer', ['tax_code' => 'Đã có khách hàng dùng mã số thuế này.']);
        $this->actingAs($user)->post(route('customers.store'), ['name' => 'Công ty C', 'tax_code' => '12345'])
            ->assertSessionHasErrorsIn('customer', 'tax_code');
        $this->actingAs($user)->post(route('customers.store'), ['name' => 'Anh Dũng'])->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('customers.index', ['q' => '0801379534']))->assertOk()->assertSeeText('Công ty A');
    }

    public function test_customers_are_viewed_edited_and_deleted_by_an_admin_only_while_they_have_no_tickets(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $customer = Customer::create(['name' => 'Công ty Mới', 'tax_code' => '0202019370', 'address' => 'Hải Phòng']);

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('data-href="'.route('customers.show', $customer).'"', false)
            ->assertSee('data-dialog-open="customer-new"', false);

        $this->actingAs($user)->get(route('customers.show', $customer))->assertOk()->assertSeeText('MST 0202019370')->assertDontSeeText('Xoá');

        $this->actingAs($user)->put(route('customers.update', $customer), ['name' => 'Công ty Mới (đổi tên)', 'tax_code' => '0202019370', 'phone' => '0900'])
            ->assertRedirect(route('customers.show', $customer));
        $this->assertSame('Công ty Mới (đổi tên)', $customer->refresh()->name);

        $this->actingAs($user)->delete(route('customers.destroy', $customer))->assertForbidden();

        $withTicket = RmaTicket::factory()->create()->customer;
        $this->actingAs($admin)->delete(route('customers.destroy', $withTicket))->assertSessionHas('error');
        $this->assertModelExists($withTicket);

        $this->actingAs($admin)->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));
        $this->assertModelMissing($customer);
    }

    public function test_tax_code_lookup_uses_xinvoice_then_falls_back_to_vietqr_and_caches_found_companies(): void
    {
        Http::fake([
            'api.xinvoice.vn/*/0202019370' => Http::response([
                'orgType' => 'Doanh nghiệp / Đơn vị sự nghiệp công lập', 'taxID' => '0202019370', 'name' => 'CÔNG TY TNHH PEGATRON VIỆT NAM',
                'address' => 'Lô đất CN3A, KCN DEEP C 2A,  Phường Đông Hải, TP Hải Phòng', 'taxDepartment' => 'Thuế thành phố Hải Phòng', 'status' => 'NNT đang hoạt động',
            ]),
            'api.xinvoice.vn/*' => Http::response(['message' => 'Too many requests'], 429),
            'api.vietqr.io/*/1001275425' => Http::response(['code' => '00', 'data' => [
                'name' => 'CÔNG TY TNHH YULONG', 'address' => 'KCN Liên Hà Thái, Xã Thái Thụy, Hưng Yên', 'status' => 'NNT ngừng hoạt động và đã đóng MST',
            ]]),
            'api.vietqr.io/*/0100000000' => Http::response(['code' => '52', 'desc' => 'Không tìm thấy']),
            'api.vietqr.io/*' => Http::response(['code' => '429'], 429),
        ]);
        $user = User::factory()->create();
        $existing = Customer::create(['name' => 'Pegatron', 'tax_code' => '0202019370']);

        $this->actingAs($user)->getJson(route('lookup.tax-code', ['code' => ' 0202019370 ']))
            ->assertOk()
            ->assertJsonPath('result', 'found')
            ->assertJsonPath('source', 'xinvoice')
            ->assertJsonPath('name', 'CÔNG TY TNHH PEGATRON VIỆT NAM')
            ->assertJsonPath('address', 'Lô đất CN3A, KCN DEEP C 2A, Phường Đông Hải, TP Hải Phòng')
            ->assertJsonPath('tax_department', 'Thuế thành phố Hải Phòng')
            ->assertJsonPath('active', true)
            ->assertJsonPath('existing.id', $existing->id);

        $this->actingAs($user)->getJson(route('lookup.tax-code', ['code' => '0202019370']))->assertJsonPath('result', 'found');
        Http::assertSentCount(1);

        $this->actingAs($user)->getJson(route('lookup.tax-code', ['code' => '1001275425']))
            ->assertJsonPath('result', 'found')
            ->assertJsonPath('source', 'vietqr')
            ->assertJsonPath('active', false);
        $this->actingAs($user)->getJson(route('lookup.tax-code', ['code' => '0100000000']))->assertJsonPath('result', 'not_found');
        $this->actingAs($user)->getJson(route('lookup.tax-code', ['code' => '0200000000']))->assertJsonPath('result', 'unavailable');
        $this->actingAs($user)->getJson(route('lookup.tax-code', ['code' => '12345']))->assertUnprocessable();
    }

    public function test_a_new_customer_opened_with_a_ticket_keeps_its_tax_code(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $model = ProductModel::factory()->create();
        $payload = [
            'warranty_status' => 'out_of_warranty', 'kind' => 'repair', 'serial_number' => 'SN-1',
            'device_type_id' => $model->device_type_id, 'brand_id' => $model->brand_id, 'product_model_id' => $model->id,
            'customer_id' => 'new', 'customer_name' => 'CÔNG TY TNHH YULONG', 'contact_name' => 'Chị Nga', 'contact_phone' => '0900000000', 'customer_tax_code' => '1001275425',
            'fault_description' => 'Không lên nguồn', 'received_date' => today()->toDateString(), 'photos' => [UploadedFile::fake()->image('may.jpg')],
        ];

        $this->actingAs($user)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('1001275425', Customer::sole()->tax_code);

        $this->actingAs($user)->post(route('tickets.store'), $payload)->assertSessionHasErrors('customer_tax_code');
    }
}
