<?php

namespace App\Jobs\AiWeekly;

use App\AiWeekly\ModelClient;
use App\Models\AiArticle;
use App\Models\AiTopic;
use App\Models\AutomationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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
        $payload = $run->payload ?? [];
        $cursor = $payload['cursor'] ?? 0;
        $articles = AiArticle::query()->where('id', '>', $cursor)->whereNull('duplicate_of')
            ->when(isset($payload['article_id']), fn ($query) => $query->whereKey($payload['article_id']))
            ->when(! isset($payload['article_id']), function ($query): void {
                $query->where(function ($pending): void {
                    $pending->whereNotIn('status', ['ANALYZED'])->orWhereDoesntHave('analyses', fn ($analysis) => $analysis->where('is_current', true));
                });
            })->orderBy('id')->limit(1)->get();
        if ($articles->isEmpty()) {
            $failed = $payload['failed'] ?? 0;
            $run->update(['status' => $failed ? (($payload['success'] ?? 0) ? 'PARTIAL' : 'FAILED') : 'SUCCESS', 'finished_at' => now()]);

            return;
        }

        $run->update(['status' => 'RUNNING', 'started_at' => $run->started_at ?? now(), 'locked_at' => now()]);
        $slugs = AiTopic::query()->where('status', 'ACTIVE')->pluck('slug')->all();
        foreach ($articles as $article) {
            if (! $article->raw_summary && ! $article->raw_content) {
                $payload['skipped'] = ($payload['skipped'] ?? 0) + 1;
                $payload['article_results'][$article->id] = ['status' => 'SKIPPED', 'error' => 'Article has no usable content.'];

                continue;
            }

            try {
                $result = $client->complete('Summarize this article in Vietnamese. Return summary, key_points, technologies, companies, opportunities, risks and topics with slug and confidence. Use only the supplied article and allowed topics.', [
                    'article_id' => $article->id, 'title' => $article->title, 'url' => $article->url,
                    'content' => mb_substr($article->raw_content ?: $article->raw_summary, 0, 20000), 'input_truncated' => mb_strlen($article->raw_content ?: $article->raw_summary) > 20000, 'allowed_topics' => $slugs,
                ]);
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
                DB::transaction(function () use ($article, $validated, $result): void {
                    $locked = AiArticle::query()->lockForUpdate()->findOrFail($article->id);
                    $version = $locked->analyses()->max('version') + 1;
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
                });
                $payload['success'] = ($payload['success'] ?? 0) + 1;
                $payload['article_results'][$article->id] = ['status' => 'SUCCESS'];
            } catch (Throwable) {
                DB::transaction(function () use ($article): void {
                    $locked = AiArticle::query()->lockForUpdate()->findOrFail($article->id);
                    $locked->analyses()->create(['version' => $locked->analyses()->max('version') + 1, 'is_current' => false, 'status' => 'FAILED', 'error_message' => 'Analysis or validation failed.']);
                    if (! $locked->analyses()->where('is_current', true)->where('status', 'SUCCESS')->exists()) {
                        $locked->update(['status' => 'FAILED']);
                    }
                });
                $payload['failed'] = ($payload['failed'] ?? 0) + 1;
                $payload['article_results'][$article->id] = ['status' => 'FAILED', 'error' => 'Analysis or validation failed.'];
            }
            $payload['cursor'] = $article->id;
        }

        $payload['cursor'] = $articles->last()->id;
        $run->update(['payload' => $payload, 'locked_at' => now()]);
        self::dispatch($this->runId, $this->attempt);
    }
}
