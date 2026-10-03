<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'slug'];

    protected $casts = ['created_at' => 'datetime'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tags')->withPivot('created_at');
    }
}
