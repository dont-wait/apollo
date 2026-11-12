<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AutomationRun extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['run_type', 'status', 'trigger_type', 'triggered_by', 'idempotency_key', 'payload', 'attempt', 'max_attempts', 'locked_at', 'dispatched_at', 'started_at', 'finished_at', 'error_message', 'created_at'];

    /**
     * @template TResult
     *
     * @param  Closure(self): TResult  $callback
     * @return TResult|null
     */
    public function withinAttempt(int $attempt, Closure $callback): mixed
    {
        return DB::transaction(function () use ($attempt, $callback): mixed {
            $run = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($run->attempt !== $attempt || ! in_array($run->status, ['PENDING', 'RUNNING'], true)) {
                return null;
            }

            return $callback($run);
        });
    }

    /** @return array{completed: int, total: ?int, failed: int, percent: ?int, message: string, current_item: ?string} */
    public function progress(): array
    {
        $payload = $this->payload ?? [];
        $active = in_array($this->status, ['PENDING', 'RUNNING'], true);
        $results = $this->run_type === 'COLLECT' ? ($payload['sources'] ?? []) : ($payload['article_results'] ?? []);
        if ($this->run_type === 'ANALYZE' && $this->getAttribute('progress_completed') !== null) {
            $completed = (int) $this->getAttribute('progress_completed');
            $failed = (int) $this->getAttribute('progress_failed');
        } else {
            $completed = count(array_filter($results, fn (array $item): bool => in_array($item['status'] ?? '', ['SUCCESS', 'FAILED', 'SKIPPED'], true)));
            $failed = count(array_filter($results, fn (array $item): bool => ($item['status'] ?? '') === 'FAILED'));
        }
        $total = $this->run_type === 'COLLECT' ? count($results) : ($payload['total'] ?? null);
        $currentItem = $payload['current_item'] ?? null;
        if ($this->run_type === 'COLLECT') {
            $names = array_column(array_filter($results, fn (array $item): bool => ($item['status'] ?? '') === 'RUNNING'), 'name');
            $currentItem = implode(', ', array_filter($names)) ?: null;
        }
        $message = match ($this->status) {
            'PENDING' => 'Đang chờ worker nhận job',
            'SUCCESS' => 'Đã hoàn tất',
            'PARTIAL' => 'Hoàn tất một phần, có mục bị lỗi',
            'FAILED' => 'Job thất bại',
            default => match ($this->run_type) {
                'COLLECT' => 'Đang thu thập nguồn',
                'ANALYZE' => 'Đang phân tích bài viết',
                default => match ($payload['stage'] ?? '') {
                    'generating' => 'AI đang tạo nội dung từ các bài đã chọn',
                    'saving' => 'Đang lưu bản tổng hợp và bài nháp',
                    default => 'Đang chuẩn bị dữ liệu tổng hợp',
                },
            },
        };
        $percent = $total > 0 ? (int) floor($completed / $total * 100) : null;
        if ($percent !== null && $active) {
            $percent = min(99, $percent);
        }
        if ($this->status === 'SUCCESS') {
            $percent = 100;
        }

        return ['completed' => $completed, 'total' => $total, 'failed' => $failed,
            'percent' => $percent, 'message' => $message, 'current_item' => $active ? $currentItem : null];
    }

    public function triggerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function collectedArticles(): HasMany
    {
        return $this->hasMany(AiArticle::class, 'collection_run_id');
    }

    public function weeklyReviews(): HasMany
    {
        return $this->hasMany(WeeklyReview::class, 'generation_run_id');
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'locked_at' => 'datetime', 'dispatched_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'attempt' => 'integer', 'max_attempts' => 'integer', 'created_at' => 'datetime'];
    }
}
