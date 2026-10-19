<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('id');
            $table->string('idempotency_key', 64)->nullable()->after('version');
            $table->char('idempotency_hash', 64)->nullable()->after('idempotency_key');
            $table->unique(['user_id', 'idempotency_key'], 'comments_user_id_idempotency_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('comments')->where('status', 'PENDING')->exists()) {
            throw new RuntimeException('Cannot roll back comment concurrency fields while PENDING comments still exist.');
        }

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropUnique('comments_user_id_idempotency_key_unique');
            $table->dropColumn(['version', 'idempotency_key', 'idempotency_hash']);
        });
    }
};
