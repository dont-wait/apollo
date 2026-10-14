<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug')) {
            $title = $this->input('title');

            $this->merge([
                'slug' => is_string($title) ? Str::slug($title) : '',
            ]);
        }
    }

    public function rules(): array
    {
        $article = $this->route('article');
        $statuses = PostStatus::selectableValues();

        if ($article instanceof Post && ! in_array($article->status, $statuses, true)) {
            $statuses[] = $article->status;
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')->ignore($article instanceof Post ? $article->id : $article),
            ],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'thumbnail' => ['nullable', 'image', 'mimes:'.implode(',', Post::ALLOWED_IMAGE_MIMES), 'max:'.Post::MAX_THUMBNAIL_KB],
            'excerpt' => ['nullable', 'string', 'max:'.Post::MAX_EXCERPT_LENGTH],
            'markdown_content' => ['required', 'string', 'max:'.Post::MAX_CONTENT_LENGTH],
            'status' => ['required', Rule::in($statuses)],
        ];
    }
}
