<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function show(Request $request, Post $article): View
    {
        abort_unless($article->isPublished(), 404);

        $article->load(['author:id,name', 'category:id,name', 'tags:id,name', 'thumbnail:id,url'])
            ->loadCount('bookmarks');
        if ($request->isMethod('GET')) {
            RateLimiter::attempt(
                'article-view:'.$article->id.':'.hash('sha256', (string) $request->ip()),
                1,
                fn (): bool => $article->increment('view_count') > 0,
                3600,
            );
        }

        return view('articles.show', [
            'article' => $article,
            'content' => Str::markdown($article->markdown_content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'isBookmarked' => $request->user()?->bookmarkedPosts()
                ->whereKey($article->id)
                ->exists() ?? false,
        ]);
    }
}
