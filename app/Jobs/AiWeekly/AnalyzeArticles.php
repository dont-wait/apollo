<?php

namespace App\Jobs\AiWeekly;

use App\AiWeekly\ModelClient;
use App\AiWeekly\PipelineFailure;
use App\AiWeekly\Prompts;
use App\AiWeekly\ResponseSchemas;
use App\Models\AiArticle;
use App\Models\AiTopic;
use App\Models\AutomationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class AnalyzeArticles implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(public int $runId, public int $attempt = 1)
    {
        $this->timeout = config('ai_weekly.model_timeout') + 15;
    }

    public function handle(ModelClient $client): void
    {
        $run = AutomationRun::findOrFail($this->runId);
        if ($run->attempt !== $this->attempt || ! in_array($run->status, ['PENDING', 'RUNNING'], true)) {
            return;
        }
        $run = $run->withinAttempt($this->attempt, function (AutomationRun $locked): AutomationRun {
            $locked->update(['status' => 'RUNNING', 'started_at' => $locked->started_at ?? now(), 'locked_at' => now()]);

            return $locked;
        });
        if ($run === null) {
            return;
        }
        $payload = $run->payload ?? [];
        $cursor = $payload['cursor'] ?? 0;
        $articlesQuery = AiArticle::query()->where('id', '>', $cursor)->whereNull('duplicate_of')
            ->when(isset($payload['article_id']), fn ($query) => $query->whereKey($payload['article_id']))
            ->when(! isset($payload['article_id']), function ($query): void {
                $query->where(function ($content): void {
                    $content->where(fn ($summary) => $summary->whereNotNull('raw_summary')->where('raw_summary', '!=', ''))
                        ->orWhere(fn ($body) => $body->whereNotNull('raw_content')->where('raw_content', '!=', ''));
                })->where(function ($pending): void {
                    $pending->whereNotIn('status', ['ANALYZED'])->orWhereDoesntHave('analyses', fn ($analysis) => $analysis->where('is_current', true));
                });
            });
        $payload['total'] ??= (clone $articlesQuery)->count();
        $articles = $articlesQuery->orderBy('id')->limit(1)->get();
        if ($articles->isEmpty()) {
            $payload['total'] = count($payload['article_results'] ?? []);
            $failed = $payload['failed'] ?? 0;
            $run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => array_diff_key($payload, ['current_item' => true]), 'status' => $failed ? (($payload['success'] ?? 0) ? 'PARTIAL' : 'FAILED') : 'SUCCESS', 'finished_at' => now()]));

            return;
        }

        $slugs = AiTopic::query()->where('status', 'ACTIVE')->pluck('slug')->all();
        $article = $articles->sole();
        $payload['current_item'] = $article->title;
        $payload['total'] = max($payload['total'], count($payload['article_results'] ?? []) + 1);
        if (! $run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload, 'locked_at' => now()]))) {
            return;
        }
        $lock = Cache::lock('ai-weekly:analysis:'.$article->id, $this->timeout + 30);
        if (! $lock->get()) {
            self::dispatch($this->runId, $this->attempt)->delay(5);

            return;
        }

        try {
            $article->refresh();
            if (! isset($payload['article_id']) && $article->status === 'ANALYZED' && $article->analyses()->where('is_current', true)->where('status', 'SUCCESS')->exists()) {
                $payload['article_results'][$article->id] = ['status' => 'SKIPPED'];
                $payload['cursor'] = $article->id;
                $run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload, 'locked_at' => now()]));
                self::dispatch($this->runId, $this->attempt);

                return;
            }
            $inputVersion = (int) $article->analyses()->max('version');
            if (! $article->raw_summary && ! $article->raw_content) {
                $payload['skipped'] = ($payload['skipped'] ?? 0) + 1;
                $payload['article_results'][$article->id] = ['status' => 'SKIPPED', 'error' => 'Article has no usable content.'];

                $payload['cursor'] = $article->id;
                $run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload, 'locked_at' => now()]));
                self::dispatch($this->runId, $this->attempt);

                return;
            }

            try {
                $result = $client->complete(Prompts::analysis(), [
                    'article_id' => $article->id, 'title' => $article->title, 'url' => $article->url,
                    'content' => mb_substr($article->raw_content ?: $article->raw_summary, 0, config('ai_weekly.analysis_input_chars')),
                    'input_truncated' => mb_strlen($article->raw_content ?: $article->raw_summary) > config('ai_weekly.analysis_input_chars'), 'allowed_topics' => $slugs,
                ], schema: ResponseSchemas::analysis($slugs), maxTokens: config('ai_weekly.analysis_output_tokens'));
                $validated = Validator::make($result, [
                    'summary' => ['required', 'string', 'max:3000'],
                    'key_points' => ['present', 'array', 'max:10'], 'key_points.*' => ['string', 'max:500'],
                    'technologies' => ['present', 'array', 'max:20'], 'technologies.*' => ['string', 'max:100'],
                    'companies' => ['present', 'array', 'max:20'], 'companies.*' => ['string', 'max:100'],
                    'opportunities' => ['present', 'array', 'max:10'], 'opportunities.*' => ['string', 'max:500'],
                    'risks' => ['present', 'array', 'max:10'], 'risks.*' => ['string', 'max:500'],
                    'topics' => ['present', 'array', 'max:10'], 'topics.*.slug' => ['required', Rule::in($slugs)],
                    'topics.*.confidence' => ['required', 'numeric', 'between:0,1'],
                ])->validate();
                $saved = $run->withinAttempt($this->attempt, function (AutomationRun $active) use ($article, $validated, $result, $inputVersion, &$payload): bool {
                    $locked = AiArticle::query()->lockForUpdate()->findOrFail($article->id);
                    if ((int) $locked->analyses()->max('version') !== $inputVersion) {
                        return false;
                    }
                    $version = $inputVersion + 1;
                    $locked->analyses()->update(['is_current' => false]);
                    $locked->analyses()->create([
                        'version' => $version, 'is_current' => true, 'status' => 'SUCCESS',
                        'model_name' => config('ai_weekly.model'), 'prompt_version' => config('ai_weekly.prompt_version'),
                        'summary' => $validated['summary'], 'key_points' => $validated['key_points'],
                        'technologies' => $validated['technologies'], 'companies' => $validated['companies'],
                        'opportunities' => $validated['opportunities'], 'risks' => $validated['risks'], 'raw_output' => $result,
                        'input_tokens' => $result['_usage']['input_tokens'] ?? null, 'output_tokens' => $result['_usage']['output_tokens'] ?? null,
                    ]);
                    $topicIds = AiTopic::query()->whereIn('slug', array_column($validated['topics'], 'slug'))->pluck('id', 'slug');
                    $locked->topics()->sync(collect($validated['topics'])->mapWithKeys(fn (array $topic): array => [$topicIds[$topic['slug']] => ['confidence' => $topic['confidence']]])->all());
                    $locked->update(['status' => 'ANALYZED']);
                    $payload['success'] = ($payload['success'] ?? 0) + 1;
                    $payload['article_results'][$article->id] = ['status' => 'SUCCESS'];
                    $payload['cursor'] = $article->id;
                    $active->update(['payload' => $payload, 'locked_at' => now()]);

                    return true;
                });
                if ($saved === null) {
                    return;
                }
            } catch (Throwable $exception) {
                $saved = $run->withinAttempt($this->attempt, function (AutomationRun $active) use ($article, $exception, $inputVersion, &$payload): bool {
                    $locked = AiArticle::query()->lockForUpdate()->findOrFail($article->id);
                    if ((int) $locked->analyses()->max('version') !== $inputVersion) {
                        return false;
                    }
                    $locked->analyses()->create(['version' => $locked->analyses()->max('version') + 1, 'is_current' => false, 'status' => 'FAILED', 'error_message' => PipelineFailure::message($exception)]);
                    if (! $locked->analyses()->where('is_current', true)->where('status', 'SUCCESS')->exists()) {
                        $locked->update(['status' => 'FAILED']);
                    }
                    $payload['failed'] = ($payload['failed'] ?? 0) + 1;
                    $payload['article_results'][$article->id] = ['status' => 'FAILED', 'error' => PipelineFailure::message($exception)];
                    $payload['cursor'] = $article->id;
                    $active->update(['payload' => $payload, 'locked_at' => now()]);

                    return true;
                });
                if ($saved === null) {
                    return;
                }
            }
            $payload['cursor'] = $article->id;
            if ($run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['payload' => $payload, 'locked_at' => now()]))) {
                self::dispatch($this->runId, $this->attempt);
            }
        } finally {
            $lock->release();
        }
    }
}
