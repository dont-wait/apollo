<?php

namespace App\AiWeekly;

use App\Models\AiArticle;
use App\Models\WeeklyReview;
use RuntimeException;

class SectionEditor
{
    public const HEADINGS = [
        'overview' => '## Tổng quan tuần',
        'top_stories' => '## Tin nổi bật',
        'research' => '## Nghiên cứu đáng chú ý',
        'topics_trends' => '## Chủ đề và xu hướng',
        'opportunities' => '## Cơ hội',
        'risks_limits' => '## Rủi ro và giới hạn',
    ];

    public function validStructure(string $markdown): bool
    {
        $previous = -1;
        foreach (self::HEADINGS as $heading) {
            if (substr_count($markdown, $heading) !== 1) {
                return false;
            }
            $position = strpos($markdown, $heading);
            if ($position <= $previous) {
                return false;
            }
            $previous = $position;
        }

        return true;
    }

    /** @return array{markdown: string, citation_ids: array<int, int>} */
    public function replace(WeeklyReview $review, string $section, string $replacement): array
    {
        $heading = self::HEADINGS[$section] ?? throw new RuntimeException('Unknown section.');
        $this->validateReplacement($replacement);
        $body = preg_split('/^## Nguồn tham khảo\s*$/m', $review->markdown_content, 2)[0];
        if (! $this->validStructure($body)) {
            throw new RuntimeException('Review headings changed; regenerate the full review.');
        }

        $oldIds = $review->sourceArticles()->orderByPivot('citation_order')->pluck('ai_articles.id')->all();
        $body = preg_replace_callback('/\[(\d+)\](?!\()/u', function (array $match) use ($oldIds): string {
            $id = $oldIds[(int) $match[1] - 1] ?? null;
            if ($id === null) {
                throw new RuntimeException('Review contains an unknown citation.');
            }

            return '[['.$id.']]';
        }, $body);
        $start = strpos($body, $heading) + strlen($heading);
        $end = strlen($body);
        foreach (self::HEADINGS as $otherHeading) {
            $position = strpos($body, $otherHeading, $start);
            if ($position !== false && $position < $end) {
                $end = $position;
            }
        }

        $body = rtrim(substr($body, 0, $start))."\n\n".trim($replacement)."\n\n".ltrim(substr($body, $end));
        if (! $this->validStructure($body)) {
            throw new RuntimeException('Replacement changes the review headings.');
        }
        preg_match_all('/\[\[(\d+)\]\]/', $body, $matches);
        $ids = array_values(array_unique(array_map('intval', $matches[1])));
        if ($ids === []) {
            throw new RuntimeException('Review needs at least one citation.');
        }

        $articles = AiArticle::query()->whereIn('id', $ids)->get()->keyBy('id');
        if ($articles->count() !== count($ids)) {
            throw new RuntimeException('Citation article is missing.');
        }

        $body = preg_replace_callback('/\[\[(\d+)\]\]/', fn (array $match): string => '['.(array_search((int) $match[1], $ids, true) + 1).']', $body);
        $references = collect($ids)->map(fn (int $id, int $index): string => ($index + 1).'. '.$this->reference($articles[$id]->title, $articles[$id]->url))->implode("\n");

        return ['markdown' => rtrim($body)."\n\n## Nguồn tham khảo\n".$references, 'citation_ids' => $ids];
    }

    public function validateReplacement(string $markdown): void
    {
        if (preg_match('/^ {0,3}#{1,2}(?:\s|$)|^\S[^\n]*\n {0,3}(?:=+|-+)[\t ]*$/m', $markdown)) {
            throw new RuntimeException('Section body must not contain top-level headings.');
        }
    }

    public function reference(string $title, string $url): string
    {
        $title = str_replace(
            ['\\', '[', ']', '!', '`', '*', '_', '<', '>'],
            ['\\\\', '\\[', '\\]', '\\!', '\\`', '\\*', '\\_', '&lt;', '&gt;'],
            preg_replace('/\R/u', ' ', $title),
        );
        $url = str_replace(['\\', '(', ')', '<', '>', "\r", "\n", ' '], ['%5C', '%28', '%29', '%3C', '%3E', '%0D', '%0A', '%20'], $url);

        return '['.$title.']('.$url.')';
    }

    /** @return array{markdown: string, citations: array<int, array{citation_order: int, is_representative: bool}>} */
    public function synchronize(WeeklyReview $review, string $markdown): array
    {
        $body = preg_split('/^## Nguồn tham khảo\s*$/m', $markdown, 2)[0];
        $articles = $review->sourceArticles()->orderByPivot('citation_order')->get()->keyBy('pivot.citation_order');
        preg_match_all('/(?<!!)\[(\d+)\](?!\()/u', $body, $matches);
        $numbers = array_values(array_unique(array_map('intval', $matches[1])));
        $citations = [];
        $references = [];
        foreach ($numbers as $index => $number) {
            $article = $articles->get($number);
            if ($article === null) {
                throw new RuntimeException('Review contains an unknown citation.');
            }
            $citations[$article->id] = ['citation_order' => $index + 1, 'is_representative' => true];
            $references[] = ($index + 1).'. '.$this->reference($article->title, $article->url);
        }
        $body = preg_replace_callback('/(?<!!)\[(\d+)\](?!\()/u', fn (array $match): string => '['.(array_search((int) $match[1], $numbers, true) + 1).']', $body);

        return ['markdown' => rtrim($body).($references ? "\n\n## Nguồn tham khảo\n".implode("\n", $references) : ''), 'citations' => $citations];
    }
}
