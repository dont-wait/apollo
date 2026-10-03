<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->enum('run_type', ['COLLECT', 'ANALYZE', 'GENERATE_REVIEW', 'PUBLISH_SCHEDULED']);
            $table->enum('status', ['PENDING', 'RUNNING', 'SUCCESS', 'PARTIAL', 'FAILED'])->default('PENDING');
            $table->enum('trigger_type', ['SCHEDULED', 'MANUAL'])->default('SCHEDULED');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempt')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['run_type', 'status', 'created_at']);
            $table->index(['status', 'locked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
