<?php

namespace App\Models;

use App\Jobs\SyncPostSearch;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Bookmark extends Pivot
{
    public $timestamps = false;

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }

    protected static function booted(): void
    {
        static::saved(fn (Bookmark $pivot) => SyncPostSearch::dispatch((int) $pivot->post_id)->afterCommit());
        static::deleted(fn (Bookmark $pivot) => SyncPostSearch::dispatch((int) $pivot->post_id)->afterCommit());
    }
}
