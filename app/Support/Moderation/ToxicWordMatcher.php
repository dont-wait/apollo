<?php

namespace App\Support\Moderation;

use App\Models\ToxicWord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ToxicWordMatcher
{
    public function __construct(
        private ToxicWordNormalizer $normalizer,
        private ToxicWordRepository $repository,
    ) {}

    /** @return list<ToxicWordMatch> */
    public function find(string $text, string $scope): array
    {
        if (trim($text) === '' || ! config('toxic_words.enabled')) {
            return [];
        }

        $terms = $this->repository->forScope($scope);

        if ($terms === []) {
            return [];
        }

        $plainSubject = $this->normalizer->normalizeWithMap($text, false);
        $foldedSubject = null;
        $matches = [];

        foreach ([false, true] as $foldDiacritics) {
            $group = array_values(array_filter(
                $terms,
                fn (array $term): bool => $term['fold_diacritics'] === $foldDiacritics,
            ));

            if ($group === []) {
                continue;
            }

            $subject = $foldDiacritics
                ? ($foldedSubject ??= $this->normalizer->normalizeWithMap($text, true))
                : $plainSubject;

            $wordTerms = array_values(array_filter(
                $group,
                fn (array $term): bool => $term['match_mode'] === ToxicWord::MATCH_WORD,
            ));
            $looseTerms = array_values(array_filter(
                $group,
                fn (array $term): bool => $term['match_mode'] === ToxicWord::MATCH_LOOSE,
            ));

            if ($wordTerms !== []) {
                $pattern = $this->cachedPattern(
                    'word',
                    $wordTerms,
                    $foldDiacritics,
                    fn (): ?string => $this->wordPattern($wordTerms, $foldDiacritics),
                );

                if ($pattern !== null) {
                    $this->collect($pattern, $subject, $text, $matches);
                }
            }

            if ($looseTerms !== []) {
                $pattern = $this->cachedPattern(
                    'loose',
                    $looseTerms,
                    $foldDiacritics,
                    fn (): ?string => $this->loosePattern($looseTerms, $foldDiacritics),
                );

                if ($pattern !== null) {
                    $this->collect($pattern, $subject, $text, $matches);
                }
            }
        }

        $deduplicated = [];

        foreach ($matches as $match) {
            $deduplicated[$match->termId.'|'.($match->start ?? 'none').'|'.($match->end ?? 'none')] ??= $match;
        }

        $matches = array_values($deduplicated);

        usort($matches, fn (ToxicWordMatch $left, ToxicWordMatch $right): int => ($left->start ?? PHP_INT_MAX) <=> ($right->start ?? PHP_INT_MAX));

        return $matches;
    }

    /**
     * @param  list<array{id: int, term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool}>  $terms
     * @param  callable(): ?string  $builder
     */
    private function cachedPattern(string $type, array $terms, bool $foldDiacritics, callable $builder): ?string
    {
        $fingerprint = md5(json_encode(array_map(
            fn (array $term): array => [$term['id'], $term['term']],
            $terms,
        )));

        try {
            return Cache::remember(
                'toxic-words:pattern:'.$type.':'.(int) $foldDiacritics.':'.$fingerprint,
                config('toxic_words.cache_ttl'),
                $builder,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $builder();
        }
    }

    /** @param list<array{id: int, term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool}> $terms */
    private function wordPattern(array $terms, bool $foldDiacritics): ?string
    {
        $parts = [];

        usort($terms, fn (array $left, array $right): int => mb_strlen($right['term']) <=> mb_strlen($left['term']));

        foreach ($terms as $term) {
            $needle = $this->normalizer->normalize($term['term'], $foldDiacritics);

            if ($needle === '') {
                continue;
            }

            $quoted = $this->wordCharactersPattern($needle);
            $parts[] = '(?<t'.$term['id'].'>'.$quoted.')';
        }

        if ($parts === []) {
            return null;
        }

        return '~(?<![\p{L}\p{N}])(?:'.implode('|', $parts).')(?![\p{L}\p{N}])~u';
    }

    /** @param list<array{id: int, term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool}> $terms */
    private function loosePattern(array $terms, bool $foldDiacritics): ?string
    {
        $parts = [];

        usort($terms, fn (array $left, array $right): int => mb_strlen($right['term']) <=> mb_strlen($left['term']));

        foreach ($terms as $term) {
            $normalized = $this->normalizer->normalize($term['term'], $foldDiacritics);
            $characters = mb_str_split($this->normalizer->compact($normalized));

            if ($characters === []) {
                continue;
            }

            $characterPatterns = array_map(
                fn (string $character): string => $this->looseCharacterPattern($character).'+',
                $characters,
            );

            $parts[] = '(?<t'.$term['id'].'>'.implode('[\s\p{P}\p{S}]*', $characterPatterns).')';
        }

        if ($parts === []) {
            return null;
        }

        return '~'.implode('|', $parts).'~u';
    }

    private function wordCharactersPattern(string $needle): string
    {
        $pattern = '';

        foreach (mb_str_split($needle) as $character) {
            $pattern .= $character === ' ' ? '\s+' : preg_quote($character, '~');
        }

        return $pattern;
    }

    private function looseCharacterPattern(string $character): string
    {
        $characters = [$character, ...match ($character) {
            'a' => ['4', '@'],
            'e' => ['3'],
            'i' => ['1', '!'],
            'o' => ['0'],
            's' => ['5', '$'],
            't' => ['7'],
            default => [],
        }];

        return '['.implode('', array_map(
            fn (string $value): string => preg_quote($value, '~'),
            array_values(array_unique($characters)),
        )).']';
    }

    /**
     * @param  array{text: string, map: list<array{start: int, end: int, original_start: int, original_end: int}>}  $subject
     * @param  list<ToxicWordMatch>  $matches
     */
    private function collect(string $pattern, array $subject, string $original, array &$matches): void
    {
        if (preg_match_all($pattern, $subject['text'], $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            throw new ToxicWordMatcherException(
                'Toxic word pattern failed: '.preg_last_error_msg(),
            );
        }

        foreach ($found as $set) {
            foreach ($set as $group => $capture) {
                if (! is_string($group) || ! Str::startsWith($group, 't')) {
                    continue;
                }

                [$matched, $offset] = $capture;

                if ($matched === '') {
                    continue;
                }

                $range = $this->originalRange($subject['map'], $offset, $offset + strlen($matched));

                if ($range === null) {
                    continue;
                }

                [$start, $end] = $range;

                $matches[] = new ToxicWordMatch(
                    (int) substr($group, 1),
                    substr($original, $start, $end - $start),
                    $start,
                    $end,
                );
            }
        }
    }

    /**
     * @param  list<array{start: int, end: int, original_start: int, original_end: int}>  $map
     * @return array{0: int, 1: int}|null
     */
    private function originalRange(array $map, int $start, int $end): ?array
    {
        $originalStart = null;
        $originalEnd = null;

        foreach ($map as $range) {
            if ($range['end'] <= $start) {
                continue;
            }

            if ($range['start'] >= $end) {
                break;
            }

            $originalStart ??= $range['original_start'];
            $originalEnd = $range['original_end'];
        }

        return $originalStart === null ? null : [$originalStart, $originalEnd];
    }
}
