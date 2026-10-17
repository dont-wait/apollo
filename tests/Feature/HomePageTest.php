<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DemoArticleSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use DatabaseTruncation;

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
        $this->assertSame(11, $response->viewData('pagination')['total']);
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
            'category_id' => Category::factory()->create(['name' => 'Systems'])->id,
            'title' => 'Unrelated production article',
        ]);

        $response = $this->get(route('home', [
            'category' => $category->slug,
            'q' => 'production',
        ]));
        $response->assertOk()
            ->assertSee($matchingArticle->title, false)
            ->assertDontSee('Unrelated production article', false);
        $this->assertSame(1, $response->viewData('pagination')['total']);
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
            ->assertJsonPath('pagination.previous_url', null)
            ->assertJsonPath('data.0.title', $matchingArticle->title);
    }

    public function test_published_article_page_renders_markdown_and_increments_views(): void
    {
        $this->freezeTime();
        $article = Post::factory()->published()->create([
            'title' => 'A published article',
            'slug' => 'a-published-article',
            'markdown_content' => "# Article body\n\nPublished content. <script>alert(\"xss\")</script>\n\n[Unsafe](javascript:alert(1))",
        ]);

        $response = $this->get(route('articles.show', ['article' => $article->slug]));

        $response->assertOk()
            ->assertViewIs('articles.show')
            ->assertSeeText('A published article')
            ->assertSee('<h1>Article body</h1>', false)
            ->assertSeeText('Published content.')
            ->assertDontSee('<script>', false)
            ->assertDontSee('href="javascript:', false);
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

    public function test_zero_search_is_not_ignored(): void
    {
        Post::factory()->published()->create(['title' => 'Version 0', 'slug' => '0-version', 'excerpt' => 'Initial version']);
        Post::factory()->published()->create(['title' => 'Another version', 'slug' => 'another-version', 'excerpt' => 'Later version']);

        $this->getJson(route('home', ['q' => '0']))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.title', 'Version 0');
    }

    public function test_inactive_category_is_hidden_from_feed_and_article_detail(): void
    {
        $category = Category::factory()->create(['status' => 'INACTIVE']);
        $article = Post::factory()->published()->create(['category_id' => $category->id]);

        $this->getJson(route('home'))->assertJsonPath('pagination.total', 0);
        $this->get(route('articles.show', $article->slug))->assertNotFound();
    }

    public function test_reading_sort_matches_displayed_minutes(): void
    {
        $longRead = Post::factory()->published()->create(['markdown_content' => str_repeat('a ', 401)]);
        Post::factory()->published()->create(['markdown_content' => str_repeat('extraordinarilylongword ', 10)]);

        $this->getJson(route('home', ['sort' => 'reading']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $longRead->id)
            ->assertJsonPath('data.0.readMinutes', 3);
    }

    public function test_popular_sort_has_stable_tie_breaker_across_pages(): void
    {
        $articles = Post::factory()->published()->count(11)->create();

        $response = $this->getJson(route('home', ['sort' => 'popular']))
            ->assertOk()->assertJsonPath('data.0.id', $articles->last()->id);
        $this->getJson($response->json('pagination.next_url'))
            ->assertOk()->assertJsonPath('data.0.id', $articles->first()->id);
    }

    public function test_cursor_does_not_repeat_articles_when_a_new_post_is_published(): void
    {
        $this->freezeTime();
        $articles = Post::factory()->published()->count(11)->create();
        $first = $this->getJson(route('home'))->assertOk();
        Post::factory()->published()->create();

        $second = $this->getJson($first->json('pagination.next_url'))->assertOk();

        $this->assertSame([$articles->first()->id], array_column($second->json('data'), 'id'));
        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson($second->json('pagination.previous_url'))
            ->assertOk()->assertJsonPath('data.0.id', $articles->last()->id);
    }

    public function test_reading_cursor_traverses_computed_sort_values(): void
    {
        $articles = Post::factory()->published()->count(11)->create(['markdown_content' => 'Short read']);
        $first = $this->getJson(route('home', ['sort' => 'reading']))->assertOk();

        $this->getJson($first->json('pagination.next_url'))
            ->assertOk()->assertJsonPath('data.0.id', $articles->first()->id);
    }

    public function test_search_matches_indexed_body_category_and_tag_fields(): void
    {
        $body = Post::factory()->published()->create(['markdown_content' => 'Uniquequasar appears in body']);
        $category = Category::factory()->create(['name' => 'Uniquequasar systems']);
        $categorized = Post::factory()->published()->create(['category_id' => $category->id]);
        $tagged = Post::factory()->published()->create();
        $tagged->tags()->attach(Tag::factory()->create(['name' => 'Uniquequasar'])->id);
        Post::factory()->published()->create();

        $response = $this->getJson(route('home', ['q' => 'Uniquequasar']))->assertOk();

        $ids = array_column($response->json('data'), 'id');
        sort($ids);
        $this->assertSame([$body->id, $categorized->id, $tagged->id], $ids);
    }

    public function test_search_wildcards_do_not_match_every_article(): void
    {
        Post::factory()->published()->create(['title' => 'Ordinary article']);

        $this->getJson(route('home', ['q' => '%']))
            ->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_repeated_article_requests_only_count_one_view(): void
    {
        $article = Post::factory()->published()->create();

        $this->get(route('articles.show', $article->slug))->assertOk();
        $this->get(route('articles.show', $article->slug))->assertOk();

        $this->assertDatabaseHas('posts', ['id' => $article->id, 'view_count' => 1]);
    }

    public function test_future_and_deleted_articles_return_not_found(): void
    {
        $future = Post::factory()->published()->create(['published_at' => now()->addDay()]);
        $deleted = Post::factory()->published()->create();
        $deleted->delete();

        $this->get(route('articles.show', $future->slug))->assertNotFound();
        $this->get(route('articles.show', $deleted->slug))->assertNotFound();
    }

    public function test_demo_seeder_keeps_deleted_posts_without_duplicate_slugs(): void
    {
        User::factory()->admin()->create();
        $deleted = Post::factory()->published()->create(['slug' => 'demo-article-01']);
        $deleted->delete();

        $this->seed(DemoArticleSeeder::class);

        $this->assertSame(50, Post::withTrashed()->where('slug', 'like', 'demo-article-%')->count());
        $this->assertSoftDeleted($deleted);
    }

    public function test_admin_cannot_create_article_exceeding_content_limit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('admin.articles.store'), [
            'title' => 'Oversized article',
            'status' => 'DRAFT',
            'markdown_content' => str_repeat('a', Post::MAX_CONTENT_LENGTH + 1),
        ])->assertUnprocessable()->assertJsonValidationErrors('markdown_content');

        $this->assertDatabaseMissing('posts', ['title' => 'Oversized article']);
    }

    public function test_article_title_is_escaped_in_document_title(): void
    {
        $article = Post::factory()->published()->create([
            'title' => '</title><script>alert(1)</script>',
        ]);

        $this->get(route('articles.show', $article->slug))
            ->assertOk()
            ->assertSee(e($article->title), false)
            ->assertDontSee($article->title, false);
    }
}
