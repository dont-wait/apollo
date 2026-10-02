<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('weekly_review_id')->nullable()->unique()->constrained('weekly_reviews')->nullOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->mediumText('markdown_content');
            $table->enum('status', ['DRAFT', 'REVIEW', 'SCHEDULED', 'PUBLISHED', 'ARCHIVED'])->default('DRAFT');
            $table->enum('source_type', ['MANUAL', 'AI_WEEKLY'])->default('MANUAL');
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'deleted_at', 'published_at']);
            $table->index(['category_id', 'status', 'deleted_at', 'published_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index('author_id');
            $table->fullText(['title', 'excerpt', 'markdown_content']);
        });

        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_published_at_check CHECK (status <> 'PUBLISHED' OR published_at IS NOT NULL)");
        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_scheduled_at_check CHECK (status <> 'SCHEDULED' OR scheduled_at IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
