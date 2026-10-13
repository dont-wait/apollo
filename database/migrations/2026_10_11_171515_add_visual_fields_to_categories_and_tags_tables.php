<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color', 7)
                ->nullable()
                ->after('description');

            $table->string('icon', 50)
                ->nullable()
                ->after('color');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->string('color', 7)
                ->nullable()
                ->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'color',
                'icon',
            ]);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
