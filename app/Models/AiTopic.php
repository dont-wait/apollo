<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiTopic extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'status'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(AiArticle::class, 'ai_article_topics', 'topic_id', 'article_id')->withPivot('confidence');
    }

    public function weeklyReviews(): BelongsToMany
    {
        return $this->belongsToMany(WeeklyReview::class, 'weekly_review_sources', 'topic_id', 'review_id')->withPivot(['article_id', 'is_representative', 'citation_order']);
    }
}
