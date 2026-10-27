<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesToxicContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
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
        $tag = $this->route('tag');

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('tags', 'name')->ignore($tag->id),
            ],

            'slug' => [
                'required',
                'string',
                'max:80',
                Rule::unique('tags', 'slug')->ignore($tag->id),
            ],
            'color' => [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ];
    }
}
