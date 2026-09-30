<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_seeder_creates_a_configured_admin_account(): void
    {
        Config::set([
            'admin.email' => 'seed-admin@example.com',
            'admin.password' => 'admin-password',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'seed-admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('admin-password', $admin->password));
    }

    public function test_admin_seeder_does_not_promote_an_existing_regular_user(): void
    {
        User::factory()->create([
            'email' => 'seed-admin@example.com',
            'role' => 'user',
        ]);
        Config::set('admin.email', 'seed-admin@example.com');

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('The admin seeder should reject an existing regular user.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The configured admin email seed-admin@example.com belongs to a non-admin user.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('users', [
            'email' => 'seed-admin@example.com',
            'role' => 'user',
        ]);
    }
}
