<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostPublicationService
{
    /**
     * @return array{published: int, failed: int}
     */
    public function publishDue(CarbonInterface $cutoff, int $limit = 100): array
    {
        $ids = Post::query()
            ->where('status', PostStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $cutoff)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $published = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $didPublish = DB::transaction(function () use ($id, $cutoff): bool {
                    $post = Post::query()
                        ->lockForUpdate()
                        ->find($id);

                    if ($post === null
                        || $post->status !== PostStatus::Scheduled->value
                        || $post->scheduled_at === null
                        || $post->scheduled_at->isAfter($cutoff)) {
                        return false;
                    }

                    $post->update([
                        'status' => PostStatus::Published->value,
                        'published_at' => $cutoff,
                    ]);

                    return true;
                }, 3);

                if ($didPublish) {
                    $published++;
                }
            } catch (Throwable $exception) {
                $failed++;

                Log::error('Scheduled post publish failed; it remains scheduled for retry.', [
                    'post_id' => (int) $id,
                    'exception' => $exception,
                ]);
            }
        }

        return compact('published', 'failed');
    }
}
