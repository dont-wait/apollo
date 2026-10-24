<?php

namespace Tests\Unit;

use App\Support\Moderation\ToxicWordNormalizer;
use PHPUnit\Framework\TestCase;

class ToxicWordNormalizerTest extends TestCase
{
    public function test_normalize_lowercases_and_removes_invisible_characters(): void
    {
        $normalizer = new ToxicWordNormalizer;

        $this->assertSame('bad word', $normalizer->normalize("  BAD\u{200B} WORD  "));
    }

    public function test_normalize_can_fold_vietnamese_diacritics(): void
    {
        $normalizer = new ToxicWordNormalizer;

        $this->assertSame('day la tieng viet', $normalizer->normalize('Đây là Tiếng Việt', true));
        $this->assertSame('đây là tiếng việt', $normalizer->normalize('Đây là Tiếng Việt'));
    }

    public function test_normalize_preserves_line_breaks_when_whitespace_collapsing_is_disabled(): void
    {
        $normalizer = new ToxicWordNormalizer;

        $this->assertSame("first line\nsecond line", $normalizer->normalize("First line\nSecond line", false, false));
    }

    public function test_compact_removes_separators_and_maps_leet_characters(): void
    {
        $normalizer = new ToxicWordNormalizer;

        $this->assertSame('badword', $normalizer->compact('b4d-w0rd'));
    }

    public function test_compact_collapses_repeated_characters(): void
    {
        $normalizer = new ToxicWordNormalizer;

        $this->assertSame('badword', $normalizer->compact('baaadwooord'));
    }
}
