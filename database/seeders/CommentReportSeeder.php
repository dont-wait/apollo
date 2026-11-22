<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentReportSeeder extends Seeder
{
    /**
     * Seed a few open reports on existing visible comments so the admin report page has data in development.
     */
    public function run(): void
    {
        $reporter = User::query()->where('email', 'test@example.com')->first();

        if ($reporter === null) {
            return;
        }

        Comment::query()
            ->where('status', Comment::STATUS_VISIBLE)
            ->where('user_id', '!=', $reporter->id)
            ->limit(3)
            ->get()
            ->each(fn (Comment $comment) => CommentReport::query()->firstOrCreate(
                ['comment_id' => $comment->id, 'reporter_id' => $reporter->id],
                ['reason' => 'OFFENSIVE', 'details' => 'Sample report for development.', 'status' => CommentReport::STATUS_OPEN],
            ));
    }
}
