<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_user_management_to_login(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_is_forbidden_from_user_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_inactive_admin_is_forbidden_from_user_management(): void
    {
        $admin = User::factory()->admin()->create([
            'status' => User::STATUS_INACTIVE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_search_users_by_name_and_email_and_filter_by_role_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        $matchingUser = User::factory()->create([
            'name' => 'UniqueNameMatch',
            'email' => 'ordinary-user@example.test',
            'role' => User::ROLE_USER,
            'status' => User::STATUS_INACTIVE,
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Unrelated User',
            'email' => 'unrelated-user@example.test',
        ]);
        $emailMatch = User::factory()->admin()->create([
            'name' => 'Ordinary Admin Name',
            'email' => 'needle-admin@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'q' => 'UniqueNameMatch',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_INACTIVE,
            ]))
            ->assertOk()
            ->assertSeeText($matchingUser->name)
            ->assertDontSeeText($otherUser->name)
            ->assertDontSeeText($emailMatch->name);

        $this->get(route('admin.users.index', ['q' => 'needle-admin@example.test']))
            ->assertOk()
            ->assertSeeText($emailMatch->name)
            ->assertDontSeeText($matchingUser->name);
    }

    public function test_admin_user_list_is_paginated_and_keeps_the_requested_page(): void
    {
        $admin = User::factory()->admin()->create([
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);
        $pageOneUser = User::factory()->create([
            'name' => 'Page One User',
            'created_at' => now()->addDays(11),
            'updated_at' => now()->addDays(11),
        ]);

        foreach (range(2, 10) as $day) {
            User::factory()->create([
                'created_at' => now()->addDays($day),
                'updated_at' => now()->addDays($day),
            ]);
        }

        $pageTwoUser = User::factory()->create([
            'name' => 'Page Two User',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('users', function (LengthAwarePaginator $users): bool {
                return $users->currentPage() === 2 && $users->total() === 12;
            })
            ->assertSeeText($pageTwoUser->name)
            ->assertDontSeeText($pageOneUser->name);
    }

    public function test_admin_can_open_create_and_edit_user_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('name="role"', false);

        $this->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSeeText($user->email)
            ->assertSee('name="password"', false);
    }

    public function test_admin_can_create_user_with_a_hashed_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New User',
                'email' => 'new-user@example.test',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'Tài khoản đã được tạo thành công.');

        $user = User::query()->where('email', 'new-user@example.test')->firstOrFail();
        $this->assertSame('New User', $user->name);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertTrue(Hash::check('secure-password', $user->password));
    }

    public function test_admin_cannot_create_user_with_an_existing_email(): void
    {
        $admin = User::factory()->admin()->create();
        $existingUser = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate Email',
                'email' => $existingUser->email,
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_user_without_changing_the_existing_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'name' => 'Previous Name',
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $existingPasswordHash = $user->password;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'Tài khoản đã được cập nhật.');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertSame(User::STATUS_INACTIVE, $user->status);
        $this->assertSame($existingPasswordHash, $user->password);
    }

    public function test_admin_can_update_password_and_refresh_the_remember_token(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $existingRememberToken = $user->getRememberToken();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame($existingRememberToken, $user->getRememberToken());
        $this->assertSame(60, strlen($user->getRememberToken()));
    }

    public function test_admin_cannot_update_user_to_another_users_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $user))
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $otherUser->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.users.edit', $user))
            ->assertSessionHasErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_admin_can_delete_another_user_with_soft_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'Tài khoản đã được chuyển vào thùng rác.');

        $this->assertSoftDeleted($user);
    }

    public function test_admin_cannot_delete_the_last_admin_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertModelExists($admin);
        $this->assertNull($admin->fresh()->deleted_at);
        $this->assertSame(1, User::query()->where('role', User::ROLE_ADMIN)->count());
    }

    public function test_admin_cannot_demote_the_last_admin_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
        $this->assertSame(1, User::query()->where('role', User::ROLE_ADMIN)->count());
    }
}
