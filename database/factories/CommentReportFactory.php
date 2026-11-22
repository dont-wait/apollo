<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommentReport> */
class CommentReportFactory extends Factory
{
    protected $model = CommentReport::class;

    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'reporter_id' => User::factory(),
            'reason' => 'OFFENSIVE',
            'status' => CommentReport::STATUS_OPEN,
        ];
    }
}
