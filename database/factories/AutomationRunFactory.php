<?php

namespace Database\Factories;

use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AutomationRun> */
class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    public function definition(): array
    {
        return ['run_type' => 'COLLECT', 'status' => 'PENDING', 'trigger_type' => 'SCHEDULED', 'idempotency_key' => fake()->unique()->uuid(), 'payload' => [], 'attempt' => 0, 'max_attempts' => 3, 'created_at' => now()];
    }
}
