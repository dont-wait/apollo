<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ReplaceMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,gif,pdf',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $fileName = basename(
                        $value->getClientOriginalName()
                    );

                    if (mb_strlen($fileName) > 255) {
                        $fail(
                            'Tên file không được vượt quá 255 ký tự.'
                        );
                    }
                },
            ],
        ];
    }
}
