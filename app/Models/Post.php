<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\ModelObserver;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    public const int MAX_THUMBNAIL_KB = 5120;

    public const array ALLOWED_IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    public const int MAX_EXCERPT_LENGTH = 500;

    public const int MAX_CONTENT_LENGTH = 200000;

    protected $fillable = ['author_id', 'category_id', 'weekly_review_id', 'thumbnail_media_id', 'title', 'slug', 'excerpt', 'markdown_content', 'status', 'source_type', 'seo_title', 'seo_description', 'canonical_url', 'published_at', 'scheduled_at', 'view_count', 'like_count'];

    protected static function booted(): void
    {
        ModelObserver::disableSyncingFor(static::class);

        static::saved(function (Post $post): void {
            if ($post->searchIndexShouldBeUpdated()) {
                SearchIndexOutbox::enqueuePost((int) $post->id);
            }
        });

        static::deleted(function (Post $post): void {
            SearchIndexOutbox::enqueuePost((int) $post->id);
        });

        static::restored(function (Post $post): void {
            SearchIndexOutbox::enqueuePost((int) $post->id);
        });
    }

    public function shouldBeSearchable(): bool
    {
        return ! $this->trashed()
            && $this->status === PostStatus::Published->value
            && $this->published_at !== null;
    }

    public static function makeAllSearchableQuery(): Builder
    {
        $post = new static;

        return $post->newQuery()
            ->when(true, function (Builder $query) use ($post): void {
                $post->makeAllSearchableUsing($query);
            })
            ->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at');
    }

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged([
            'title', 'slug', 'excerpt', 'markdown_content', 'category_id', 'status', 'published_at',
        ]);
    }

    /** @return array<string, int|string|bool|list<string>> */
    public function toSearchableArray(): array
    {
        $this->load(['category', 'tags']);
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($this->markdown_content), $words);

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?? '',
            'content' => strip_tags($this->markdown_content),
            'category' => $this->category?->name ?? '',
            'category_slug' => $this->category?->slug ?? '',
            'category_active' => $this->category_id === null || $this->category?->status === 'ACTIVE',
            'tags' => $this->tags->pluck('name')->all(),
            'published_at' => $this->published_at?->timestamp ?? 0,
            'is_published' => $this->shouldBeSearchable(),
            'bookmarks_count' => $this->bookmarks()->count(),
            'reading_minutes' => max(1, (int) ceil(count($words[0]) / 200)),
        ];
    }

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
        return $this->belongsToMany(Tag::class, 'post_tags')->using(PostTag::class)->withPivot('created_at');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')->withPivot('created_at');
    }

    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')->using(Bookmark::class)->withPivot('created_at');
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'scheduled_at' => 'datetime', 'view_count' => 'integer', 'like_count' => 'integer'];
    }
}
