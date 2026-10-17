<?php

return [
    'timezone' => env('AI_WEEKLY_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'max_attempts' => (int) env('AI_WEEKLY_MAX_ATTEMPTS', 3),
    'http_timeout' => (int) env('AI_WEEKLY_HTTP_TIMEOUT', 30),
    'model_timeout' => (int) env('AI_WEEKLY_MODEL_TIMEOUT', 60),
    'max_feed_bytes' => (int) env('AI_WEEKLY_MAX_FEED_BYTES', 5000000),
    'max_items_per_source' => (int) env('AI_WEEKLY_MAX_ITEMS_PER_SOURCE', 200),
    'tracking_query_prefixes' => ['utm_'],
    'retry_delay_minutes' => (int) env('AI_WEEKLY_RETRY_DELAY_MINUTES', 5),
    'stale_minutes' => (int) env('AI_WEEKLY_STALE_MINUTES', 20),
    'collect_schedule' => env('AI_WEEKLY_COLLECT_SCHEDULE', '0 */6 * * *'),
    'analyze_schedule' => env('AI_WEEKLY_ANALYZE_SCHEDULE', '30 */6 * * *'),
    'review_schedule' => env('AI_WEEKLY_REVIEW_SCHEDULE', '0 8 * * 1'),
    'minimum_articles' => (int) env('AI_WEEKLY_MIN_ARTICLES', 5),
    'minimum_sources' => (int) env('AI_WEEKLY_MIN_SOURCES', 2),
    'max_stories' => (int) env('AI_WEEKLY_MAX_STORIES', 10),
    'representatives_per_topic' => (int) env('AI_WEEKLY_REPRESENTATIVES_PER_TOPIC', 3),
    'model' => env('AI_WEEKLY_MODEL'),
    'model_url' => env('AI_WEEKLY_MODEL_URL'),
    'model_key' => env('AI_WEEKLY_MODEL_KEY'),
    'prompt_version' => '1',
    'api_adapters' => [
        'generic_json' => [
            'headers' => env('AI_WEEKLY_GENERIC_API_TOKEN') ? ['Authorization' => 'Bearer '.env('AI_WEEKLY_GENERIC_API_TOKEN')] : [],
        ],
    ],
    'topics' => [
        'llm' => 'LLM',
        'agents' => 'Agents',
        'robotics' => 'Robotics',
        'ai-coding' => 'AI Coding',
        'regulation' => 'Regulation',
        'research' => 'Research',
    ],
];
