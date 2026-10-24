<?php

namespace App\Support\Moderation;

use App\Models\ToxicWord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ToxicWordRepository
{
    /**
     * @return list<array{id: int, term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool}>
     */
    public function all(): array
    {
        if (! config('toxic_words.enabled')) {
            return [];
        }

        try {
            $terms = Cache::remember(
                config('toxic_words.cache_key'),
                config('toxic_words.cache_ttl'),
                fn (): array => tap(
                    ToxicWord::query()
                        ->active()
                        ->orderBy('id')
                        ->get(['id', 'term', 'normalized_term', 'scopes', 'match_mode', 'fold_diacritics'])
                        ->map(fn (ToxicWord $word): array => [
                            'id' => (int) $word->id,
                            'term' => $word->term,
                            'normalized_term' => $word->normalized_term,
                            'scopes' => $word->scopes,
                            'match_mode' => $word->match_mode,
                            'fold_diacritics' => $word->fold_diacritics,
                        ])
                        ->all(),
                    function (array $terms): void {
                        if (count($terms) > (int) config('toxic_words.max_terms')) {
                            Log::critical('toxic_words.dictionary_too_large', ['count' => count($terms)]);
                        }
                    },
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->baseline();
        }

        return $terms;
    }

    /** @return list<array{id: int, term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool}> */
    public function forScope(string $scope): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $term): bool => in_array($scope, $term['scopes'], true),
        ));
    }

    /** @return list<array{id: int, term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool}> */
    private function baseline(): array
    {
        Log::warning('toxic_words.fallback_to_baseline');

        $normalizer = app(ToxicWordNormalizer::class);

        return collect(config('toxic_words.baseline', []))
            ->map(function (mixed $entry, int $index) use ($normalizer): array {
                $term = is_array($entry) ? ($entry['term'] ?? null) : $entry;

                if (! is_string($term) || trim($term) === '') {
                    return [];
                }

                return [
                    'id' => $index + 1,
                    'term' => trim($term),
                    'normalized_term' => $normalizer->normalize($term),
                    'scopes' => is_array($entry) ? ($entry['scopes'] ?? config('toxic_words.scopes')) : config('toxic_words.scopes'),
                    'match_mode' => is_array($entry) ? ($entry['match_mode'] ?? ToxicWord::MATCH_WORD) : ToxicWord::MATCH_WORD,
                    'fold_diacritics' => is_array($entry) && (bool) ($entry['fold_diacritics'] ?? false),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
