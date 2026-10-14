<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['latest', 'popular', 'reading'])],
        ]);
        $sort = $filters['sort'] ?? 'latest';

        $posts = Post::query()
            ->select(['id', 'author_id', 'category_id', 'thumbnail_media_id', 'title', 'slug', 'excerpt', 'published_at', 'view_count'])
            ->selectRaw("GREATEST(1, CEIL(CHAR_LENGTH(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(markdown_content, '<[^>]*>', ''), '[[:alnum:]]+', '1'), '[^1]', '')) / 200)) AS reading_minutes")
            ->with(['author:id,name', 'category:id,name', 'tags:id,name', 'thumbnail:id,url'])
            ->withCount('bookmarks')
            ->when(isset($filters['category']) && $filters['category'] !== '', function (Builder $query) use ($filters): void {
                $category = $filters['category'];
                $query->whereHas('category', function (Builder $query) use ($category): void {
                    $query->where('slug', $category);
                });
            })
            ->when(isset($filters['q']) && $filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['q'];
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhereHas('category', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('tags', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            });

        $this->applyPublishedScope($posts);

        match ($sort) {
            'popular' => $posts->orderByDesc('bookmarks_count')->orderByDesc('id'),
            'reading' => $posts->orderByDesc('reading_minutes')->orderByDesc('id'),
            default => $posts->orderByDesc('published_at')->orderByDesc('id'),
        };

        $posts = $posts->paginate(10)->withQueryString();
        $posts->setCollection($posts->getCollection()->map(fn (Post $post): array => $this->formatPost($post)));

        $categoryOptions = Category::query()
            ->where('status', 'ACTIVE')
            ->whereHas('posts', function (Builder $query): void {
                $this->applyPublishedScope($query);
            })
            ->withCount(['posts as published_posts_count' => function (Builder $query): void {
                $this->applyPublishedScope($query);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $categories = [
            ['slug' => '', 'name' => 'All Posts'],
            ...$categoryOptions->map(fn (Category $category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
                'count' => $category->published_posts_count,
            ])->all(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $posts->getCollection()->values()->all(),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'from' => $posts->firstItem(),
                    'to' => $posts->lastItem(),
                ],
            ]);
        }

        return view('home.index', [
            'posts' => $posts,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    private function applyPublishedScope(Builder $query): void
    {
        $query
            ->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('category_id')
                    ->orWhereHas('category', fn (Builder $query): Builder => $query->where('status', 'ACTIVE'));
            });
    }

    /**
     * @return array<string, int|string|list<string>|null>
     */
    private function formatPost(Post $post): array
    {
        return [
            'id' => $post->id,
            'category' => $post->category?->name ?? 'Uncategorized',
            'label' => $post->tags->first()?->name ?? 'Article',
            'title' => $post->title,
            'excerpt' => $post->excerpt ?? '',
            'author' => $post->author?->name ?? 'Apollo Editorial',
            'date' => $post->published_at?->diffForHumans() ?? '',
            'readMinutes' => (int) $post->reading_minutes,
            'views' => $this->formatCount((int) $post->view_count),
            'bookmarks' => (int) $post->bookmarks_count,
            'publishedOrder' => $post->published_at?->timestamp ?? 0,
            'tags' => $post->tags->pluck('name')->values()->all(),
            'image' => $post->thumbnail?->url,
            'url' => route('articles.show', ['article' => $post->slug]),
        ];
    }

    private function formatCount(int $count): string
    {
        if ($count < 1000) {
            return (string) $count;
        }

        if ($count < 1000000) {
            return number_format($count / 1000, 1).'k';
        }

        return number_format($count / 1000000, 1).'m';
    }
}
