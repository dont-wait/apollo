<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesToxicContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'name' => ['scope' => 'category', 'label' => 'Tên category'],
            'slug' => ['scope' => 'category', 'label' => 'Slug category'],
            'description' => ['scope' => 'category', 'label' => 'Mô tả category'],
        ];
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
            ],

            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'slug'),
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'status' => [
                'nullable',
                Rule::in(['ACTIVE', 'INACTIVE']),
            ],
            'color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],

            'icon' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }
}
