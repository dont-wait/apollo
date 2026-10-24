<?php

namespace App\Support\Moderation;

use App\Models\ToxicWord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ToxicWordMatcher
{
    /** @var array<string, string> */
    private const array FOLD_VARIANTS = [
        'a' => 'aàáảãạăằắẳẵặâầấẩẫậ',
        'c' => 'cç',
        'd' => 'dđ',
        'e' => 'eèéẻẽẹêềếểễệ',
        'i' => 'iìíỉĩị',
        'n' => 'nñ',
        'o' => 'oòóỏõọôồốổỗộơờớởỡợø',
        's' => 'sš',
        'u' => 'uùúủũụưừứửữự',
        'y' => 'yỳýỷỹỵ',
        'z' => 'zž',
    ];

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

        $subject = $this->normalizer->normalize($text, false, false, false);
        $matches = [];

        foreach ([false, true] as $foldDiacritics) {
            $group = array_values(array_filter(
                $terms,
                fn (array $term): bool => $term['fold_diacritics'] === $foldDiacritics,
            ));

            if ($group === []) {
                continue;
            }

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
                    $this->collect($pattern, $subject, $matches);
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
                    $this->collect($pattern, $subject, $matches);
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

        return Cache::remember(
            'toxic-words:pattern:'.$type.':'.(int) $foldDiacritics.':'.$fingerprint,
            config('toxic_words.cache_ttl'),
            $builder,
        );
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

            $quoted = $this->wordCharactersPattern($needle, $foldDiacritics);
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
            $characters = mb_str_split(preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '');

            if ($characters === []) {
                continue;
            }

            $characterPatterns = array_map(
                fn (string $character): string => $this->looseCharacterPattern($character, $foldDiacritics).'+',
                $characters,
            );

            $parts[] = '(?<t'.$term['id'].'>'.implode('[\s\p{P}\p{S}]*', $characterPatterns).')';
        }

        if ($parts === []) {
            return null;
        }

        return '~'.implode('|', $parts).'~u';
    }

    private function wordCharactersPattern(string $needle, bool $foldDiacritics): string
    {
        $pattern = '';

        foreach (mb_str_split($needle) as $character) {
            if ($character === ' ') {
                $pattern .= '\s+';

                continue;
            }

            $pattern .= $foldDiacritics
                ? $this->foldedCharacterPattern($character)
                : preg_quote($character, '~');
        }

        return $pattern;
    }

    private function looseCharacterPattern(string $character, bool $foldDiacritics): string
    {
        $characters = [$character];

        if ($foldDiacritics) {
            $characters = mb_str_split(self::FOLD_VARIANTS[$character] ?? $character);
        }

        $characters = [...$characters, ...match ($character) {
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

    private function foldedCharacterPattern(string $character): string
    {
        $variants = self::FOLD_VARIANTS[$character] ?? null;

        if ($variants === null) {
            return preg_quote($character, '~');
        }

        return '['.implode('', array_map(
            fn (string $value): string => preg_quote($value, '~'),
            mb_str_split($variants),
        )).']';
    }

    /** @param list<ToxicWordMatch> $matches */
    private function collect(string $pattern, string $subject, array &$matches): void
    {
        if (preg_match_all($pattern, $subject, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return;
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

                $matches[] = new ToxicWordMatch(
                    (int) substr($group, 1),
                    $matched,
                    $offset,
                    $offset + strlen($matched),
                );
            }
        }
    }
}
