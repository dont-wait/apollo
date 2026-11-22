<?php

namespace App\Http\Requests;

use App\Models\CommentReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(array_keys(CommentReport::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
