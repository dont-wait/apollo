<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('admin.email');
        $configuredPassword = config('admin.password');
        $admin = User::query()->where('email', $email)->first();

        if ($admin !== null) {
            if (! $admin->isAdmin()) {
                throw new RuntimeException("The configured admin email {$email} belongs to a non-admin user.");
            }

            return;
        }

        $generatedPassword = blank($configuredPassword);
        $password = $generatedPassword ? Str::random(32) : $configuredPassword;
        $admin = new User;
        $admin->name = config('admin.name');
        $admin->email = $email;
        $admin->password = $password;
        $admin->role = 'ADMIN';
        $admin->email_verified_at = now();
        $admin->save();

        if ($generatedPassword && $this->command !== null) {
            $this->command->info("Admin account created: {$email}");
            $this->command->info("Generated admin password: {$password}");
        }
    }
}
