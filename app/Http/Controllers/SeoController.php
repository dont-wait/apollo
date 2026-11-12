<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $articles = Post::query()
            ->published()
            ->select(['id', 'slug', 'canonical_url'])
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        return response()
            ->view('seo.sitemap', ['articles' => $articles])
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
