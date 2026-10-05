<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),

                function (string $attribute, mixed $value, \Closure $fail) use ($categoryId): void {
                    if ($value === null) {
                        return;
                    }

                    $parent = Category::find($value);
                    $visited = [];

                    while ($parent !== null) {
                        if ((int) $parent->getKey() === (int) $categoryId) {
                            $fail('Category cha không được là chính nó hoặc category con của nó.');

                            return;
                        }

                        if (isset($visited[$parent->id])) {
                            $fail('Cấu trúc category đang có vòng lặp không hợp lệ.');

                            return;
                        }

                        $visited[$parent->id] = true;

                        $parent = $parent->parent_id
                            ? Category::find($parent->parent_id)
                            : null;
                    }
                },
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
                Rule::unique('categories', 'slug')
                    ->ignore($category->id),
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'status' => [
                'required',
                Rule::in(['ACTIVE', 'INACTIVE']),
            ],
        ];
    }
}
