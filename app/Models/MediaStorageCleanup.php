<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaStorageCleanup extends Model
{
    protected $fillable = [
        'disk',
        'storage_key',
        'attempts',
        'last_error',
        'available_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'datetime',
        ];
    }
}