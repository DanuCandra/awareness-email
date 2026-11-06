<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function adminData(): array
    {
        return ['name' => 'Admin Kedua', 'username' => 'admin.kedua', 'email' => 'kedua@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'];
    }

    public function test_initial_admin_can_use_requested_short_password_and_seeding_is_idempotent(): void
    {
        config(['initial-admin.name' => 'Danu Candra', 'initial-admin.username' => 'danu', 'initial-admin.email' => 'danu@example.com', 'initial-admin.password' => '123']);

        $this->seed(InitialAdminSeeder::class);
        $admin = User::sole();
        $this->assertSame('danu', $admin->username);
        $this->assertSame('Danu Candra', $admin->name);
        $this->assertTrue(Hash::check('123', $admin->password));
        $this->assertNotSame('123', $admin->password);

        $admin->update(['password' => 'ChangedPassword123!']);
        $this->seed(InitialAdminSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('ChangedPassword123!', $admin->fresh()->password));
    }

    public function test_admin_can_log_in_with_username_and_email_including_initial_short_password(): void
    {
        $admin = User::factory()->create(['username' => 'danu', 'email' => 'danu@example.com', 'password' => '123']);

        foreach ([' DANU ', 'DANU@EXAMPLE.COM'] as $identifier) {
            $this->post(route('login.store'), ['identifier' => $identifier, 'password' => '123'])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($admin);
            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_every_workspace_page_requires_authentication_and_admin_json_is_private(): void
    {
        foreach (['/', '/emails', '/simulasi', '/simulasi/buat', '/simulasi/detail', '/email-templates', '/reports', '/settings', '/admins'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->getJson(route('admins.index'))->assertUnauthorized();
        $this->postJson(route('admins.store'), $this->adminData())->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_failed_login_does_not_authenticate_or_flash_the_password(): void
    {
        User::factory()->create(['username' => 'danu', 'password' => '123']);

        $this->from(route('login'))->post(route('login.store'), ['identifier' => 'danu', 'password' => 'wrong'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['identifier'])
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_repeated_failed_logins_are_throttled_even_with_a_correct_password_afterwards(): void
    {
        User::factory()->create(['username' => 'danu', 'password' => '123']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['identifier' => 'danu', 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['identifier' => 'danu', 'password' => '123'])
            ->assertSessionHasErrors(['identifier' => 'Terlalu banyak percobaan masuk. Coba kembali dalam satu menit.']);
        $this->assertGuest();
    }

    public function test_logged_in_admin_can_read_and_create_persisted_admins_without_exposing_passwords(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->getJson(route('admins.index'))->assertOk()
            ->assertJsonPath('current_admin.id', $admin->id)
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');

        $this->postJson(route('admins.store'), $this->adminData())->assertCreated()
            ->assertJsonPath('data.username', 'admin.kedua')
            ->assertJsonMissingPath('data.password');

        $created = User::where('username', 'admin.kedua')->sole();
        $this->assertTrue(Hash::check('Password123!', $created->password));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_new_admins_require_eight_character_confirmed_passwords(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->postJson(route('admins.store'), [...$this->adminData(), 'password' => '123', 'password_confirmation' => '123'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson(route('admins.store'), [...$this->adminData(), 'password_confirmation' => 'different'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_username_and_email_uniqueness_are_normalized_and_ignore_the_edited_admin(): void
    {
        $admin = User::factory()->create(['username' => 'danu', 'email' => 'danu@example.com']);
        $this->actingAs($admin)->postJson(route('admins.store'), [...$this->adminData(), 'username' => ' DANU ', 'email' => 'DANU@EXAMPLE.COM'])
            ->assertUnprocessable()->assertJsonValidationErrors(['username', 'email']);

        $hash = $admin->password;
        $this->putJson(route('admins.update', $admin), ['name' => 'Nama baru', 'username' => ' DANU ', 'email' => 'DANU@EXAMPLE.COM', 'password' => '', 'password_confirmation' => '', 'remember_token' => 'injected'])
            ->assertOk()->assertJsonPath('data.name', 'Nama baru');
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertNotSame('injected', $admin->fresh()->remember_token);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_changing_own_password_logs_out_and_new_password_can_log_in(): void
    {
        $admin = User::factory()->create(['username' => 'danu', 'password' => '123']);

        $this->actingAs($admin)->putJson(route('admins.update', $admin), [...$this->adminData(), 'username' => 'danu'])
            ->assertOk()->assertJsonPath('sessionInvalidated', true);
        $this->assertGuest();
        $this->assertTrue(Hash::check('Password123!', $admin->fresh()->password));
        $this->post(route('login.store'), ['identifier' => 'danu', 'password' => '123'])->assertSessionHasErrors('identifier');
        $this->assertGuest();
        $this->post(route('login.store'), ['identifier' => 'danu', 'password' => 'Password123!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_reset_another_password_without_losing_their_own_session(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->putJson(route('admins.update', $other), [...$this->adminData(), 'username' => $other->username, 'email' => $other->email])
            ->assertOk()->assertJsonPath('sessionInvalidated', false);
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check('Password123!', $other->fresh()->password));
    }

    public function test_admin_cannot_delete_self_but_can_delete_other_accounts(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->deleteJson(route('admins.destroy', $admin))->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->deleteJson(route('admins.destroy', $other))->assertOk();
        $this->assertModelMissing($other);
        $this->assertDatabaseCount('users', 1);
        $this->deleteJson(route('admins.destroy', $admin))->assertUnprocessable();
    }

    public function test_forged_browser_preview_sessions_grant_no_access_and_admin_names_are_escaped(): void
    {
        $this->withSession(['security-awareness.admin-session.v1' => ['adminId' => 'admin-demo']])->get('/admins')->assertRedirect(route('login'));
        $admin = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_logout_ends_server_session_and_protected_pages_cannot_be_reopened(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/admins')->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/admins')->assertRedirect(route('login'));
    }

    public function test_password_change_revokes_existing_sessions_on_other_devices(): void
    {
        $admin = User::factory()->create();
        $oldHash = $admin->password;
        $this->actingAs($admin)->withSession(['password_hash_web' => $oldHash]);
        $admin->update(['password' => 'NewPassword123!']);

        $this->get('/admins')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
