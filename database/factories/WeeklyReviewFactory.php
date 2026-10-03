<?php

namespace Database\Factories;

use App\Models\WeeklyReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeeklyReview> */
class WeeklyReviewFactory extends Factory
{
    protected $model = WeeklyReview::class;

    public function definition(): array
    {
        $start = now()->subWeeks(fake()->unique()->numberBetween(1, 52))->startOfWeek();

        return ['week_start' => $start->toDateString(), 'week_end' => $start->copy()->endOfWeek()->toDateString(), 'title' => 'AI Weekly: '.fake()->sentence(4), 'excerpt' => fake()->sentence(), 'markdown_content' => fake()->paragraphs(4, true), 'status' => 'DRAFT', 'suggested_tags' => [fake()->word(), fake()->word()]];
    }
}
