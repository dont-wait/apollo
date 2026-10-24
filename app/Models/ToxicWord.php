<?php

namespace App\Models;

use Database\Factories\ToxicWordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ToxicWord extends Model
{
    /** @use HasFactory<ToxicWordFactory> */
    use HasFactory;

    public const string MATCH_WORD = 'WORD';

    public const string MATCH_LOOSE = 'LOOSE';

    public const array MATCH_MODES = [self::MATCH_WORD, self::MATCH_LOOSE];

    protected $fillable = [
        'term',
        'normalized_term',
        'scopes',
        'match_mode',
        'fold_diacritics',
        'is_active',
        'note',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(config('toxic_words.cache_key')));
        static::deleted(fn (): bool => Cache::forget(config('toxic_words.cache_key')));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'fold_diacritics' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
