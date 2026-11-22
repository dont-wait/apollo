<?php

return [
    'sitemap_chunk_size' => 5000,

    // Exact hostnames (not regex). When empty, the APP_URL hostname is trusted.
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SEO_TRUSTED_HOSTS', '')),
    ))),
];
