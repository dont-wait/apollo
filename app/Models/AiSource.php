<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSource extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'source_type', 'site_url', 'feed_url', 'trust_score', 'priority', 'status', 'last_fetched_at'];

    public function articles(): HasMany
    {
        return $this->hasMany(AiArticle::class, 'source_id');
    }

    protected function casts(): array
    {
        return ['last_fetched_at' => 'datetime', 'trust_score' => 'integer', 'priority' => 'integer'];
    }
}
