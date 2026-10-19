<?php

namespace App\Http\Requests;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('comment')) ?? false;
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
            'version' => ['required', 'integer', 'min:1'],
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
            'content.required' => 'Write a comment before saving.',
            'content.max' => 'Comments cannot be longer than :max characters.',
        ];
    }
}
