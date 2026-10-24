<?php

namespace App\AiWeekly;

use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Jobs\AiWeekly\CollectSource;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
use App\Models\AiSource;
use App\Models\AutomationRun;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunCoordinator
{
    public function collect(string $trigger, ?int $userId = null): AutomationRun
    {
        $ids = AiSource::query()->where('status', 'ACTIVE')->whereIn('source_type', ['RSS', 'API'])->pluck('id');
        if ($ids->isEmpty()) {
            $active = AutomationRun::query()->where('run_type', 'COLLECT')
                ->whereIn('status', ['PENDING', 'RUNNING'])->orderBy('id')->first();
            if ($active) {
                $this->dispatch($active);

                return $active;
            }

            throw new RuntimeException('Không có nguồn đang hoạt động.');
        }

        $run = $this->create('COLLECT', $trigger, $userId, [
            'sources' => $ids->mapWithKeys(fn (int $id): array => [$id => ['status' => 'PENDING']])->all(),
        ]);
        $this->dispatch($run);

        return $run;
    }

    public function analyze(string $trigger, ?int $userId = null, ?int $articleId = null): AutomationRun
    {
        $run = $this->create('ANALYZE', $trigger, $userId, $articleId ? ['article_id' => $articleId] : []);
        $this->dispatch($run);

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
        $this->dispatch($run);

        return $run;
    }

    public function dispatch(AutomationRun $run): void
    {
        $this->dispatchIfNeeded($run, function (AutomationRun $fresh): void {
            if ($fresh->run_type === 'COLLECT') {
                foreach (array_keys($fresh->payload['sources'] ?? []) as $id) {
                    CollectSource::dispatch($fresh->id, (int) $id, $fresh->attempt);
                }
            } elseif ($fresh->run_type === 'ANALYZE') {
                AnalyzeArticles::dispatch($fresh->id, $fresh->attempt);
            } else {
                GenerateWeeklyReview::dispatch($fresh->id, $fresh->attempt);
            }
        });
    }

    public function retry(AutomationRun $run): bool
    {
        try {
            $pending = $this->withDispatchLock($run->run_type, fn (): ?array => DB::transaction(function () use ($run): ?array {
                if ($this->activeRunFor($run->run_type, $run->payload ?? [])) {
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
                    'payload' => $payload, 'finished_at' => null, 'locked_at' => now(), 'dispatched_at' => null, 'error_message' => null]);

                return [$locked->run_type, $sourceIds, $locked->attempt];
            }));
        } catch (RunDispatchBusyException $exception) {
            report($exception);

            return false;
        }
        if ($pending === null) {
            return false;
        }

        [$type, $sourceIds, $attempt] = $pending;
        $this->dispatchIfNeeded($run->fresh(), function (AutomationRun $fresh) use ($type, $sourceIds, $attempt): void {
            if ($type === 'COLLECT') {
                foreach ($sourceIds as $id) {
                    CollectSource::dispatch($fresh->id, $id, $attempt);
                }
            } elseif ($type === 'ANALYZE') {
                AnalyzeArticles::dispatch($fresh->id, $attempt);
            } else {
                GenerateWeeklyReview::dispatch($fresh->id, $attempt);
            }
        });

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
        return $this->withDispatchLock($type, function () use ($type, $trigger, $userId, $payload): AutomationRun {
            $existing = $this->activeRunFor($type, $payload);
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

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withDispatchLock(string $type, Closure $callback): mixed
    {
        $name = 'ai-weekly:dispatch:'.$type;

        try {
            $acquired = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$name]);
        } catch (Throwable $exception) {
            throw new RunDispatchBusyException('Hệ thống đang xử lý một job khác, vui lòng thử lại.', previous: $exception);
        }

        if ((int) ($acquired->acquired ?? 0) !== 1) {
            throw new RunDispatchBusyException('Hệ thống đang xử lý một job khác, vui lòng thử lại.');
        }

        try {
            return $callback();
        } finally {
            try {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @param  Closure(AutomationRun): void  $dispatch
     */
    private function dispatchIfNeeded(AutomationRun $run, Closure $dispatch): void
    {
        $this->withDispatchLock($run->run_type, function () use ($run, $dispatch): void {
            $fresh = $run->fresh();
            if ($fresh === null || $fresh->dispatched_at !== null || ! in_array($fresh->status, ['PENDING', 'RUNNING'], true)) {
                return;
            }

            $dispatch($fresh);
            $fresh->update(['dispatched_at' => now()]);
        });
    }

    /** @param array<string, mixed> $payload */
    private function activeRunFor(string $type, array $payload): ?AutomationRun
    {
        return AutomationRun::query()
            ->where('run_type', $type)
            ->whereIn('status', ['PENDING', 'RUNNING'])
            ->orderBy('id')
            ->get()
            ->first(fn (AutomationRun $run): bool => $this->runCovers($type, $run->payload ?? [], $payload));
    }

    /**
     * @param  array<string, mixed>  $activePayload
     * @param  array<string, mixed>  $requestedPayload
     */
    private function runCovers(string $type, array $activePayload, array $requestedPayload): bool
    {
        return match ($type) {
            'COLLECT' => array_diff(
                array_keys($requestedPayload['sources'] ?? []),
                array_keys($activePayload['sources'] ?? []),
            ) === [],
            'ANALYZE' => $this->analysisRunCovers($activePayload, $requestedPayload),
            'GENERATE_REVIEW' => ($activePayload['week_start'] ?? null) === ($requestedPayload['week_start'] ?? null)
                && ($activePayload['mode'] ?? null) === ($requestedPayload['mode'] ?? null)
                && ($activePayload['review_id'] ?? null) === ($requestedPayload['review_id'] ?? null),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $activePayload
     * @param  array<string, mixed>  $requestedPayload
     */
    private function analysisRunCovers(array $activePayload, array $requestedPayload): bool
    {
        $activeArticleId = $activePayload['article_id'] ?? null;
        $requestedArticleId = $requestedPayload['article_id'] ?? null;
        if ($requestedArticleId === null) {
            return $activeArticleId === null;
        }
        if ($activeArticleId !== null) {
            return (int) $activeArticleId === (int) $requestedArticleId;
        }

        return (int) ($activePayload['cursor'] ?? 0) < (int) $requestedArticleId
            && ! array_key_exists((int) $requestedArticleId, $activePayload['article_results'] ?? []);
    }
}
