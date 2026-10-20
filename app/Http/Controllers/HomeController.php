<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\PostSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;

class HomeController extends Controller
{
    public function index(Request $request, PostSearch $postSearch): View|JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['relevance', 'latest', 'popular', 'reading'])],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'search_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim($filters['q'] ?? '');
        $sort = $filters['sort'] ?? ($search !== '' ? 'relevance' : 'latest');
        $filters['sort'] = $sort;

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
            });

        $this->applyPublishedScope($posts);

        if ($search !== '') {
            try {
                $posts = $postSearch->search($search, $filters['category'] ?? null, $sort, (int) ($filters['search_page'] ?? 1), $posts);
            } catch (ApiException|CommunicationException $exception) {
                report($exception);
                abort(503, 'Article search is temporarily unavailable. Please try again.');
            }
            $total = $posts->total();
        } else {
            match ($sort) {
                'popular' => $posts->orderByDesc('bookmarks_count')->orderByDesc('id'),
                'reading' => $posts->orderByDesc('reading_minutes')->orderByDesc('id'),
                default => $posts->orderByDesc('published_at')->orderByDesc('id'),
            };
            $total = (clone $posts)->reorder()->count();
            $posts = $posts->cursorPaginate(10)->withQueryString();
        }
        $pagination = [
            'previous_url' => $posts->previousPageUrl(),
            'next_url' => $posts->nextPageUrl(),
            'per_page' => $posts->perPage(),
            'total' => $total,
            'count' => $posts->count(),
        ];
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
                'pagination' => $pagination,
            ]);
        }

        return view('home.index', [
            'posts' => $posts,
            'categories' => $categories,
            'filters' => $filters,
            'pagination' => $pagination,
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
