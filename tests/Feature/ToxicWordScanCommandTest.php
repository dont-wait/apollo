<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordHighlighter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToxicWordScanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_command_reports_matches_without_changing_content(): void
    {
        ToxicWord::factory()->create([
            'term' => 'badword',
            'normalized_term' => 'badword',
            'scopes' => ['post'],
        ]);
        $post = Post::factory()->create([
            'title' => 'A badword title',
            'markdown_content' => 'Clean body',
        ]);

        $this->artisan('toxic-words:scan', ['--scope' => ['post'], '--limit' => 10])
            ->expectsOutputToContain('badword')
            ->assertSuccessful();

        $this->assertSame('A badword title', $post->fresh()->title);
    }

    public function test_highlighter_sanitizes_markdown_and_marks_matches(): void
    {
        ToxicWord::factory()->create([
            'term' => 'badword',
            'normalized_term' => 'badword',
            'scopes' => ['post'],
        ]);

        $html = app(ToxicWordHighlighter::class)->highlightMarkdown(
            "This is **badword**.\n\n<script>alert(1)</script>",
            'post',
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('toxic-word-hit', $html);
    }
}
