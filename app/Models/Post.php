<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['author_id', 'category_id', 'weekly_review_id', 'thumbnail_media_id', 'title', 'slug', 'excerpt', 'markdown_content', 'status', 'source_type', 'seo_title', 'seo_description', 'canonical_url', 'published_at', 'scheduled_at', 'view_count', 'like_count'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function weeklyReview(): BelongsTo
    {
        return $this->belongsTo(WeeklyReview::class);
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'thumbnail_media_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags')->withPivot('created_at');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')->withPivot('created_at');
    }

    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')->withPivot('created_at');
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'scheduled_at' => 'datetime', 'view_count' => 'integer', 'like_count' => 'integer'];
    }
}
