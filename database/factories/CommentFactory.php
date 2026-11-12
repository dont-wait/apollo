<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory()->published(),
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
            'status' => Comment::STATUS_VISIBLE,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['status' => 'HIDDEN']);
    }

    public function deleted(): static
    {
        return $this->state(['status' => 'DELETED']);
    }

    public function reply(Comment $parent): static
    {
        return $this->state([
            'post_id' => $parent->post_id,
            'parent_id' => $parent->id,
        ]);
    }
}
