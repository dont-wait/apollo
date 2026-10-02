<?php

namespace Database\Factories;

use App\Models\AiTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiTopic> */
class AiTopicFactory extends Factory
{
    protected $model = AiTopic::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['name' => $name, 'slug' => Str::slug($name), 'status' => 'ACTIVE'];
    }
}
