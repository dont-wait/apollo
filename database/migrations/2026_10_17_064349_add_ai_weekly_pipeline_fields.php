<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_sources', function (Blueprint $table) {
            $table->string('adapter_key', 100)->nullable();
        });
        Schema::table('ai_articles', function (Blueprint $table) {
            $table->foreignId('duplicate_of')->nullable()->constrained('ai_articles')->nullOnDelete();
        });
        Schema::table('weekly_reviews', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_reviews', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
        Schema::table('ai_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of');
        });
        Schema::table('ai_sources', function (Blueprint $table) {
            $table->dropColumn('adapter_key');
        });
    }
};
