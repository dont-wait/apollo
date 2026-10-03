<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $unexpectedRoles = DB::table('users')
            ->whereNotIn('role', ['admin', 'user', 'ADMIN', 'USER'])
            ->distinct()
            ->pluck('role')
            ->all();

        if ($unexpectedRoles !== []) {
            throw new RuntimeException(sprintf(
                'Cannot normalize users.role. Unexpected legacy roles found: %s.',
                implode(', ', $unexpectedRoles),
            ));
        }

        DB::table('users')->where('role', 'admin')->update(['role' => 'ADMIN']);
        DB::table('users')->where('role', 'user')->update(['role' => 'USER']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['ADMIN', 'USER'])->default('USER')->change();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->string('avatar_url', 1000)->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->softDeletes();
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'avatar_url', 'last_login_at']);
            $table->string('role')->default('user')->change();
        });

        DB::table('users')->where('role', 'ADMIN')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'USER')->update(['role' => 'user']);
    }
};
