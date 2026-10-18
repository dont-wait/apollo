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
        DB::statement("UPDATE comments SET status = 'VISIBLE' WHERE status = 'PENDING'");
        DB::statement("ALTER TABLE comments MODIFY status ENUM('VISIBLE', 'HIDDEN', 'DELETED') NOT NULL DEFAULT 'VISIBLE'");
    }
};
