<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return ['author_id' => User::factory(), 'category_id' => Category::factory(), 'title' => $title, 'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999), 'excerpt' => fake()->paragraph(), 'markdown_content' => '# '.$title."\n\n".fake()->paragraphs(4, true), 'status' => 'DRAFT', 'source_type' => 'MANUAL', 'view_count' => 0, 'like_count' => 0];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'PUBLISHED', 'published_at' => now()]);
    }
}
