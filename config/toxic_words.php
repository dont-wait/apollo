<?php

return [
    'enabled' => env('TOXIC_WORDS_ENABLED', true),

    'cache_key' => 'toxic-words:active:v1',

    'cache_ttl' => 600,

    'max_terms' => 5000,

    'scopes' => ['post', 'tag', 'category', 'comment', 'user_name'],

    'baseline' => [
        // Add curated baseline terms when the editorial team approves a source list.
        // Keep the actual list out of source code and seed it through the database.
    ],
];
