<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiArticleAnalysis extends Model
{
    use HasFactory;

    protected $table = 'ai_article_analysis';

    public $timestamps = false;

    protected $fillable = ['article_id', 'version', 'is_current', 'status', 'model_name', 'prompt_version', 'summary', 'key_points', 'technologies', 'companies', 'opportunities', 'risks', 'raw_output', 'error_message', 'input_tokens', 'output_tokens'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(AiArticle::class, 'article_id');
    }

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'key_points' => 'array', 'technologies' => 'array', 'companies' => 'array', 'opportunities' => 'array', 'risks' => 'array', 'raw_output' => 'array'];
    }
}
