<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{action: array<int, mixed>}
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['hide', 'delete', 'dismiss'])],
        ];
    }
}
