<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    use HasFactory;

    public const int MAX_CONTENT_LENGTH = 2000;

    public const int ROOT_PAGE_SIZE = 10;

    public const int REPLY_PAGE_SIZE = 5;

    public const string STATUS_VISIBLE = 'VISIBLE';

    public const string STATUS_PENDING = 'PENDING';

    public const string STATUS_HIDDEN = 'HIDDEN';

    public const string STATUS_DELETED = 'DELETED';

    protected $fillable = ['post_id', 'user_id', 'parent_id', 'content', 'status', 'moderated_by', 'moderated_at'];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VISIBLE);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }
}
