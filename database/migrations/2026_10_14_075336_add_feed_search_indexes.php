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
        Schema::table('categories', function (Blueprint $table): void {
            $table->fullText('name');
            $table->index('name');
        });
        Schema::table('tags', function (Blueprint $table): void {
            $table->fullText('name');
            $table->index('name');
        });
        Schema::table('posts', function (Blueprint $table): void {
            $table->index('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropFullText(['name']);
            $table->dropIndex(['name']);
        });
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropFullText(['name']);
            $table->dropIndex(['name']);
        });
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['title']);
        });
    }
};
