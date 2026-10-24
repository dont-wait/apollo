<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesToxicContent;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    use ValidatesToxicContent;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array{scope: string, label: string}> */
    protected function toxicContentFields(): array
    {
        return [
            'name' => ['scope' => 'tag', 'label' => 'Tên tag'],
            'slug' => ['scope' => 'tag', 'label' => 'Slug tag'],
        ];
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
            ],

            'slug' => [
                'required',
                'string',
                'max:80',
            ],
            'color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ];
    }
}
