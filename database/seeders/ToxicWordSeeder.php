<?php

namespace Database\Seeders;

use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordNormalizer;
use Illuminate\Database\Seeder;

class ToxicWordSeeder extends Seeder
{
    public function __construct(private ToxicWordNormalizer $normalizer) {}

    public function run(): void
    {
        ToxicWord::withoutEvents(function (): void {
            foreach (config('toxic_words.baseline', []) as $entry) {
                $term = is_array($entry) ? ($entry['term'] ?? null) : $entry;

                if (! is_string($term) || trim($term) === '') {
                    continue;
                }

                $normalized = $this->normalizer->normalize($term);
                $isArray = is_array($entry);

                ToxicWord::query()->firstOrCreate(
                    ['normalized_term' => $normalized],
                    [
                        'term' => trim($term),
                        'scopes' => $isArray ? ($entry['scopes'] ?? config('toxic_words.scopes')) : config('toxic_words.scopes'),
                        'match_mode' => $isArray ? ($entry['match_mode'] ?? ToxicWord::MATCH_WORD) : ToxicWord::MATCH_WORD,
                        'fold_diacritics' => $isArray && (bool) ($entry['fold_diacritics'] ?? false),
                        'is_active' => $isArray ? ($entry['is_active'] ?? true) : true,
                        'note' => $isArray ? ($entry['note'] ?? null) : null,
                    ],
                );
            }
        });

        ToxicWord::invalidateCache();
    }
}
