<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('search_index_outbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->unique();
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_index_outbox');
    }
};
