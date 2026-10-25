<?php

namespace Tests\Feature;

use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordFilter;
use App\Support\Moderation\ToxicWordMatcher;
use App\Support\Moderation\ToxicWordMatcherException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToxicWordMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_mode_matches_case_insensitively_at_unicode_boundaries(): void
    {
        $this->word('badword');

        $matches = $this->filter()->find('This is a BADWORD!', 'post');

        $this->assertCount(1, $matches);
        $this->assertSame('BADWORD', $matches[0]->matched);
        $this->assertSame(10, $matches[0]->start);
        $this->assertSame(17, $matches[0]->end);
        $this->assertSame([], $this->filter()->find('notbadwords', 'post'));
    }

    public function test_vietnamese_diacritics_are_preserved_by_default(): void
    {
        $this->word('các');

        $this->assertCount(1, $this->filter()->find('CÁC bạn', 'post'));
        $this->assertSame([], $this->filter()->find('cac ban', 'post'));
    }

    public function test_fold_diacritics_matches_unaccented_variants(): void
    {
        $this->word('các', foldDiacritics: true);

        $matches = $this->filter()->find('cac ban', 'post');

        $this->assertCount(1, $matches);
        $this->assertSame(0, $matches[0]->start);
        $this->assertSame(3, $matches[0]->end);
        $this->assertSame('cac', $matches[0]->matched);
    }

    public function test_fold_diacritics_highlights_accented_text_at_the_original_offsets(): void
    {
        $this->word('cac', foldDiacritics: true);

        $text = 'Xin chào các bạn';
        $match = $this->filter()->find($text, 'post')[0];

        $this->assertSame('các', substr($text, $match->start, $match->end - $match->start));
    }

    public function test_loose_mode_matches_separators_and_leet_speak(): void
    {
        $this->word('badword', mode: ToxicWord::MATCH_LOOSE);

        $this->assertCount(1, $this->filter()->find('b4d w0rd', 'post'));
        $this->assertCount(1, $this->filter()->find('bad-word', 'post'));
    }

    public function test_terms_only_apply_to_their_configured_scopes(): void
    {
        $this->word('badword', scopes: ['post']);

        $this->assertCount(1, $this->filter()->find('badword', 'post'));
        $this->assertSame([], $this->filter()->find('badword', 'comment'));
    }

    public function test_inactive_terms_are_ignored_and_cache_is_cleared(): void
    {
        $word = $this->word('badword');

        $this->assertCount(1, $this->filter()->find('badword', 'post'));

        $word->update(['is_active' => false]);

        $this->assertSame([], $this->filter()->find('badword', 'post'));
    }

    public function test_matches_can_be_used_to_slice_the_original_text(): void
    {
        $this->word('badword');

        $text = '  say badword now';
        $match = $this->filter()->find($text, 'post')[0];

        $this->assertSame(6, $match->start);
        $this->assertSame('badword', substr($text, $match->start, $match->end - $match->start));
    }

    public function test_offsets_survive_invisible_characters_and_folding(): void
    {
        $this->word('các', foldDiacritics: true);

        $text = "Xin chào\u{200B} các bạn";
        $match = $this->filter()->find($text, 'post')[0];

        $this->assertSame('các', substr($text, $match->start, $match->end - $match->start));
        $this->assertSame('các', $match->matched);
    }

    public function test_folding_uses_the_same_transliteration_as_the_normalizer(): void
    {
        $this->word('über', foldDiacritics: true);

        $match = $this->filter()->find('UBER', 'post')[0];

        $this->assertSame('UBER', substr('UBER', $match->start, $match->end - $match->start));
    }

    public function test_loose_repeated_letter_runs_do_not_exhaust_pcre(): void
    {
        $this->word('baadword', mode: ToxicWord::MATCH_LOOSE);

        $matches = $this->filter()->find('ba'.str_repeat('a', 30000), 'post');

        $this->assertSame([], $matches);
    }

    public function test_matcher_fails_closed_when_pattern_matching_fails(): void
    {
        $matcher = $this->createMock(ToxicWordMatcher::class);
        $matcher->expects($this->once())
            ->method('find')
            ->willThrowException(new ToxicWordMatcherException('Pattern failed.'));

        $matches = (new ToxicWordFilter($matcher))->find('badword', 'post');

        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]->isFailure());
        $this->assertSame(0, $matches[0]->termId);
        $this->assertNull($matches[0]->start);
    }

    private function word(
        string $term,
        array $scopes = ['post'],
        string $mode = ToxicWord::MATCH_WORD,
        bool $foldDiacritics = false,
    ): ToxicWord {
        return ToxicWord::factory()->create([
            'term' => $term,
            'normalized_term' => mb_strtolower($term),
            'scopes' => $scopes,
            'match_mode' => $mode,
            'fold_diacritics' => $foldDiacritics,
            'is_active' => true,
        ]);
    }

    private function filter(): ToxicWordFilter
    {
        return app(ToxicWordFilter::class);
    }
}
