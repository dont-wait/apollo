<?php

namespace App\Models;

use App\Jobs\SyncPostSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SearchIndexOutbox extends Model
{
    protected $table = 'search_index_outbox';

    protected $fillable = [
        'post_id',
        'version',
        'available_at',
        'locked_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'version' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public static function enqueuePost(int $postId): void
    {
        DB::table((new static)->getTable())->insertOrIgnore([
            'post_id' => $postId,
            'version' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outbox = static::query()
            ->where('post_id', $postId)
            ->lockForUpdate()
            ->firstOrFail();

        $outbox->increment('version');
        $outbox->forceFill([
            'available_at' => now(),
            'locked_at' => null,
            'last_error' => null,
        ])->save();

        SyncPostSearch::dispatch($postId)->afterCommit();
    }
}
