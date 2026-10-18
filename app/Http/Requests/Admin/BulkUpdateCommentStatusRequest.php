<?php

namespace App\Http\Requests\Admin;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCommentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'comment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'comment_ids.*' => ['integer', 'distinct', 'exists:comments,id'],
            'status' => [
                'required',
                Rule::in([
                    Comment::STATUS_VISIBLE,
                    Comment::STATUS_HIDDEN,
                    Comment::STATUS_DELETED,
                ]),
            ],
        ];
    }
}
