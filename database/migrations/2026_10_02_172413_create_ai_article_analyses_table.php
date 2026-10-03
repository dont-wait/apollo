<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_article_analysis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('ai_articles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('is_current')->default(false);
            $table->enum('status', ['SUCCESS', 'FAILED']);
            $table->string('model_name', 100)->nullable();
            $table->string('prompt_version', 50)->nullable();
            $table->text('summary')->nullable();
            $table->json('key_points')->nullable();
            $table->json('technologies')->nullable();
            $table->json('companies')->nullable();
            $table->json('opportunities')->nullable();
            $table->json('risks')->nullable();
            $table->json('raw_output')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['article_id', 'version']);
            $table->index(['article_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_article_analysis');
    }
};
