<?php

use App\Models\Post;

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'prefix' => env('SCOUT_PREFIX', 'apollo_'),
    'queue' => env('SCOUT_QUEUE', true),
    'jobs' => ['tries' => 4, 'backoff' => 10],
    'after_commit' => true,
    'chunk' => ['searchable' => 100, 'unsearchable' => 100],
    'soft_delete' => false,
    'integration_tests' => env('MEILISEARCH_INTEGRATION_TESTS', false),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY', 'apollo-local-search-key'),
        'index-settings' => [
            Post::class => [
                'searchableAttributes' => ['title', 'tags', 'category', 'excerpt', 'content', 'slug'],
                'displayedAttributes' => ['id'],
                'filterableAttributes' => ['is_published', 'published_at', 'category_active', 'category_slug'],
                'sortableAttributes' => ['published_at', 'bookmarks_count', 'reading_minutes', 'id'],
                'typoTolerance' => ['enabled' => true],
                'rankingRules' => ['sort', 'words', 'typo', 'proximity', 'attribute', 'exactness'],
            ],
        ],
    ],
];
