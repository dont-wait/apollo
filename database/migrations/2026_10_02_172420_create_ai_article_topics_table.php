<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_article_topics', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained('ai_articles')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('ai_topics')->cascadeOnDelete();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->primary(['article_id', 'topic_id']);
        });

        DB::statement('ALTER TABLE ai_article_topics ADD CONSTRAINT ai_article_topics_confidence_check CHECK (confidence IS NULL OR (confidence BETWEEN 0 AND 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_article_topics');
    }
};
