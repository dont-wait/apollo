<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordHighlighter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
        $original = $post->fresh()->getAttributes();

        $this->assertSame(0, Artisan::call('toxic-words:scan', ['--scope' => ['post'], '--limit' => 10]));

        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/\|\s*post\s*\|\s*title\s*\|\s*\d+\s*\|\s*badword\s*\|/', $output);

        $fresh = $post->fresh()->getAttributes();
        ksort($original);
        ksort($fresh);

        $this->assertSame($original, $fresh);
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
