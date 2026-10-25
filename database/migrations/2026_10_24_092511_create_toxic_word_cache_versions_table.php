<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toxic_word_cache_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        DB::table('toxic_word_cache_versions')->insert([
            'id' => 1,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('toxic_word_cache_versions');
    }
};
