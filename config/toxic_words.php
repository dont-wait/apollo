<?php

return [
    'enabled' => env('TOXIC_WORDS_ENABLED', true),

    'cache_key' => 'toxic-words:active:v1',

    'cache_ttl' => 600,

    'max_terms' => 5000,

    'scopes' => ['post', 'tag', 'category', 'comment', 'user_name'],

    /*
    |--------------------------------------------------------------------------
    | Curated baseline dictionary
    |--------------------------------------------------------------------------
    |
    | Used by ToxicWordSeeder and as a read-only fallback when the database
    | cannot be reached. Entries use every scope and WORD matching by default;
    | use an array entry to override scopes, match_mode, or fold_diacritics.
    |
    | Vietnamese terms keep their diacritics by default to avoid collisions
    | with unrelated unaccented words. Common unaccented/teencode variants are
    | listed explicitly instead. Admins can disable or tune any entry in the UI.
    |
    */
    'baseline' => [
        // Vietnamese profanity and common teencode variants.
        'địt',
        'đjt',
        'djt',
        'đụ',
        'đéo',
        'đĩ',
        'điếm',
        'lồn',
        'loz',
        'cặc',
        'cak',
        'buồi',
        'cứt',
        'đm',
        'đmm',
        'đkm',
        'dcm',
        'dmm',
        'dkm',
        'vcl',
        'clm',
        'clgt',
        'mẹ mày',
        'má mày',
        'mẹ kiếp',

        // Vietnamese insults and harassment.
        'ngu',
        'đần',
        'chó đẻ',
        'chó chết',
        'thằng chó',
        'đồ chó',
        'óc chó',
        'não chó',
        'súc sinh',
        'súc vật',
        'cầm thú',
        'cặn bã',
        'rác rưởi',
        'mất dạy',
        'vô học',
        'vô giáo dục',
        'khốn nạn',
        'khốn khiếp',
        'đồ khốn',
        'thằng khốn',
        'con khốn',
        'đồ điên',
        'thằng điên',
        'con điên',
        'câm mồm',
        'cút đi',
        'xéo đi',
        'biến đi',

        // English profanity.
        'fuck',
        'fucking',
        'fucked',
        'fucker',
        'motherfucker',
        'motherfucking',
        'shit',
        'shitty',
        'bullshit',
        'bitch',
        'bitches',
        'bastard',
        'asshole',
        'arsehole',
        'dick',
        'dickhead',
        'cock',
        'cunt',
        'pussy',
        'whore',
        'slut',
        'twat',
        'wanker',
        'douchebag',
        'jackass',
        'dumbass',
        'dipshit',

        // Ableist and identity-based slurs.
        'retard',
        'retarded',
        'nigger',
        'nigga',
        'faggot',
        'fag',
        'chink',
        'gook',
        'spic',
        'kike',
        'wetback',
        'tranny',
        'dyke',

        // Harassment phrases.
        'kys',
        'stfu',
        'kill yourself',
        'piece of shit',
        'son of a bitch',
    ],
];
