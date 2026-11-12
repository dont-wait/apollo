<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ArticleSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_article_detail_renders_seo_metadata_and_rss_discovery_link(): void
    {
        $this->freezeTime();
        $article = Post::factory()->published()->create([
            'title' => 'Article title',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description & details',
            'canonical_url' => 'https://example.com/articles/canonical-article',
        ]);

        $this->get(route('articles.show', $article->slug))
            ->assertOk()
            ->assertSee('<title>SEO title · Apollo Blog</title>', false)
            ->assertSee('<meta name="description" content="SEO description &amp; details">', false)
            ->assertSee('<link rel="canonical" href="https://example.com/articles/canonical-article">', false)
            ->assertSee('<link rel="alternate" type="application/rss+xml" title="'.e(config('app.name', 'Apollo Blog')).' RSS Feed" href="'.e(route('rss')).'">', false);
    }

    public function test_article_detail_uses_title_excerpt_and_route_as_seo_fallbacks(): void
    {
        $this->freezeTime();
        $article = Post::factory()->published()->create([
            'title' => 'Fallback article',
            'excerpt' => 'Fallback excerpt',
            'seo_title' => null,
            'seo_description' => null,
            'canonical_url' => null,
        ]);

        $this->get(route('articles.show', $article->slug))
            ->assertOk()
            ->assertSee('<title>Fallback article · Apollo Blog</title>', false)
            ->assertSee('<meta name="description" content="Fallback excerpt">', false)
            ->assertSee('<link rel="canonical" href="'.e(route('articles.show', $article->slug)).'">', false);
    }

    public function test_article_seo_fields_are_escaped_in_the_document_head(): void
    {
        $article = Post::factory()->published()->create([
            'seo_title' => '<script>alert(1)</script>',
            'seo_description' => '" onmouseover="alert(2)',
            'canonical_url' => 'https://example.com/?q=" onload="alert(3)',
        ]);

        $this->get(route('articles.show', $article->slug))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('content="" onmouseover=', false)
            ->assertDontSee('href="https://example.com/?q=" onload=', false);
    }
}
