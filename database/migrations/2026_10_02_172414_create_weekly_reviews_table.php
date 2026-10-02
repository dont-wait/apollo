<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reviews', function (Blueprint $table): void {
            $table->id();
            $table->date('week_start')->unique();
            $table->date('week_end');
            $table->string('title');
            $table->string('excerpt', 500)->nullable();
            $table->mediumText('markdown_content');
            $table->enum('status', ['DRAFT', 'REVIEW', 'CONVERTED', 'DISCARDED'])->default('DRAFT');
            $table->text('quality_warning')->nullable();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('suggested_tags')->nullable();
            $table->foreignId('generation_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->string('model_name', 100)->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reviews');
    }
};
