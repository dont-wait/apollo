<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['run_type', 'status', 'trigger_type', 'triggered_by', 'idempotency_key', 'payload', 'attempt', 'max_attempts', 'locked_at', 'started_at', 'finished_at', 'error_message', 'created_at'];

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
        return ['payload' => 'array', 'locked_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'attempt' => 'integer', 'max_attempts' => 'integer', 'created_at' => 'datetime'];
    }
}
