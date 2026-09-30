<?php

namespace Tests\Feature;

use App\Models\ServiceCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_centers_are_added_and_edited_in_dialogs_and_only_admins_hide_them(): void
    {
        $user = User::factory()->create();
        $center = ServiceCenter::factory()->create(['name' => 'TTBH ASUS Hải Phòng']);

        $this->actingAs($user)->get(route('service-centers.index'))
            ->assertOk()
            ->assertSee('data-dialog-open="center-new"', false)
            ->assertSee('data-dialog-open="center-'.$center->id.'"', false)
            ->assertDontSee(route('service-centers.toggle', $center), false);

        $this->actingAs($user)->post(route('service-centers.store'), ['name' => ''])->assertSessionHasErrorsIn('serviceCenter', 'name');
        $this->actingAs($user)->post(route('service-centers.store'), ['name' => 'Tin học Lâm Hiếu', 'brands' => 'VSP'])->assertSessionHasNoErrors();

        $this->actingAs($user)->put(route('service-centers.update', $center), ['name' => ''])->assertSessionHasErrorsIn('serviceCenter'.$center->id, 'name');
        $this->actingAs($user)->put(route('service-centers.update', $center), ['name' => 'TTBH ASUS HP', 'phone' => '0225 123'])->assertSessionHasNoErrors();
        $this->assertSame(['TTBH ASUS HP', '0225 123'], [$center->refresh()->name, $center->phone]);

        $this->actingAs($user)->patch(route('service-centers.toggle', $center))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('service-centers.toggle', $center));
        $this->assertFalse($center->refresh()->is_active);
    }
}
