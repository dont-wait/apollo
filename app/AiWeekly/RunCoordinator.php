<?php

namespace App\AiWeekly;

use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Jobs\AiWeekly\CollectSource;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
use App\Models\AiSource;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RunCoordinator
{
    public function collect(string $trigger, ?int $userId = null): AutomationRun
    {
        $ids = AiSource::query()->where('status', 'ACTIVE')->whereIn('source_type', ['RSS', 'API'])->pluck('id');
        if ($ids->isEmpty()) {
            throw new RuntimeException('Không có nguồn đang hoạt động.');
        }

        $run = $this->create('COLLECT', $trigger, $userId, [
            'sources' => $ids->mapWithKeys(fn (int $id): array => [$id => ['status' => 'PENDING']])->all(),
        ]);
        if ($run->wasRecentlyCreated) {
            foreach ($ids as $id) {
                CollectSource::dispatch($run->id, $id);
            }
        }

        return $run;
    }

    public function analyze(string $trigger, ?int $userId = null, ?int $articleId = null): AutomationRun
    {
        $run = $this->create('ANALYZE', $trigger, $userId, $articleId ? ['article_id' => $articleId] : []);
        if ($run->wasRecentlyCreated) {
            AnalyzeArticles::dispatch($run->id);
        }

        return $run;
    }

    public function generate(string $weekStart, string $trigger, ?int $userId = null): AutomationRun
    {
        $run = DB::transaction(function () use ($weekStart, $trigger, $userId): AutomationRun {
            $existing = AutomationRun::query()->where('run_type', 'GENERATE_REVIEW')
                ->where('payload->week_start', $weekStart)->whereNull('payload->mode')
                ->orderByDesc('id')->lockForUpdate()->first();
            if ($existing && (! in_array($existing->status, ['FAILED', 'PARTIAL'], true) || $existing->attempt < $existing->max_attempts)) {
                return $existing;
            }

            $values = [
                'run_type' => 'GENERATE_REVIEW', 'status' => 'PENDING', 'trigger_type' => $trigger,
                'triggered_by' => $userId,
                'payload' => ['week_start' => $weekStart, 'previous_run_id' => $existing?->id, 'snapshot' => $existing?->payload['snapshot'] ?? null],
                'attempt' => 1, 'max_attempts' => config('ai_weekly.max_attempts'), 'created_at' => now(),
            ];

            return $existing
                ? AutomationRun::create(['idempotency_key' => 'weekly:'.$weekStart.':'.Str::uuid()] + $values)
                : AutomationRun::query()->createOrFirst(['idempotency_key' => 'weekly:'.$weekStart], $values);
        });
        if ($run->wasRecentlyCreated) {
            GenerateWeeklyReview::dispatch($run->id);
        }

        return $run;
    }

    public function retry(AutomationRun $run): bool
    {
        $pending = Cache::lock('ai-weekly:dispatch:'.$run->run_type, 15)->block(5, fn (): ?array => DB::transaction(function () use ($run): ?array {
            if (AutomationRun::query()->where('run_type', $run->run_type)->whereIn('status', ['PENDING', 'RUNNING'])->exists()) {
                return null;
            }
            $locked = AutomationRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! in_array($locked->status, ['FAILED', 'PARTIAL'], true) || $locked->attempt >= $locked->max_attempts) {
                return null;
            }

            $payload = $locked->payload ?? [];
            $sourceIds = [];
            if ($locked->run_type === 'COLLECT') {
                foreach ($payload['sources'] ?? [] as $id => $result) {
                    if (isset($result['retry_after_at']) && now()->lessThan($result['retry_after_at'])) {
                        return null;
                    }
                    if (($result['status'] ?? '') === 'PENDING' || (($result['status'] ?? '') === 'FAILED' && ($result['retryable'] ?? true))) {
                        $sourceIds[] = (int) $id;
                        $payload['sources'][$id] = ['status' => 'PENDING'];
                    }
                }
                if ($sourceIds === []) {
                    return null;
                }
            } elseif ($locked->run_type === 'ANALYZE') {
                $payload['cursor'] = 0;
                $payload['success'] = 0;
                $payload['failed'] = 0;
                unset($payload['total'], $payload['current_item'], $payload['article_results'], $payload['skipped']);
            }

            $locked->update(['status' => 'PENDING', 'attempt' => $locked->attempt + 1,
                'payload' => $payload, 'finished_at' => null, 'locked_at' => now(), 'error_message' => null]);

            return [$locked->run_type, $sourceIds, $locked->attempt];
        }));
        if ($pending === null) {
            return false;
        }

        [$type, $sourceIds, $attempt] = $pending;
        if ($type === 'COLLECT') {
            foreach ($sourceIds as $id) {
                CollectSource::dispatch($run->id, $id, $attempt);
            }
        } elseif ($type === 'ANALYZE') {
            AnalyzeArticles::dispatch($run->id, $attempt);
        } else {
            GenerateWeeklyReview::dispatch($run->id, $attempt);
        }

        return true;
    }

    public function reconcile(): void
    {
        $staleAt = now()->subMinutes(config('ai_weekly.stale_minutes'));
        AutomationRun::query()->where(function ($query) use ($staleAt): void {
            $query->where(function ($running) use ($staleAt): void {
                $running->where('status', 'RUNNING')->where(function ($age) use ($staleAt): void {
                    $age->where('locked_at', '<', $staleAt)->orWhere(fn ($neverLocked) => $neverLocked->whereNull('locked_at')->where('created_at', '<', $staleAt));
                });
            })->orWhere(function ($pending) use ($staleAt): void {
                $pending->where('status', 'PENDING')->where(function ($age) use ($staleAt): void {
                    $age->where('locked_at', '<', $staleAt)->orWhere(fn ($neverLocked) => $neverLocked->whereNull('locked_at')->where('created_at', '<', $staleAt));
                });
            });
        })->chunkById(100, function ($runs) use ($staleAt): void {
            foreach ($runs as $run) {
                DB::transaction(function () use ($run, $staleAt): void {
                    $locked = AutomationRun::query()->lockForUpdate()->findOrFail($run->id);
                    $age = $locked->locked_at ?? $locked->created_at;
                    if (! in_array($locked->status, ['PENDING', 'RUNNING'], true) || $age >= $staleAt) {
                        return;
                    }

                    $payload = $locked->payload ?? [];
                    if ($locked->run_type === 'COLLECT') {
                        foreach ($payload['sources'] ?? [] as $id => $result) {
                            if (in_array($result['status'] ?? '', ['PENDING', 'RUNNING'], true)) {
                                $payload['sources'][$id] = ['status' => 'FAILED', 'retryable' => true, 'error' => 'Worker stopped before completion.'];
                            }
                        }
                    }
                    $locked->update(['status' => 'FAILED', 'payload' => $payload, 'finished_at' => now(), 'error_message' => 'Worker stopped before completion.']);
                });
            }
        });

        AutomationRun::query()->whereIn('status', ['FAILED', 'PARTIAL'])
            ->whereColumn('attempt', '<', 'max_attempts')
            ->where('finished_at', '<', now()->subMinutes(config('ai_weekly.retry_delay_minutes')))
            ->chunkById(100, function ($runs): void {
                foreach ($runs as $run) {
                    $this->retry($run);
                }
            });
    }

    /** @param array<string, mixed> $payload */
    private function create(string $type, string $trigger, ?int $userId, array $payload): AutomationRun
    {
        return Cache::lock('ai-weekly:dispatch:'.$type, 15)->block(5, function () use ($type, $trigger, $userId, $payload): AutomationRun {
            $existing = AutomationRun::query()->where('run_type', $type)->whereIn('status', ['PENDING', 'RUNNING'])->orderBy('id')->first();
            if ($existing) {
                return $existing;
            }

            return AutomationRun::create([
                'run_type' => $type, 'status' => 'PENDING', 'trigger_type' => $trigger,
                'triggered_by' => $userId, 'idempotency_key' => (string) Str::uuid(),
                'payload' => $payload, 'attempt' => 1,
                'max_attempts' => config('ai_weekly.max_attempts'), 'created_at' => now(),
            ]);
        });
    }
}
