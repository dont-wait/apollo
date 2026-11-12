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
                'revision' => \App\Support\ArticleRevision::for($article),
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
                'revision' => \App\Support\ArticleRevision::for($article),
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
                'revision' => \App\Support\ArticleRevision::for($article),
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
    public function test_renaming_an_article_updates_its_generated_canonical_url(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create([
            'slug' => 'previous-slug',
            'canonical_url' => \App\Support\SiteUrl::article('previous-slug'),
        ]);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'revision' => \App\Support\ArticleRevision::for($article),
            'title' => $article->title,
            'slug' => 'current-slug',
            'markdown_content' => $article->markdown_content,
            'status' => 'PUBLISHED',
            'original_status' => 'PUBLISHED',
            'canonical_url' => $article->canonical_url,
        ])->assertRedirect(route('admin.articles.edit', $article));

        $this->assertSame(\App\Support\SiteUrl::article('current-slug'), $article->fresh()->canonical_url);
    }

    public function test_renaming_does_not_change_a_custom_canonical_url(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create([
            'slug' => 'first-slug',
            'canonical_url' => 'https://partner.example/story',
        ]);

        $this->actingAs($admin)->put(route('admin.articles.update', $article), [
            'revision' => \App\Support\ArticleRevision::for($article),
            'title' => $article->title,
            'slug' => 'second-slug',
            'markdown_content' => $article->markdown_content,
            'status' => 'PUBLISHED',
            'original_status' => 'PUBLISHED',
            'canonical_url' => $article->canonical_url,
        ])->assertRedirect(route('admin.articles.edit', $article));

        $this->assertSame('https://partner.example/story', $article->fresh()->canonical_url);
    }

    public function test_untrusted_request_host_is_not_used_to_generate_persisted_canonical(): void
    {
        $admin = User::factory()->admin()->create();
        config(['app.url' => 'https://apollo.example']);

        $this->actingAs($admin)
            ->withServerVariables(['HTTP_HOST' => 'attacker.example'])
            ->post('/admin/articles', [
                'title' => 'Trusted canonical',
                'markdown_content' => '# Article content',
                'status' => 'PUBLISHED',
            ])->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'slug' => 'trusted-canonical',
            'canonical_url' => 'https://apollo.example/articles/trusted-canonical',
        ]);
    }

    public function test_revision_matches_after_reloading_a_just_created_article(): void
    {
        // Factory-created posts may not include every nullable column in the
        // in-memory model, while a DB-hydrated post includes nulls for them.
        $article = Post::factory()->create();

        $this->assertSame(
            \App\Support\ArticleRevision::for($article),
            \App\Support\ArticleRevision::for($article->fresh()),
        );
    }

    public function test_an_article_update_without_a_revision_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create(['title' => 'Original title']);

        $this->actingAs($admin)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), [
                'title' => 'Unauthorized overwrite',
                'slug' => $article->slug,
                'markdown_content' => $article->markdown_content,
                'status' => $article->status,
            ])
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHasErrors('revision');

        $this->assertSame('Original title', $article->fresh()->title);
    }

    public function test_stale_admin_form_cannot_overwrite_newer_seo_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create([
            'seo_title' => 'Original title',
            'seo_description' => 'Original description',
        ]);
        $oldRevision = \App\Support\ArticleRevision::for($article);

        $article->update(['seo_title' => 'Newer title']);

        $this->actingAs($admin)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), [
                'revision' => $oldRevision,
                'title' => $article->title,
                'slug' => $article->slug,
                'markdown_content' => $article->markdown_content,
                'status' => 'PUBLISHED',
                'original_status' => 'PUBLISHED',
                'seo_title' => 'Original title',
                'seo_description' => 'Overwrite attempt',
            ])
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHasErrors('revision');

        $this->assertSame('Newer title', $article->fresh()->seo_title);
        $this->assertSame('Original description', $article->fresh()->seo_description);
    }
}
