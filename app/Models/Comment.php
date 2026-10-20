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

    protected $fillable = ['post_id', 'user_id', 'parent_id', 'content', 'status', 'moderated_by', 'moderated_at', 'edited_at', 'deleted_at', 'deleted_by', 'version', 'idempotency_key', 'idempotency_hash'];

    protected $attributes = [
        'version' => 1,
    ];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VISIBLE);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', self::STATUS_VISIBLE)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', self::STATUS_DELETED)
                        ->whereHas('replies', function (Builder $query): void {
                            $query->whereIn('status', [
                                self::STATUS_VISIBLE,
                                self::STATUS_DELETED,
                            ]);
                        });
                });
        });
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === self::STATUS_VISIBLE
            || ($this->status === self::STATUS_DELETED && $this->replies()
                ->whereIn('status', [self::STATUS_VISIBLE, self::STATUS_DELETED])
                ->exists());
    }

    public function hasBlockedPublicAncestor(): bool
    {
        $ancestorId = $this->parent_id;
        $visited = [];

        while ($ancestorId !== null) {
            if (isset($visited[$ancestorId])) {
                return true;
            }

            $visited[$ancestorId] = true;
            $ancestor = static::query()
                ->select(['id', 'parent_id', 'status'])
                ->find($ancestorId);

            if ($ancestor === null) {
                return true;
            }

            if (in_array($ancestor->status, [self::STATUS_PENDING, self::STATUS_HIDDEN], true)) {
                return true;
            }

            $ancestorId = $ancestor->parent_id;
        }

        return false;
    }

    public function isPubliclyAccessible(): bool
    {
        return $this->isPubliclyVisible() && ! $this->hasBlockedPublicAncestor();
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
        return [
            'version' => 'integer',
            'moderated_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
