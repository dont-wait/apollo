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
        Schema::table('comments', function (Blueprint $table): void {
            $table->index(
                ['post_id', 'status', 'parent_id', 'created_at', 'id'],
                'comments_post_status_parent_cursor_index',
            );
            $table->index(
                ['parent_id', 'status', 'created_at', 'id'],
                'comments_parent_status_cursor_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_post_status_parent_cursor_index');
            $table->dropIndex('comments_parent_status_cursor_index');
        });
    }
};
