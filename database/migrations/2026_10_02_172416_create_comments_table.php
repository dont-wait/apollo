<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->text('content');
            $table->enum('status', ['VISIBLE', 'HIDDEN', 'DELETED'])->default('VISIBLE');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('moderated_at')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at']);
            $table->index('parent_id');
            $table->index('user_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
