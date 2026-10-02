<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_review_sources', function (Blueprint $table): void {
            $table->foreignId('review_id')->constrained('weekly_reviews')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('ai_articles')->restrictOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('ai_topics')->nullOnDelete();
            $table->boolean('is_representative')->default(false);
            $table->unsignedSmallInteger('citation_order')->nullable();
            $table->primary(['review_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_review_sources');
    }
};
