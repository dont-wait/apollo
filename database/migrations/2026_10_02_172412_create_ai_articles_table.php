<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('ai_sources')->restrictOnDelete();
            $table->foreignId('collection_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->string('title', 500);
            $table->string('url', 2048);
            $table->string('canonical_url', 2048);
            $table->char('canonical_url_hash', 64)->unique();
            $table->char('title_fingerprint', 64)->nullable();
            $table->string('author')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('raw_summary')->nullable();
            $table->mediumText('raw_content')->nullable();
            $table->enum('status', ['COLLECTED', 'ANALYZED', 'FAILED'])->default('COLLECTED');
            $table->dateTime('collected_at');
            $table->index(['source_id', 'published_at']);
            $table->index(['published_at', 'status']);
            $table->index('title_fingerprint');
            $table->index('collection_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_articles');
    }
};
