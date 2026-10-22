<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE comments MODIFY status ENUM('PENDING', 'VISIBLE', 'HIDDEN', 'DELETED') NOT NULL DEFAULT 'VISIBLE'");

        Schema::table('comments', function (Blueprint $table): void {
            $table->dateTime('edited_at')->nullable()->after('updated_at');
            $table->dateTime('deleted_at')->nullable()->after('edited_at');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->fullText('content', 'comments_content_fulltext_index');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->fullText('title', 'posts_title_fulltext_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->fullText('name', 'users_name_fulltext_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('comments')->where('status', 'PENDING')->exists()) {
            throw new RuntimeException('Cannot roll back comment lifecycle while PENDING comments still exist.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropFullText('users_name_fulltext_index');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropFullText('posts_title_fulltext_index');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropFullText('comments_content_fulltext_index');
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['edited_at', 'deleted_at', 'deleted_by']);
        });

        DB::statement("ALTER TABLE comments MODIFY status ENUM('PENDING', 'VISIBLE', 'HIDDEN', 'DELETED') NOT NULL DEFAULT 'PENDING'");
    }
};
