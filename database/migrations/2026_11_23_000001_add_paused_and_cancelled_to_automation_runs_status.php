<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE automation_runs MODIFY status ENUM('PENDING', 'RUNNING', 'PAUSED', 'SUCCESS', 'PARTIAL', 'FAILED', 'CANCELLED') NOT NULL DEFAULT 'PENDING'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('automation_runs')->whereIn('status', ['PAUSED', 'CANCELLED'])->exists()) {
            throw new RuntimeException('Cannot roll back while PAUSED or CANCELLED automation runs still exist.');
        }

        DB::statement("ALTER TABLE automation_runs MODIFY status ENUM('PENDING', 'RUNNING', 'SUCCESS', 'PARTIAL', 'FAILED') NOT NULL DEFAULT 'PENDING'");
    }
};
