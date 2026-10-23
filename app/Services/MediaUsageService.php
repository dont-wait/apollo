<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Post;

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
        return Post::withTrashed()
            ->where('markdown_content', 'like', '%'.$media->url.'%')
            ->exists();
    }

    public function isInUse(Media $media): bool
    {
        return $this->isUsedAsThumbnail($media)
            || $this->isUsedInMarkdown($media);
    }
}
