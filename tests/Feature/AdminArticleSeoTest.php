<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminArticleSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_an_article_with_seo_metadata(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('name="seo_title"', false)
            ->assertSee('name="seo_description"', false)
            ->assertSee('name="canonical_url"', false);

        $this->post(route('admin.articles.store'), [
            'title' => 'SEO metadata article',
            'markdown_content' => '# Article content',
            'status' => 'DRAFT',
            'seo_title' => 'Search result title',
            'seo_description' => 'Search result description',
            'canonical_url' => 'https://example.com/articles/seo-metadata-article',
        ])->assertRedirect(route('admin.articles.index'));

        $this->assertDatabaseHas('posts', [
            'slug' => 'seo-metadata-article',
            'seo_title' => 'Search result title',
            'seo_description' => 'Search result description',
            'canonical_url' => 'https://example.com/articles/seo-metadata-article',
        ]);
    }

    public function test_admin_can_update_article_seo_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'title' => $article->title,
                'slug' => $article->slug,
                'markdown_content' => $article->markdown_content,
                'status' => $article->status,
                'original_status' => $article->status,
                'seo_title' => 'Updated SEO title',
                'seo_description' => 'Updated SEO description',
                'canonical_url' => 'https://example.com/articles/updated-canonical',
            ])
            ->assertRedirect(route('admin.articles.edit', $article));

        $this->assertDatabaseHas('posts', [
            'id' => $article->id,
            'seo_title' => 'Updated SEO title',
            'seo_description' => 'Updated SEO description',
            'canonical_url' => 'https://example.com/articles/updated-canonical',
        ]);
    }

    public function test_admin_cannot_save_a_non_http_canonical_url(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Invalid canonical article',
                'markdown_content' => '# Article content',
                'status' => 'DRAFT',
                'canonical_url' => 'javascript:alert(1)',
            ])
            ->assertRedirect(route('admin.articles.create'))
            ->assertSessionHasErrors('canonical_url');

        $this->assertDatabaseMissing('posts', [
            'slug' => 'invalid-canonical-article',
        ]);
    }

    public function test_existing_canonical_url_is_preserved_when_the_admin_leaves_it_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create([
            'canonical_url' => 'https://example.com/articles/stable-canonical',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'title' => $article->title,
                'slug' => $article->slug,
                'markdown_content' => $article->markdown_content,
                'status' => 'PUBLISHED',
                'original_status' => 'PUBLISHED',
                'canonical_url' => null,
            ])
            ->assertRedirect(route('admin.articles.edit', $article));

        $this->assertDatabaseHas('posts', [
            'id' => $article->id,
            'canonical_url' => 'https://example.com/articles/stable-canonical',
        ]);
    }

    public function test_manual_publication_generates_a_canonical_url_when_it_is_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create([
            'slug' => 'manual-canonical-article',
            'canonical_url' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'title' => $article->title,
                'slug' => $article->slug,
                'markdown_content' => $article->markdown_content,
                'status' => 'PUBLISHED',
                'original_status' => 'DRAFT',
                'canonical_url' => null,
            ])
            ->assertRedirect(route('admin.articles.edit', $article));

        $this->assertDatabaseHas('posts', [
            'id' => $article->id,
            'status' => 'PUBLISHED',
            'canonical_url' => route('articles.show', ['article' => $article->slug]),
        ]);
    }
}
