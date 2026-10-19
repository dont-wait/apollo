<?php

namespace App\Jobs\AiWeekly;

use App\AiWeekly\DraftPostCreator;
use App\AiWeekly\PipelineFailure;
use App\AiWeekly\WeeklyDraftBuilder;
use App\Models\AutomationRun;
use App\Models\User;
use App\Models\WeeklyReview;
use App\Notifications\WeeklyReviewReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class GenerateWeeklyReview implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(public int $runId, public int $attempt = 1)
    {
        $this->timeout = config('ai_weekly.model_timeout') + 15;
    }

    public function handle(WeeklyDraftBuilder $builder, DraftPostCreator $drafts): void
    {
        $run = AutomationRun::findOrFail($this->runId);
        $run = $run->withinAttempt($this->attempt, function (AutomationRun $locked): AutomationRun {
            $locked->update(['status' => 'RUNNING', 'started_at' => $locked->started_at ?? now(), 'locked_at' => now()]);

            return $locked;
        });
        if ($run === null) {
            return;
        }
        try {
            $builder->build($run);
            $run->withinAttempt($this->attempt, function (AutomationRun $locked) use ($drafts): void {
                $candidate = $locked->payload['candidate'] ?? null;
                $review = ($locked->payload['mode'] ?? null) === 'candidate'
                    ? WeeklyReview::findOrFail($locked->payload['review_id'])
                    : WeeklyReview::query()->where('week_start', $locked->payload['week_start'])->firstOrFail();
                $post = null;
                if (($locked->payload['mode'] ?? null) !== 'candidate') {
                    $author = User::query()->where('role', 'ADMIN')->where('status', 'ACTIVE')
                        ->when($locked->triggered_by !== null, fn ($query) => $query->whereKey($locked->triggered_by))
                        ->when($locked->triggered_by === null && config('ai_weekly.author_id') !== null, fn ($query) => $query->whereKey(config('ai_weekly.author_id')))
                        ->orderBy('id')->first() ?? throw new RuntimeException('Không có admin đang hoạt động để sở hữu bài AI Weekly nháp.');
                    $post = $drafts->create($review, $author, markReviewed: false);
                }
                $locked->update(['status' => 'SUCCESS', 'finished_at' => now(), 'locked_at' => null]);
                User::query()->where('role', 'ADMIN')->where('status', 'ACTIVE')->chunkById(100, function ($admins) use ($locked, $candidate, $review, $post): void {
                    Notification::send($admins, new WeeklyReviewReady($locked, $review->id, $candidate['draft']['title'] ?? $review->title, $post?->id));
                });
            });
        } catch (Throwable $exception) {
            $run->withinAttempt($this->attempt, fn (AutomationRun $locked): bool => $locked->update(['status' => 'FAILED', 'finished_at' => now(), 'locked_at' => null, 'error_message' => PipelineFailure::message($exception)]));
        }
    }
}
