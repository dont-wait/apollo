<?php

namespace App\AiWeekly;

use App\Models\AiArticle;
use App\Models\AutomationRun;
use App\Models\WeeklyReview;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class WeeklyDraftBuilder
{
    public function __construct(private WeeklyWindow $window, private ModelClient $model, private SectionEditor $sections) {}

    public function build(AutomationRun $run): void
    {
        $attempt = $run->attempt;
        $payload = $run->payload ?? [];
        $weekStart = $this->window->start($payload['week_start']);
        if (! isset($payload['snapshot'])) {
            $current = $this->window->articles($weekStart);
            if ($current->isEmpty()) {
                throw new RuntimeException('No analyzed articles for this week.');
            }

            $previous = $this->window->articles($weekStart->subWeek());
            $previousStart = $weekStart->subWeek()->utc();
            $previousEnd = $weekStart->utc();
            $baselineAvailable = $previous->isNotEmpty()
                && AutomationRun::query()->where('run_type', 'COLLECT')->where('status', 'SUCCESS')->where('created_at', '>=', $previousStart)->where('created_at', '<', $previousEnd)->exists()
                && ! AutomationRun::query()->where('run_type', 'COLLECT')->whereIn('status', ['PENDING', 'RUNNING', 'FAILED', 'PARTIAL'])->where('created_at', '>=', $previousStart)->where('created_at', '<', $previousEnd)->exists();
            $topics = [];
            foreach ($current as $article) {
                foreach ($article->topics as $topic) {
                    $topics[$topic->slug]['name'] = $topic->name;
                    $topics[$topic->slug]['article_ids'][] = $article->id;
                }
            }
            foreach ($topics as $slug => &$topic) {
                $topic['article_ids'] = array_values(array_unique($topic['article_ids']));
                $topic['count'] = count($topic['article_ids']);
                $topic['previous_count'] = $previous->filter(fn (AiArticle $article): bool => $article->topics->contains('slug', $slug))->count();
                $topic['baseline_available'] = $baselineAvailable;
                $topic['delta_count'] = $topic['baseline_available'] ? $topic['count'] - $topic['previous_count'] : null;
                $topic['percent_change'] = $topic['baseline_available'] && $topic['previous_count'] > 0
                    ? round($topic['delta_count'] / $topic['previous_count'] * 100, 1) : null;
            }
            unset($topic);

            $ranked = $current->sort(function (AiArticle $a, AiArticle $b): int {
                return [$b->source->trust_score, $b->source->priority, $b->published_at?->timestamp ?? $b->collected_at->timestamp, $b->id]
                    <=> [$a->source->trust_score, $a->source->priority, $a->published_at?->timestamp ?? $a->collected_at->timestamp, $a->id];
            });
            $stories = $ranked->take(config('ai_weekly.max_stories'));
            $supporting = $stories;
            foreach ($topics as $slug => &$topic) {
                $representatives = $ranked
                    ->filter(fn (AiArticle $article): bool => $article->topics->contains('slug', $slug))
                    ->take(config('ai_weekly.representatives_per_topic'));
                $topic['representative_article_ids'] = $representatives->pluck('id')->all();
                $supporting = $supporting->concat($representatives)->unique('id');
            }
            unset($topic);
            $payload['snapshot'] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->endOfWeek()->toDateString(),
                'model' => config('ai_weekly.model'),
                'prompt_version' => config('ai_weekly.prompt_version'),
                'eligible_article_count' => $current->count(),
                'eligible_source_count' => $current->pluck('source_id')->unique()->count(),
                'top_story_ids' => $stories->pluck('id')->all(),
                'topics' => $topics,
                'articles' => $supporting->map(fn (AiArticle $article): array => [
                    'id' => $article->id, 'analysis_version' => $article->analyses->first()->version,
                    'title' => $article->title, 'url' => $article->url, 'source_name' => $article->source->name,
                    'summary' => $article->analyses->first()->summary,
                    'published_at' => $article->published_at?->toIso8601String(),
                    'used_collected_at' => $article->published_at === null,
                ])->values()->all(),
            ];
            if (! $run->withinAttempt($attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload]))) {
                return;
            }
        }

        $snapshot = $payload['snapshot'];
        $validIds = array_column($snapshot['articles'], 'id');
        if ($validIds === []) {
            throw new RuntimeException('No cited articles for this week.');
        }

        $section = $payload['section'] ?? null;
        $payload['stage'] = 'generating';
        if (! $run->withinAttempt($attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload, 'locked_at' => now()]))) {
            return;
        }
        $output = $this->model->complete(Prompts::review($section), $snapshot, $snapshot['model'], ResponseSchemas::review($validIds), config('ai_weekly.review_output_tokens'));
        $draft = Validator::make($output, [
            'title' => ['required', 'string', 'max:255'], 'excerpt' => ['required', 'string', 'max:500'],
            'markdown_content' => ['required', 'string', 'max:50000'],
            'citation_ids' => ['required', 'array', 'min:1'], 'citation_ids.*' => ['required', 'integer', 'distinct', 'in:'.implode(',', $validIds)],
            'suggested_tags' => ['sometimes', 'array', 'max:10'], 'suggested_tags.*' => ['string', 'max:100'],
        ])->validate();
        $draft['citation_ids'] = array_map('intval', $draft['citation_ids']);
        if (! $section && ! $this->sections->validStructure($draft['markdown_content'])) {
            throw new RuntimeException('Draft is missing a required section.');
        }
        if ($section) {
            $this->sections->validateReplacement($draft['markdown_content']);
        }
        $tokens = [];
        preg_match_all('/\[\[(\d+)\]\]/', $draft['markdown_content'], $tokens);
        $citedInText = array_map('intval', $tokens[1]);
        if ($citedInText === [] || array_diff($citedInText, $draft['citation_ids']) || array_diff($draft['citation_ids'], $citedInText)) {
            throw new RuntimeException('Draft citations do not match the approved snapshot.');
        }
        $draft['markdown_content'] = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', strip_tags($draft['markdown_content']));
        $draft['markdown_content'] = preg_replace('/https?:\/\/\S+/i', '', $draft['markdown_content']);
        if (! $section) {
            $draft['markdown_content'] = preg_replace_callback('/\[\[(\d+)\]\]/', fn (array $match): string => '['.(array_search((int) $match[1], $draft['citation_ids'], true) + 1).']', $draft['markdown_content']);
        }
        $citations = collect($draft['citation_ids'])->map(fn (int $id, int $index): array => ['article_id' => $id, 'citation_order' => $index + 1, 'is_representative' => true])->all();
        $references = collect($snapshot['articles'])->keyBy('id');
        if (! $section) {
            $draft['markdown_content'] .= "\n\n## Nguồn tham khảo\n".collect($draft['citation_ids'])->map(fn (int $id, int $index): string => ($index + 1).'. '.$this->sections->reference($references[$id]['title'], $references[$id]['url']))->implode("\n");
        }
        $draft['quality_warning'] = ($snapshot['eligible_article_count'] ?? count($snapshot['articles'])) < config('ai_weekly.minimum_articles') || ($snapshot['eligible_source_count'] ?? count(array_unique(array_column($snapshot['articles'], 'source_name')))) < config('ai_weekly.minimum_sources') ? 'Nguồn tuần này còn hạn chế.' : null;

        $payload['stage'] = 'saving';
        if (! $run->withinAttempt($attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload, 'locked_at' => now()]))) {
            return;
        }

        if (($payload['mode'] ?? null) === 'candidate') {
            $payload['candidate'] = ['draft' => $draft, 'citations' => $citations, 'revision' => $payload['revision'], 'section' => $section];
            $run->withinAttempt($attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload]));

            return;
        }

        $run->withinAttempt($attempt, function () use ($run, $snapshot, $draft, $citations): void {
            $review = WeeklyReview::query()->createOrFirst(['week_start' => $snapshot['week_start']], [
                'week_end' => $snapshot['week_end'], 'title' => $draft['title'], 'excerpt' => $draft['excerpt'],
                'markdown_content' => $draft['markdown_content'], 'status' => 'REVIEW',
                'quality_warning' => $draft['quality_warning'], 'suggested_tags' => $draft['suggested_tags'] ?? [],
                'generation_run_id' => $run->id, 'model_name' => $snapshot['model'], 'generated_at' => now(),
            ]);
            if ($review->wasRecentlyCreated) {
                $review->sourceArticles()->sync(collect($citations)->mapWithKeys(fn (array $citation): array => [$citation['article_id'] => ['citation_order' => $citation['citation_order'], 'is_representative' => true]])->all());
            }
        });
    }
}
