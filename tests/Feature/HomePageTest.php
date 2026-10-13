<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_page_renders_without_exposing_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('home.index')
            ->assertSeeText('Engineering notes for the systems behind AI.')
            ->assertSeeText('AI Weekly: What changed in models, agents, and inference this week')
            ->assertDontSeeText($user->name)
            ->assertDontSeeText($user->email);
    }

    public function test_home_page_lists_published_articles_only(): void
    {
        $publishedArticle = Post::factory()->published()->create([
            'title' => 'Published article from the database',
        ]);
        Post::factory()->create([
            'title' => 'Draft article hidden from the home page',
        ]);
        Post::factory()->published()->create([
            'title' => 'Future article hidden from the home page',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee($publishedArticle->title, false)
            ->assertDontSee('Draft article hidden from the home page', false)
            ->assertDontSee('Future article hidden from the home page', false);
    }

    public function test_home_page_paginates_published_articles(): void
    {
        Post::factory()->published()->count(11)->create();

        $response = $this->get(route('home'));
        $posts = $response->viewData('posts');

        $response->assertOk();
        $this->assertSame(11, $posts->total());
        $this->assertCount(10, $posts->items());
        $this->assertTrue($posts->hasPages());
    }

    public function test_home_page_applies_search_and_category_filters_before_pagination(): void
    {
        $category = Category::factory()->create(['name' => 'Systems']);
        $matchingArticle = Post::factory()->published()->create([
            'category_id' => $category->id,
            'title' => 'Distributed systems in production',
        ]);
        Post::factory()->published()->create([
            'title' => 'Unrelated article',
        ]);

        $response = $this->get(route('home', [
            'category' => $category->name,
            'q' => 'production',
        ]));
        $posts = $response->viewData('posts');

        $response->assertOk()
            ->assertSee($matchingArticle->title, false)
            ->assertDontSee('Unrelated article', false);
        $this->assertSame(1, $posts->total());
    }

    public function test_home_page_returns_filtered_articles_as_paginated_json(): void
    {
        $matchingArticle = Post::factory()->published()->create([
            'title' => 'JSON article result',
        ]);
        Post::factory()->published()->create([
            'title' => 'Another article result',
        ]);

        $response = $this->getJson(route('home', ['q' => 'JSON article']));

        $response->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('data.0.title', $matchingArticle->title);
    }

    public function test_published_article_page_renders_markdown_and_increments_views(): void
    {
        $article = Post::factory()->published()->create([
            'title' => 'A published article',
            'slug' => 'a-published-article',
            'markdown_content' => '# Article body\n\nPublished content. <script>alert("xss")</script>',
        ]);

        $response = $this->get(route('articles.show', ['article' => $article->slug]));

        $response->assertOk()
            ->assertViewIs('articles.show')
            ->assertSeeText('A published article')
            ->assertSeeText('Article body')
            ->assertSeeText('Published content.')
            ->assertDontSee('<script>', false);
        $this->assertDatabaseHas('posts', [
            'id' => $article->id,
            'view_count' => 1,
        ]);
    }

    public function test_unpublished_article_page_returns_not_found(): void
    {
        $article = Post::factory()->create([
            'slug' => 'draft-article',
        ]);

        $this->get(route('articles.show', ['article' => $article->slug]))
            ->assertNotFound();
    }
}
