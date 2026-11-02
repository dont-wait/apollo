<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostStatus;
use App\Http\Requests\Concerns\ValidatesToxicContent;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    use ValidatesToxicContent;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array{scope: string, label: string, markdown?: bool}> */
    protected function toxicContentFields(): array
    {
        return [
            'title' => ['scope' => 'post', 'label' => 'Tiêu đề'],
            'slug' => ['scope' => 'post', 'label' => 'Slug'],
            'excerpt' => ['scope' => 'post', 'label' => 'Tóm tắt'],
            'markdown_content' => ['scope' => 'post', 'label' => 'Nội dung bài viết', 'markdown' => true],
            'new_tags.*' => ['scope' => 'tag', 'label' => 'Tên tag'],
            'new_tag_slugs.*' => ['scope' => 'tag', 'label' => 'Slug tag'],
        ];
    }

    protected function toxicContentWithModeration(): bool
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

        if ($this->input('status') !== PostStatus::Scheduled->value) {
            $this->merge(['scheduled_at' => null]);
        }

        $tags = $this->input('new_tags');

        if (is_array($tags)) {
            $this->merge([
                'new_tag_slugs' => array_map(
                    fn (mixed $tag): string => is_string($tag) ? Str::slug($tag) : '',
                    $tags,
                ),
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
            'original_status' => ['nullable', Rule::in(array_keys(PostStatus::filterOptions()))],
            'scheduled_at' => ['nullable', 'date', 'required_if:status,'.PostStatus::Scheduled->value, 'after:now'],
        ];
    }
}
