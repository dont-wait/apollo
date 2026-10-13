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
            'category' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['latest', 'popular', 'reading'])],
        ]);
        $sort = $filters['sort'] ?? 'latest';

        $posts = Post::query()
            ->with(['author:id,name', 'category:id,name', 'tags:id,name', 'thumbnail:id,url'])
            ->withCount('bookmarks')
            ->when($filters['category'] ?? null, function (Builder $query, string $category): void {
                $query->whereHas('category', function (Builder $query) use ($category): void {
                    $query->where('name', $category);
                });
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
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
            'popular' => $posts->orderByDesc('bookmarks_count'),
            'reading' => $posts->orderByRaw('CHAR_LENGTH(markdown_content) DESC'),
            default => $posts->orderByDesc('published_at')->orderByDesc('id'),
        };

        $posts = $posts->paginate(10)->withQueryString();
        $posts->setCollection($posts->getCollection()->map(fn (Post $post): array => $this->formatPost($post)));

        $categoryOptions = Category::query()
            ->whereHas('posts', function (Builder $query): void {
                $this->applyPublishedScope($query);
            })
            ->withCount(['posts as published_posts_count' => function (Builder $query): void {
                $this->applyPublishedScope($query);
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = [
            'All Posts',
            ...$categoryOptions->pluck('name')->all(),
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
            'categoryCounts' => $categoryOptions->mapWithKeys(fn (Category $category): array => [
                $category->name => $category->published_posts_count,
            ])->all(),
            'filters' => $filters,
        ]);
    }

    private function applyPublishedScope(Builder $query): void
    {
        $query
            ->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
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
            'readMinutes' => $this->readingMinutes($post->markdown_content),
            'views' => $this->formatCount((int) $post->view_count),
            'bookmarks' => (int) $post->bookmarks_count,
            'publishedOrder' => $post->published_at?->timestamp ?? 0,
            'tags' => $post->tags->pluck('name')->values()->all(),
            'image' => $post->thumbnail?->url,
            'url' => route('articles.show', ['article' => $post->slug]),
        ];
    }

    private function readingMinutes(string $markdown): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($markdown), $matches);

        return max(1, (int) ceil(count($matches[0]) / 200));
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
