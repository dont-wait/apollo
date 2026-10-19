<?php

namespace App\Jobs\AiWeekly;

use App\AiWeekly\ArticleNormalizer;
use App\AiWeekly\PipelineFailure;
use App\AiWeekly\SourceFetcher;
use App\AiWeekly\SourceRequestException;
use App\Models\AiArticle;
use App\Models\AiSource;
use App\Models\AutomationRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CollectSource implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(public int $runId, public int $sourceId, public int $attempt = 1)
    {
        $this->timeout = config('ai_weekly.http_timeout') + 15;
    }

    public function tries(): int
    {
        return 1;
    }

    public function handle(SourceFetcher $fetcher, ArticleNormalizer $normalizer): void
    {
        $run = AutomationRun::findOrFail($this->runId);
        if ($run->attempt !== $this->attempt || ! in_array($run->status, ['PENDING', 'RUNNING'], true)) {
            return;
        }
        $source = AiSource::findOrFail($this->sourceId);
        if ($source->status !== 'ACTIVE') {
            $this->record(['status' => 'SKIPPED', 'new' => 0, 'duplicate' => 0, 'invalid' => 0]);

            return;
        }

        if (! $run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['status' => 'RUNNING', 'started_at' => $locked->started_at ?? now(), 'locked_at' => now()]))) {
            return;
        }
        try {
            $items = $fetcher->fetch($source);
        } catch (SourceRequestException $exception) {
            $this->record([
                'status' => 'FAILED', 'retryable' => $exception->retryable,
                'retry_after_at' => $exception->retryAfterSeconds === null ? null : now()->addSeconds($exception->retryAfterSeconds)->toIso8601String(),
                'error' => 'Source request failed.', 'http_status' => $exception->statusCode,
            ]);

            return;
        } catch (RuntimeException $exception) {
            $this->record(['status' => 'FAILED', 'retryable' => false, 'error' => PipelineFailure::message($exception)]);

            return;
        }
        $new = $duplicate = $invalid = 0;
        $itemErrors = [];
        foreach ($items as $item) {
            try {
                if (! is_array($item) || ! is_string($item['title'] ?? null) || trim($item['title']) === '' || ! is_string($item['url'] ?? null)) {
                    $invalid++;

                    continue;
                }
                $canonical = $normalizer->canonicalUrl($item['url']);
                $fingerprint = $normalizer->titleFingerprint($item['title']);
                $published = ! empty($item['published_at']) ? CarbonImmutable::parse($item['published_at'])->utc() : null;
                $inserted = $run->withinAttempt($this->attempt, function () use ($source, $fingerprint, $published, $item, $canonical, $run): int {
                    AiSource::query()->lockForUpdate()->findOrFail($source->id);
                    $sameStory = AiArticle::query()
                        ->where('source_id', $source->id)
                        ->where('title_fingerprint', $fingerprint)
                        ->when($published, fn ($query) => $query->whereDate('published_at', $published->toDateString()))
                        ->when(! $published, fn ($query) => $query->whereNull('published_at'))
                        ->get()->first(fn (AiArticle $candidate): bool => (! empty($item['content']) && $candidate->raw_content === $item['content'])
                        || (! empty($item['summary']) && $candidate->raw_summary === $item['summary'])
                        );

                    return DB::table('ai_articles')->insertOrIgnore([
                        'source_id' => $source->id,
                        'collection_run_id' => $run->id,
                        'duplicate_of' => $sameStory?->duplicate_of ?? $sameStory?->id,
                        'title' => trim($item['title']),
                        'url' => $item['url'],
                        'canonical_url' => $canonical,
                        'canonical_url_hash' => hash('sha256', $canonical),
                        'title_fingerprint' => $fingerprint,
                        'author' => $item['author'] ?? null,
                        'published_at' => $published,
                        'raw_summary' => $item['summary'] ?? null,
                        'raw_content' => $item['content'] ?? null,
                        'status' => 'COLLECTED',
                        'collected_at' => now(),
                    ]);
                });
                if ($inserted === null) {
                    return;
                }
                if ($inserted) {
                    $new++;
                } else {
                    $duplicate++;
                }
            } catch (Throwable $exception) {
                $invalid++;
                if (count($itemErrors) < 10) {
                    $itemErrors[] = PipelineFailure::message($exception);
                }
            }
        }

        $run->withinAttempt($this->attempt, fn (): bool => $source->update(['last_fetched_at' => now()]));
        $this->record(['status' => 'SUCCESS', 'new' => $new, 'duplicate' => $duplicate, 'invalid' => $invalid, 'item_errors' => $itemErrors]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->record(['status' => 'FAILED', 'retryable' => true, 'error' => $exception ? PipelineFailure::message($exception) : 'Source fetch or parse failed.']);
    }

    /** @param array<string, mixed> $result */
    private function record(array $result): void
    {
        DB::transaction(function () use ($result): void {
            $run = AutomationRun::query()->lockForUpdate()->findOrFail($this->runId);
            if ($run->attempt !== $this->attempt || ! in_array($run->status, ['PENDING', 'RUNNING'], true)) {
                return;
            }
            $payload = $run->payload ?? [];
            $payload['sources'][$this->sourceId] = $result;
            $statuses = array_column($payload['sources'], 'status');
            $complete = ! in_array('PENDING', $statuses, true);
            $successful = count(array_filter($statuses, fn (string $status): bool => $status === 'SUCCESS' || $status === 'SKIPPED'));
            $run->update([
                'payload' => $payload,
                'status' => $complete ? ($successful === count($statuses) ? 'SUCCESS' : ($successful > 0 ? 'PARTIAL' : 'FAILED')) : 'RUNNING',
                'finished_at' => $complete ? now() : null,
            ]);
        });
    }
}
