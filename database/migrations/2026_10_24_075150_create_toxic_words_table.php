<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toxic_words', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 100);
            $table->string('normalized_term', 100)->unique();
            $table->json('scopes');
            $table->string('match_mode', 10)->default('WORD');
            $table->boolean('fold_diacritics')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toxic_words');
    }
};
