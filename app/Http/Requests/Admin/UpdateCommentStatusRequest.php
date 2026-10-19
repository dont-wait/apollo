<?php

namespace App\Http\Requests\Admin;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommentStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in([
                    Comment::STATUS_VISIBLE,
                    Comment::STATUS_HIDDEN,
                    Comment::STATUS_DELETED,
                ]),
            ],
            'version' => ['required', 'integer', 'min:1'],
            'restore' => ['nullable', 'boolean'],
        ];
    }
}
