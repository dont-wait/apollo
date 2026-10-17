<?php

namespace App\Jobs\AiWeekly;

use App\AiWeekly\WeeklyDraftBuilder;
use App\Models\AutomationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateWeeklyReview implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(public int $runId, public int $attempt = 1)
    {
        $this->timeout = config('ai_weekly.model_timeout') + 15;
    }

    public function handle(WeeklyDraftBuilder $builder): void
    {
        $run = AutomationRun::findOrFail($this->runId);
        if ($run->attempt !== $this->attempt || ! in_array($run->status, ['PENDING', 'RUNNING'], true)) {
            return;
        }
        $run->update(['status' => 'RUNNING', 'started_at' => $run->started_at ?? now(), 'locked_at' => now()]);
        try {
            $builder->build($run);
            $run->update(['status' => 'SUCCESS', 'finished_at' => now(), 'locked_at' => null]);
        } catch (Throwable) {
            $run->update(['status' => 'FAILED', 'finished_at' => now(), 'locked_at' => null, 'error_message' => 'Generation or citation validation failed.']);
        }
    }
}
