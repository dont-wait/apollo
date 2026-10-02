<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiArticle extends Model
{
    use HasFactory;

    protected $fillable = ['source_id', 'collection_run_id', 'title', 'url', 'canonical_url', 'canonical_url_hash', 'title_fingerprint', 'author', 'published_at', 'raw_summary', 'raw_content', 'status', 'collected_at'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiSource::class, 'source_id');
    }

    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'collection_run_id');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(AiArticleAnalysis::class, 'article_id');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(AiTopic::class, 'ai_article_topics', 'article_id', 'topic_id')->withPivot('confidence');
    }

    public function weeklyReviews(): BelongsToMany
    {
        return $this->belongsToMany(WeeklyReview::class, 'weekly_review_sources', 'article_id', 'review_id')->withPivot(['topic_id', 'is_representative', 'citation_order']);
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'collected_at' => 'datetime'];
    }
}
