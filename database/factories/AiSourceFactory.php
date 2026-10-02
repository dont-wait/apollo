<?php

namespace Database\Factories;

use App\Models\AiSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiSource> */
class AiSourceFactory extends Factory
{
    protected $model = AiSource::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return ['name' => fake()->unique()->company(), 'source_type' => 'RSS', 'site_url' => 'https://'.$domain, 'feed_url' => 'https://'.$domain.'/feed.xml', 'trust_score' => 5, 'priority' => 5, 'status' => 'ACTIVE'];
    }
}
