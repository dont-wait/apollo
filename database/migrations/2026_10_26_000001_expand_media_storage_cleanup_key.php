<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_storage_cleanups', function (Blueprint $table) {
            $table->string('storage_key', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('media_storage_cleanups', function (Blueprint $table) {
            $table->string('storage_key', 255)->change();
        });
    }
};
