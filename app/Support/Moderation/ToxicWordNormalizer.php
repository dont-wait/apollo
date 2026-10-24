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

    public function compact(string $text): string
    {
        $text = strtr($text, self::LEET_MAP);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '', $text) ?? '';

        return preg_replace('/(\p{L}|\p{N})\1{2,}/u', '$1', $text) ?? $text;
    }

    private function removeInvisibleCharacters(string $text): string
    {
        return preg_replace(self::INVISIBLE_CHARACTERS, '', $text) ?? $text;
    }
}
