<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Bookmark extends Pivot
{
    public $timestamps = false;

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Bookmark $pivot) => SearchIndexOutbox::enqueuePost((int) $pivot->post_id));
        static::deleted(fn (Bookmark $pivot) => SearchIndexOutbox::enqueuePost((int) $pivot->post_id));
    }
}
