<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentReport extends Model
{
    use HasFactory;

    public const string STATUS_OPEN = 'OPEN';

    public const string STATUS_RESOLVED = 'RESOLVED';

    public const string STATUS_DISMISSED = 'DISMISSED';

    public const array REASONS = [
        'SPAM' => 'Spam or advertising',
        'OFFENSIVE' => 'Offensive or toxic language',
        'HARASSMENT' => 'Harassment or personal attack',
        'MISLEADING' => 'Misleading information',
        'OTHER' => 'Other',
    ];

    protected $fillable = ['comment_id', 'reporter_id', 'reason', 'details', 'status', 'handled_by', 'handled_at'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
