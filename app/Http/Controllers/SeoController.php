<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $pageSize = $this->sitemapPageSize();
        $count = Post::query()->published()->count();

        if ($count > $pageSize) {
            return $this->xml('seo.sitemap-index', [
                'pageCount' => (int) ceil($count / $pageSize),
            ]);
        }

        return $this->xml('seo.sitemap', [
            'articles' => $this->sitemapArticles(1, $pageSize),
            'includeHome' => true,
        ]);
    }

    public function sitemapPage(int $page): Response
    {
        $pageSize = $this->sitemapPageSize();
        $count = Post::query()->published()->count();
        abort_if($page < 1 || $page > (int) ceil($count / $pageSize), 404);

        return $this->xml('seo.sitemap', [
            'articles' => $this->sitemapArticles($page, $pageSize),
            'includeHome' => $page === 1,
        ]);
    }

    private function sitemapPageSize(): int
    {
        return max(1, min(49999, (int) config('seo.sitemap_chunk_size', 5000)));
    }

    private function sitemapArticles(int $page, int $pageSize): Collection
    {
        return Post::query()
            ->published()
            ->select(['id', 'slug'])
            ->orderBy('published_at')
            ->orderBy('id')
            ->forPage($page, $pageSize)
            ->get();
    }

    /** @param array<string, mixed> $data */
    private function xml(string $view, array $data): Response
    {
        return response()->view($view, $data)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function rss(): Response
    {
        $articles = Post::query()
            ->published()
            ->select([
                'id',
                'slug',
                'title',
                'seo_title',
                'excerpt',
                'seo_description',
                'canonical_url',
                'published_at',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()
            ->view('seo.rss', ['articles' => $articles])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
