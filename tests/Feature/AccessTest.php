<?php

namespace Tests\Feature;

use App\Enums\AttachmentStage;
use App\Enums\AttachmentType;
use App\Models\Brand;
use App\Models\DeviceType;
use App\Models\RmaTicket;
use App\Models\User;
use App\Services\AttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('tickets.index'))->assertRedirect(route('login'));
    }

    public function test_active_users_can_log_in_and_locked_users_cannot(): void
    {
        $active = User::factory()->create();
        $locked = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $locked->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khoá.']);
        $this->assertGuest();

        $this->post('/login', ['email' => $active->email, 'password' => 'password'])->assertRedirect(route('tickets.index'));
        $this->assertAuthenticatedAs($active);
    }

    public function test_only_admins_manage_accounts(): void
    {
        $this->actingAs(User::factory()->create())->get(route('users.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('users.index'))->assertOk();
    }

    public function test_admin_creates_a_user_account(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('users.store'), ['name' => 'Nhân viên mới', 'email' => 'moi@sangy.vn', 'role' => 'user', 'password' => 'matkhau123'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'moi@sangy.vn', 'role' => 'user', 'is_active' => true]);
    }

    public function test_admin_cannot_lock_or_demote_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('users.toggle', $admin))->assertForbidden();
        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user'])
            ->assertSessionHasErrorsIn('user'.$admin->id, 'role');
        $this->assertTrue($admin->refresh()->isAdmin());
    }

    public function test_admin_edits_name_email_role_and_password_in_the_edit_dialog(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create(['name' => 'Huy', 'email' => 'huy@sangy.vn']);
        User::factory()->create(['email' => 'tam@sangy.vn']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('data-dialog-open="user-'.$staff->id.'"', false);

        $this->actingAs($admin)->put(route('users.update', $staff), ['name' => 'Huy', 'email' => 'tam@sangy.vn', 'role' => 'user'])
            ->assertSessionHasErrorsIn('user'.$staff->id, 'email');

        $this->actingAs($admin)->put(route('users.update', $staff), [
            'name' => 'Nguyễn Quang Huy', 'email' => 'huy.nguyen@sangy.vn', 'role' => 'admin', 'password' => 'matkhaumoi1',
        ])->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame(['Nguyễn Quang Huy', 'huy.nguyen@sangy.vn'], [$staff->name, $staff->email]);
        $this->assertTrue($staff->isAdmin());
        $this->assertTrue(Hash::check('matkhaumoi1', $staff->password));
    }

    public function test_users_may_add_catalog_entries_but_only_admins_hide_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('catalog.brands.store'), ['name' => 'lenovo'])->assertSessionHasNoErrors();
        $brand = Brand::where('name', 'LENOVO')->sole();

        $this->actingAs($user)->patch(route('catalog.brands.toggle', $brand))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('catalog.brands.toggle', $brand));
        $this->assertFalse($brand->refresh()->is_active);
    }

    public function test_only_admins_edit_the_default_warranty_exclusions_of_a_device_type(): void
    {
        $type = DeviceType::factory()->create(['code' => 'PRINTER']);
        $this->assertStringContainsString('vật tư tiêu hao', $type->warranty_exclusions);

        $this->actingAs(User::factory()->create())
            ->put(route('catalog.device-types.update', $type), ['warranty_exclusions' => 'Mực'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('catalog.device-types.update', $type), ['warranty_exclusions' => "  Mực in \n\nGiấy kém chất lượng"])
            ->assertSessionHasNoErrors();

        $this->assertSame("Mực in\nGiấy kém chất lượng", $type->refresh()->warranty_exclusions);
    }

    public function test_only_the_uploader_or_an_admin_deletes_an_attachment(): void
    {
        Storage::fake('local');
        $uploader = User::factory()->create();
        $colleague = User::factory()->create();
        $ticket = RmaTicket::factory()->create(['created_by' => $uploader->id, 'technician_id' => $colleague->id]);
        $attachment = app(AttachmentStorage::class)->store($ticket, UploadedFile::fake()->image('may.jpg'), AttachmentType::Photo, AttachmentStage::Other, $uploader);

        $this->actingAs($colleague)->delete(route('tickets.attachments.destroy', [$ticket, $attachment]))->assertForbidden();

        $this->actingAs($uploader)->delete(route('tickets.attachments.destroy', [$ticket, $attachment]))->assertRedirect();
        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->file_path);
    }

    public function test_the_last_photo_taken_when_the_device_came_in_cannot_be_deleted(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $ticket = RmaTicket::factory()->create();
        $storage = app(AttachmentStorage::class);
        $first = $storage->store($ticket, UploadedFile::fake()->image('nhan-1.jpg'), AttachmentType::Photo, AttachmentStage::Intake, $admin);

        $this->actingAs($admin)->delete(route('tickets.attachments.destroy', [$ticket, $first]))->assertSessionHasErrorsIn('attachment', 'attachment');
        $this->assertModelExists($first);

        $this->actingAs($admin)->post(route('tickets.attachments.store', $ticket), [
            'stage' => AttachmentStage::Intake->value,
            'doc_type' => AttachmentType::Photo->value,
            'files' => [UploadedFile::fake()->image('nhan-2.jpg')],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete(route('tickets.attachments.destroy', [$ticket, $first]))->assertSessionHasNoErrors();
        $this->assertModelMissing($first);
        $this->assertSame(AttachmentStage::Intake, $ticket->attachments()->sole()->stage);
    }

    public function test_attachment_of_another_ticket_is_not_reachable_through_this_ticket(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $attachment = app(AttachmentStorage::class)->store(RmaTicket::factory()->create(), UploadedFile::fake()->image('may.jpg'), AttachmentType::Photo, AttachmentStage::Other, $user);

        $this->actingAs($user)->get(route('tickets.attachments.show', [RmaTicket::factory()->create(), $attachment]))->assertNotFound();
    }
}
