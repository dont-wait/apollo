<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
