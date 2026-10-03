<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WeeklyReview extends Model
{
    use HasFactory;

    protected $fillable = ['week_start', 'week_end', 'title', 'excerpt', 'markdown_content', 'status', 'quality_warning', 'suggested_category_id', 'suggested_tags', 'generation_run_id', 'model_name', 'generated_at', 'reviewed_by', 'reviewed_at'];

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'generation_run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }

    public function sourceArticles(): BelongsToMany
    {
        return $this->belongsToMany(AiArticle::class, 'weekly_review_sources', 'review_id', 'article_id')->withPivot(['topic_id', 'is_representative', 'citation_order']);
    }

    protected function casts(): array
    {
        return ['week_start' => 'date', 'week_end' => 'date', 'suggested_tags' => 'array', 'generated_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
