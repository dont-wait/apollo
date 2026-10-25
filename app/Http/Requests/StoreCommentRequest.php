<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesToxicContent;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    use ValidatesToxicContent;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array{scope: string, label: string}> */
    protected function toxicContentFields(): array
    {
        return [
            'content' => ['scope' => 'comment', 'label' => 'Bình luận'],
        ];
    }

    protected function toxicContentWithModeration(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.Comment::MAX_CONTENT_LENGTH],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where(function (Builder $query): void {
                    /** @var Post $article */
                    $article = $this->route('article');

                    $query->where('post_id', $article->id)
                        ->where('status', Comment::STATUS_VISIBLE);
                }),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');

        if (is_string($content)) {
            $this->merge(['content' => trim($content)]);
        }

        $idempotencyKey = $this->header('Idempotency-Key');

        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->merge(['idempotency_key' => $idempotencyKey]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.required' => 'Write a comment before posting.',
            'content.max' => 'Comments cannot be longer than :max characters.',
            'parent_id.exists' => 'This conversation is no longer available.',
        ];
    }
}
