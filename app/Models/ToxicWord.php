<?php

namespace App\Models;

use Database\Factories\ToxicWordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

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
        static::saved(fn () => self::invalidateCache());
        static::deleted(fn () => self::invalidateCache());
    }

    public static function cacheVersion(): int
    {
        return (int) DB::table('toxic_word_cache_versions')->where('id', 1)->value('version');
    }

    public static function invalidateCache(): void
    {
        try {
            DB::transaction(function (): void {
                DB::table('toxic_word_cache_versions')->insertOrIgnore([
                    'id' => 1,
                    'version' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('toxic_word_cache_versions')
                    ->where('id', 1)
                    ->increment('version', 1, ['updated_at' => now()]);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
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
