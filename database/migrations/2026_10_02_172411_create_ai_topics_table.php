<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_topics');
    }
};
