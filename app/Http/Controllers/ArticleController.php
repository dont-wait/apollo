<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function show(Post $article): View
    {
        abort_unless(
            $article->status === PostStatus::Published->value
                && $article->published_at?->isPast(),
            404,
        );

        $article->load(['author:id,name', 'category:id,name', 'tags:id,name', 'thumbnail:id,url']);
        $article->increment('view_count');

        return view('articles.show', [
            'article' => $article,
            'content' => Str::markdown($article->markdown_content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
