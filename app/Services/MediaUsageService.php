<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Post;
use App\Models\WeeklyReview;

class MediaUsageService
{
    public function isUsedAsThumbnail(Media $media): bool
    {
        return Post::withTrashed()
            ->where('thumbnail_media_id', $media->id)
            ->exists();
    }

    public function isUsedInMarkdown(Media $media): bool
    {
        $references = $this->markdownReferences($media);

        return Post::withTrashed()
            ->where(function ($query) use ($references): void {
                foreach ($references as $reference) {
                    $query->orWhere(
                        'markdown_content',
                        'like',
                        '%'.$reference.'%'
                    );
                }
            })
            ->exists()
            || WeeklyReview::query()
                ->where(function ($query) use ($references): void {
                    foreach ($references as $reference) {
                        $query->orWhere(
                            'markdown_content',
                            'like',
                            '%'.$reference.'%'
                        );
                    }
                })
                ->exists();
    }

    /**
     * @return list<string>
     */
    private function markdownReferences(Media $media): array
    {
        $urlPath = parse_url($media->url, PHP_URL_PATH);
        $storageKey = ltrim($media->storage_key, '/');

        return array_values(array_unique(array_filter([
            $media->url,
            is_string($urlPath) ? $urlPath : null,
            '/'.$storageKey,
            $storageKey,
        ], fn (?string $reference): bool => $reference !== null && $reference !== '')));
    }

    public function isInUse(Media $media): bool
    {
        return $this->isUsedAsThumbnail($media)
            || $this->isUsedInMarkdown($media);
    }
}
