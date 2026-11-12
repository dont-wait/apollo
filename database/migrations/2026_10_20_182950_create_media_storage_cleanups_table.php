<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_storage_cleanups', function (Blueprint $table) {
            $table->id();

            $table->string('disk', 64);
            $table->string('storage_key', 500);

            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamp('available_at')->index();

            $table->timestamps();

            $table->unique([
                'disk',
                'storage_key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_storage_cleanups');
    }
};