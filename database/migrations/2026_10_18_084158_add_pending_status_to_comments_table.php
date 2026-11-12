<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE comments MODIFY status ENUM('PENDING', 'VISIBLE', 'HIDDEN', 'DELETED') NOT NULL DEFAULT 'PENDING'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('comments')->where('status', 'PENDING')->exists()) {
            throw new RuntimeException('Cannot roll back comment pending status while PENDING comments still exist.');
        }

        DB::statement("ALTER TABLE comments MODIFY status ENUM('VISIBLE', 'HIDDEN', 'DELETED') NOT NULL DEFAULT 'VISIBLE'");
    }
};
