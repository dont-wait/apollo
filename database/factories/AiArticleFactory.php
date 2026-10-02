<?php

namespace Database\Factories;

use App\Models\AiArticle;
use App\Models\AiSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiArticle> */
class AiArticleFactory extends Factory
{
    protected $model = AiArticle::class;

    public function definition(): array
    {
        $url = fake()->unique()->url();

        return ['source_id' => AiSource::factory(), 'title' => fake()->sentence(), 'url' => $url, 'canonical_url' => $url, 'canonical_url_hash' => hash('sha256', $url), 'title_fingerprint' => hash('sha256', Str::lower(fake()->sentence())), 'author' => fake()->name(), 'published_at' => now()->subDays(fake()->numberBetween(0, 14)), 'raw_summary' => fake()->paragraph(), 'raw_content' => fake()->paragraphs(3, true), 'status' => 'COLLECTED', 'collected_at' => now()];
    }
}
