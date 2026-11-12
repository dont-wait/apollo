<?php

namespace App\Support\Moderation;

use Illuminate\Support\Str;
use Normalizer;

class ToxicWordNormalizer
{
    private const string INVISIBLE_CHARACTERS = '/[\x{200B}-\x{200D}\x{FEFF}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /** @var array<string, string> */
    private const array LEET_MAP = [
        '0' => 'o',
        '1' => 'i',
        '3' => 'e',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
        '@' => 'a',
        '$' => 's',
    ];

    public function normalize(string $text, bool $foldDiacritics = false, bool $collapseWhitespace = true, bool $trim = true): string
    {
        $text = $this->removeInvisibleCharacters($text);

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        $text = mb_strtolower($text, 'UTF-8');

        if ($collapseWhitespace) {
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        }

        if ($trim) {
            $text = trim($text);
        }

        if ($foldDiacritics) {
            $text = Str::ascii($text);

            if ($collapseWhitespace) {
                $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            }

            if ($trim) {
                $text = trim($text);
            }
        }

        return $text;
    }

    /**
     * Normalize text while keeping a map from normalized byte ranges back to
     * the original byte ranges, so matches can be highlighted in the source.
     *
     * @return array{text: string, map: list<array{start: int, end: int, original_start: int, original_end: int}>}
     */
    public function normalizeWithMap(string $text, bool $foldDiacritics = false): array
    {
        $matched = preg_match_all('/\X/u', $text, $clusters);
        $clusters = $matched === false ? [$text] : $clusters[0];

        $normalized = '';
        $map = [];
        $originalOffset = 0;

        foreach ($clusters as $cluster) {
            $originalStart = $originalOffset;
            $originalOffset += strlen($cluster);

            $value = $this->removeInvisibleCharacters($cluster);

            if ($value === '') {
                continue;
            }

            if (class_exists(Normalizer::class)) {
                $normalizedCluster = Normalizer::normalize($value, Normalizer::FORM_C);

                if (is_string($normalizedCluster)) {
                    $value = $normalizedCluster;
                }
            }

            $value = mb_strtolower($value, 'UTF-8');

            if ($foldDiacritics) {
                $value = Str::ascii($value);
            }

            if ($value === '') {
                continue;
            }

            $start = strlen($normalized);
            $normalized .= $value;
            $map[] = [
                'start' => $start,
                'end' => strlen($normalized),
                'original_start' => $originalStart,
                'original_end' => $originalOffset,
            ];
        }

        return ['text' => $normalized, 'map' => $map];
    }

    public function compact(string $text): string
    {
        $text = strtr($text, self::LEET_MAP);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '', $text) ?? '';

        return preg_replace('/(\p{L}|\p{N})\1+/u', '$1', $text) ?? $text;
    }

    private function removeInvisibleCharacters(string $text): string
    {
        return preg_replace(self::INVISIBLE_CHARACTERS, '', $text) ?? $text;
    }
}
