<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\SearchIndexOutbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncPostSearch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [5, 15, 60];

    public int $uniqueFor = 300;

    public function __construct(public int $postId) {}

    /**
     * Execute the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->postId;
    }

    public function handle(): void
    {
        $outbox = SearchIndexOutbox::query()->where('post_id', $this->postId)->first();

        if ($outbox === null) {
            return;
        }

        $version = $outbox->version;
        $post = Post::withTrashed()->find($this->postId);

        if ($post?->shouldBeSearchable()) {
            $post->searchableUsing()->update($post->newCollection([$post]));
        } elseif ($post !== null) {
            $post->searchableUsing()->delete($post->newCollection([$post]));
        } else {
            $deletedPost = new Post;
            $deletedPost->id = $this->postId;
            $deletedPost->searchableUsing()->delete($deletedPost->newCollection([$deletedPost]));
        }

        SearchIndexOutbox::query()
            ->where('post_id', $this->postId)
            ->where('version', $version)
            ->delete();
    }

    public function failed(?Throwable $exception): void
    {
        SearchIndexOutbox::query()
            ->where('post_id', $this->postId)
            ->update([
                'available_at' => now()->addMinute(),
                'locked_at' => null,
                'last_error' => $exception?->getMessage(),
                'updated_at' => now(),
            ]);
    }
}
