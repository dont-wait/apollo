<?php

namespace Database\Factories;

use App\Models\AiArticle;
use App\Models\AiArticleAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiArticleAnalysis> */
class AiArticleAnalysisFactory extends Factory
{
    protected $model = AiArticleAnalysis::class;

    public function definition(): array
    {
        return ['article_id' => AiArticle::factory(), 'version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'model_name' => 'test-model', 'prompt_version' => 'v1', 'summary' => fake()->paragraph(), 'key_points' => [fake()->sentence(), fake()->sentence()], 'technologies' => [fake()->word()], 'raw_output' => ['summary' => fake()->sentence()]];
    }
}
