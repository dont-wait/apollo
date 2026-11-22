<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportToxicWordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx,csv', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Chọn file .xlsx hoặc .csv để import.',
            'file.extensions' => 'File phải có định dạng .xlsx hoặc .csv.',
            'file.max' => 'File không được lớn hơn 2MB.',
        ];
    }
}
