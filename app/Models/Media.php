<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    use HasFactory;

    protected $fillable = ['uploaded_by', 'file_name', 'mime_type', 'size_bytes', 'storage_key', 'url', 'width', 'height'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'thumbnail_media_id');
    }
}
