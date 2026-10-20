<?php

namespace App;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PostSearch
{
    public function search(string $term, ?string $category, string $sort, int $page, Builder $visiblePosts): LengthAwarePaginator
    {
        if (preg_match('/[\p{L}\p{N}]/u', $term) !== 1) {
            return (new LengthAwarePaginator([], 0, 10, $page, [
                'path' => url()->current(), 'pageName' => 'search_page',
            ]))->withQueryString();
        }

        $search = Post::search($term)
            ->where('is_published', true)
            ->where('published_at', '<=', now()->timestamp)
            ->where('category_active', true)
            ->options(['attributesToRetrieve' => ['id'], 'matchingStrategy' => 'all'])
            ->query(function (Builder $query) use ($visiblePosts): void {
                $query->setQuery(clone $visiblePosts->toBase());
                $query->with($visiblePosts->getEagerLoads());
            });

        if ($category !== null && $category !== '') {
            $search->where('category_slug', $category);
        }

        match ($sort) {
            'latest' => $search->orderBy('published_at', 'desc')->orderBy('id', 'desc'),
            'popular' => $search->orderBy('bookmarks_count', 'desc')->orderBy('id', 'desc'),
            'reading' => $search->orderBy('reading_minutes', 'desc')->orderBy('id', 'desc'),
            default => null,
        };

        return $search->paginate(10, 'search_page', $page)->withQueryString()
            ->appends(['query' => null, 'cursor' => null, 'page' => null]);
    }
}
