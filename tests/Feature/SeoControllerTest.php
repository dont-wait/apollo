<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SeoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sitemap_contains_only_currently_public_article_urls(): void
    {
        $this->freezeTime();
        $published = Post::factory()->published()->create([
            'canonical_url' => 'https://example.com/articles/public-article',
        ]);
        $draft = Post::factory()->create();
        $review = Post::factory()->create(['status' => 'REVIEW']);
        $scheduled = Post::factory()->create([
            'status' => 'SCHEDULED',
            'scheduled_at' => now()->addDay(),
        ]);
        $archived = Post::factory()->create(['status' => 'ARCHIVED']);
        $future = Post::factory()->published()->create([
            'published_at' => now()->addDay(),
        ]);
        $deleted = Post::factory()->published()->create();
        $deleted->delete();
        $inactiveCategory = Category::factory()->create(['status' => 'INACTIVE']);
        $inactiveArticle = Post::factory()->published()->create([
            'category_id' => $inactiveCategory->id,
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
            ->assertSee(route('home'), false)
            ->assertSee(\App\Support\SiteUrl::article($published->slug), false)
            ->assertDontSee($published->canonical_url, false)
            ->assertDontSee(route('articles.show', $draft->slug), false)
            ->assertDontSee(route('articles.show', $review->slug), false)
            ->assertDontSee(route('articles.show', $scheduled->slug), false)
            ->assertDontSee(route('articles.show', $archived->slug), false)
            ->assertDontSee(route('articles.show', $future->slug), false)
            ->assertDontSee(route('articles.show', $deleted->slug), false)
            ->assertDontSee(route('articles.show', $inactiveArticle->slug), false);
    }

    public function test_rss_contains_latest_published_articles_and_excludes_unpublished_articles(): void
    {
        $this->freezeTime();
        $older = Post::factory()->published()->create([
            'title' => 'Older article',
            'published_at' => now()->subDay(),
        ]);
        $newer = Post::factory()->published()->create([
            'title' => 'Newer article',
            'seo_title' => 'Newer SEO title',
            'seo_description' => 'Newer RSS description & details',
            'canonical_url' => 'https://example.com/articles/newer-article',
            'published_at' => now(),
        ]);
        Post::factory()->create(['title' => 'Draft article']);
        Post::factory()->create(['title' => 'Review article', 'status' => 'REVIEW']);
        Post::factory()->create([
            'title' => 'Scheduled article',
            'status' => 'SCHEDULED',
            'scheduled_at' => now()->addDay(),
        ]);
        Post::factory()->create(['title' => 'Archived article', 'status' => 'ARCHIVED']);
        Post::factory()->published()->create([
            'title' => 'Future article',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('rss'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<rss version="2.0"', false)
            ->assertSee('<title>Newer SEO title</title>', false)
            ->assertSee('<description>Newer RSS description &amp; details</description>', false)
            ->assertSee($newer->canonical_url, false)
            ->assertSee(route('articles.show', $older->slug), false)
            ->assertDontSee('Draft article', false)
            ->assertDontSee('Review article', false)
            ->assertDontSee('Scheduled article', false)
            ->assertDontSee('Archived article', false)
            ->assertDontSee('Future article', false);

        $content = $response->getContent();
        $newerPosition = strpos($content, 'Newer SEO title');
        $olderPosition = strpos($content, 'Older article');

        $this->assertIsInt($newerPosition);
        $this->assertIsInt($olderPosition);
        $this->assertLessThan($olderPosition, $newerPosition);
    }
    public function test_sitemap_index_and_pages_split_large_catalog_without_external_urls(): void
    {
        config(['seo.sitemap_chunk_size' => 2]);
        $articles = Post::factory()->published()->count(5)->create();
        $articles[0]->update(['canonical_url' => 'https://external.example/somewhere']);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee('<sitemapindex', false)
            ->assertSee(\App\Support\SiteUrl::route('sitemap.page', ['page' => 1]), false)
            ->assertSee(\App\Support\SiteUrl::route('sitemap.page', ['page' => 3]), false)
            ->assertDontSee('external.example', false);

        $pageOne = $this->get(route('sitemap.page', ['page' => 1]))
            ->assertOk()
            ->assertSee(\App\Support\SiteUrl::route('home'), false)
            ->assertSee(\App\Support\SiteUrl::article($articles[0]->slug), false)
            ->assertDontSee('external.example', false);

        $this->assertSame(3, substr_count($pageOne->getContent(), '<url>'));

        $pageTwo = $this->get(route('sitemap.page', ['page' => 2]))->assertOk();
        $this->assertSame(2, substr_count($pageTwo->getContent(), '<url>'));
        $this->get(route('sitemap.page', ['page' => 3]))->assertOk();
        $this->get(route('sitemap.page', ['page' => 4]))->assertNotFound();
    }
}
