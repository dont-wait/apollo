<?php

namespace App\Notifications;

use App\Models\AutomationRun;
use Illuminate\Notifications\Notification;

class WeeklyReviewReady extends Notification
{
    public function __construct(public AutomationRun $run, public int $reviewId, public string $title, public ?int $postId = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{review_id: int, post_id: int|null, run_id: int, title: string, week_start: string, is_candidate: bool} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'review_id' => $this->reviewId, 'post_id' => $this->postId, 'run_id' => $this->run->id, 'title' => $this->title,
            'week_start' => $this->run->payload['week_start'],
            'is_candidate' => ($this->run->payload['mode'] ?? null) === 'candidate',
        ];
    }
}
