<?php

namespace App\Support;

use App\Models\Post;

class ArticleRevision
{
    private const array EDITABLE_COLUMNS = [
        'title', 'slug', 'category_id', 'thumbnail_media_id', 'excerpt',
        'markdown_content', 'status', 'seo_title', 'seo_description',
        'canonical_url', 'published_at', 'scheduled_at',
    ];

    public static function for(Post $article): string
    {
        // A freshly-created model may omit nullable/defaulted columns that
        // appear when the same row is reloaded from the database. Normalize
        // those columns so identical persisted articles have identical hashes.
        $attributes = array_replace(
            array_fill_keys(self::EDITABLE_COLUMNS, null),
            array_intersect_key($article->getRawOriginal(), array_flip(self::EDITABLE_COLUMNS)),
        );
        ksort($attributes);

        return hash('sha256', json_encode([
            'attributes' => $attributes,
            'tags' => $article->tags()->orderBy('tags.id')->pluck('tags.id')->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
