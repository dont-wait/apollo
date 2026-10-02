<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->enum('source_type', ['RSS', 'API', 'WEBSITE'])->default('RSS');
            $table->string('site_url', 500);
            $table->string('feed_url', 1000)->nullable();
            $table->unsignedTinyInteger('trust_score')->default(5);
            $table->unsignedTinyInteger('priority')->default(5);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->dateTime('last_fetched_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        DB::statement('ALTER TABLE ai_sources ADD CONSTRAINT ai_sources_trust_score_check CHECK (trust_score BETWEEN 1 AND 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sources');
    }
};
