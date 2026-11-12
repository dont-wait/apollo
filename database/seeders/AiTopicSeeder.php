<?php

namespace Database\Seeders;

use App\Models\AiTopic;
use Illuminate\Database\Seeder;

class AiTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('ai_weekly.topics') as $slug => $name) {
            AiTopic::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'status' => 'ACTIVE']);
        }
    }
}
