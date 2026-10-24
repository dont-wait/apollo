<?php

namespace Tests\Feature;

use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordFilter;
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
        $this->assertSame('badword', $matches[0]->matched);
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
