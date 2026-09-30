<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerContactTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $pegatron;

    private CustomerContact $nga;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->admin()->create();
        $this->pegatron = Customer::create(['name' => 'CÔNG TY TNHH PEGATRON VIỆT NAM', 'tax_code' => '0202019370']);
        $this->nga = $this->pegatron->contacts()->create(['name' => 'Chị Nga', 'phone' => '0901234567']);
    }

    public function test_each_ticket_keeps_the_contact_who_sent_the_device(): void
    {
        $this->open(['contact_id' => $this->nga->id])->assertSessionHasNoErrors();
        $first = RmaTicket::latest('id')->first();

        $this->open(['contact_id' => 'new', 'contact_name' => ' Chị  Hà ', 'contact_phone' => '0912888999'])->assertSessionHasNoErrors();
        $second = RmaTicket::latest('id')->first();

        $this->assertSame(['Chị Nga', '0901234567'], [$first->contact_name, $first->contact_phone]);
        $this->assertSame(['Chị Hà', '0912888999'], [$second->contact_name, $second->contact_phone]);
        $this->assertSame(['Chị Hà', 'Chị Nga'], $this->pegatron->contacts()->pluck('name')->all());
        $this->assertSame(1, Customer::count());

        $this->nga->update(['phone' => '0999999999']);
        $this->assertSame('0901234567', $first->refresh()->contact_phone);

        $this->actingAs($this->user)->get(route('tickets.slips.show', [$first, 'receipt']))->assertOk()->assertSeeText('Người liên hệ: Chị Nga');
        $this->actingAs($this->user)->get(route('tickets.slips.show', [$second, 'receipt']))->assertOk()->assertSeeText('Người liên hệ: Chị Hà');
        $this->actingAs($this->user)->get(route('tickets.index', ['q' => 'Chị Hà']))
            ->assertSeeText($second->ticket_no)
            ->assertDontSeeText($first->ticket_no);
    }

    public function test_a_new_contact_with_a_known_phone_reuses_that_entry(): void
    {
        $this->open(['contact_id' => 'new', 'contact_name' => 'Nga (Kế toán)', 'contact_phone' => '0901234567'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->pegatron->contacts()->count());
        $this->assertSame('Nga (Kế toán)', $this->nga->refresh()->name);
    }

    public function test_only_active_contacts_of_the_chosen_customer_can_be_picked(): void
    {
        $other = Customer::create(['name' => 'Công ty khác'])->contacts()->create(['name' => 'Anh Tùng', 'phone' => '0900000001']);

        $this->open(['contact_id' => $other->id])->assertSessionHasErrors('contact_id');

        $this->actingAs($this->user)->patch(route('customers.contacts.toggle', [$this->pegatron, $this->nga]));
        $this->assertFalse($this->nga->refresh()->is_active);
        $this->open(['contact_id' => $this->nga->id])->assertSessionHasErrors('contact_id');
        $this->open([])->assertSessionHasErrors(['contact_name', 'contact_phone']);
    }

    public function test_the_address_book_is_managed_on_the_customer_page(): void
    {
        $this->actingAs($this->user)->post(route('customers.contacts.store', $this->pegatron), ['name' => 'Chị Hà', 'phone' => '0912888999'])
            ->assertRedirect(route('customers.show', $this->pegatron).'#nguoi-lien-he');
        $this->actingAs($this->user)->post(route('customers.contacts.store', $this->pegatron), ['name' => 'Trùng số', 'phone' => '0912888999'])
            ->assertSessionHasErrorsIn('contact', 'phone');

        $ha = $this->pegatron->contacts()->where('name', 'Chị Hà')->sole();
        $this->actingAs($this->user)->put(route('customers.contacts.update', [$this->pegatron, $ha]), ['name' => 'Chị Thu Hà', 'phone' => '0912888999'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Chị Thu Hà', $ha->refresh()->name);

        $this->actingAs($this->user)->get(route('customers.show', $this->pegatron))
            ->assertOk()
            ->assertSeeText('Chị Thu Hà')
            ->assertSeeText('Chị Nga');

        $this->actingAs($this->user)->get(route('tickets.create', ['customer' => $this->pegatron->id]))
            ->assertOk()
            ->assertSee('0912888999', false);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function open(array $contact)
    {
        $model = ProductModel::factory()->create();

        return $this->actingAs($this->user)->post(route('tickets.store'), [
            'warranty_status' => 'out_of_warranty', 'kind' => 'repair', 'serial_number' => 'SN-'.fake()->unique()->numerify('#####'),
            'device_type_id' => $model->device_type_id, 'brand_id' => $model->brand_id, 'product_model_id' => $model->id,
            'customer_id' => $this->pegatron->id, 'fault_description' => 'Không lên nguồn', 'received_date' => today()->toDateString(),
            'photos' => [UploadedFile::fake()->image('may.jpg')],
            ...$contact,
        ]);
    }
}
