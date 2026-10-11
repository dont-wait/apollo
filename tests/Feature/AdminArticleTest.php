<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_articles(): void
    {
        $this->get('/admin/articles')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_manage_articles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/articles')
            ->assertForbidden();
    }

    public function test_admin_can_view_and_filter_article_list(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $article = Post::factory()->create([
            'category_id' => $category->id,
            'title' => 'Matching article',
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);
        Post::factory()->create(['title' => 'Different article']);

        $this->actingAs($admin)
            ->get(route('admin.articles.index', [
                'category_id' => $category->id,
                'status' => 'PUBLISHED',
            ]))
            ->assertOk()
            ->assertSeeText($article->title)
            ->assertDontSeeText('Different article');
    }

    public function test_admin_can_open_create_and_edit_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create([
            'title' => 'Article to edit',
            'markdown_content' => '# Existing Markdown',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSee('name="markdown_content"', false);

        $this->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertSeeText($article->title)
            ->assertSee($article->markdown_content);
    }

    public function test_admin_can_create_article_with_generated_slug_and_tags(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'A New Article',
                'slug' => '',
                'category_id' => $category->id,
                'tags' => $tags->modelKeys(),
                'excerpt' => 'A short summary.',
                'markdown_content' => '# Article content',
                'status' => 'PUBLISHED',
            ])
            ->assertRedirect();

        $article = Post::query()->where('slug', 'a-new-article')->firstOrFail();
        $response->assertRedirect(route('admin.articles.edit', $article));
        $this->assertSame($admin->id, $article->author_id);
        $this->assertSame('PUBLISHED', $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(
            $tags->modelKeys(),
            $article->tags()->orderBy('tags.id')->get()->modelKeys(),
        );
    }

    public function test_admin_can_upload_article_thumbnail_to_public_storage(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'Article with image',
                'markdown_content' => 'Content',
                'status' => 'DRAFT',
                'thumbnail' => UploadedFile::fake()->image('cover.png'),
            ])
            ->assertRedirect();

        $media = Media::query()->firstOrFail();
        Storage::disk('public')->assertExists($media->storage_key);
        $this->assertDatabaseHas('posts', [
            'slug' => 'article-with-image',
            'thumbnail_media_id' => $media->id,
        ]);
    }

    public function test_admin_can_update_article_and_sync_tags(): void
    {
        $admin = User::factory()->admin()->create();
        $originalTag = Tag::factory()->create();
        $replacementTag = Tag::factory()->create();
        $article = Post::factory()->create();
        $article->tags()->attach($originalTag);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'title' => 'Updated article',
                'slug' => '',
                'tags' => [$replacementTag->id],
                'excerpt' => 'Updated summary.',
                'markdown_content' => '# Updated content',
                'status' => 'DRAFT',
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame('updated-article', $article->slug);
        $this->assertSame([$replacementTag->id], $article->tags()->get()->modelKeys());
        $this->assertDatabaseMissing('post_tags', [
            'post_id' => $article->id,
            'tag_id' => $originalTag->id,
        ]);
    }

    public function test_admin_can_soft_delete_article(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.articles.destroy', $article))
            ->assertRedirect(route('admin.articles.index'));

        $this->assertSoftDeleted('posts', ['id' => $article->id]);
    }

    public function test_article_requires_markdown_content_and_a_valid_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Invalid article',
                'status' => 'UNKNOWN',
            ])
            ->assertRedirect(route('admin.articles.create'))
            ->assertSessionHasErrors(['markdown_content', 'status']);
    }
}
