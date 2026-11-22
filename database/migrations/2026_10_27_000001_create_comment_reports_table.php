<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->enum('reason', ['SPAM', 'OFFENSIVE', 'HARASSMENT', 'MISLEADING', 'OTHER']);
            $table->text('details')->nullable();
            $table->enum('status', ['OPEN', 'RESOLVED', 'DISMISSED'])->default('OPEN');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'reporter_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
    }
};
