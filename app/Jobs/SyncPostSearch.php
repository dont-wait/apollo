<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPostSearch implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [5, 15, 60];

    public function __construct(public int $postId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $post = Post::withTrashed()->find($this->postId);
        if ($post === null) {
            return;
        }

        if ($post->shouldBeSearchable()) {
            $post->searchableUsing()->update($post->newCollection([$post]));
        } else {
            $post->searchableUsing()->delete($post->newCollection([$post]));
        }
    }
}
