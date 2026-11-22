<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\ArticleRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function test_article_list_shows_status_overview_and_engagement_metrics(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create(['name' => 'Ada Lovelace']);
        $tag = Tag::factory()->create(['name' => 'Inference']);
        $published = Post::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'Live article',
            'view_count' => 1234,
        ]);
        $published->tags()->attach($tag);
        $published->bookmarks()->attach(User::factory()->create());
        Comment::factory()->create(['post_id' => $published->id]);
        Comment::factory()->hidden()->create(['post_id' => $published->id]);
        Post::factory()->create([
            'title' => 'Queued article',
            'status' => 'SCHEDULED',
            'scheduled_at' => now()->addDays(2),
        ]);
        Post::factory()->create(['title' => 'Working draft']);
        Post::factory()->create(['title' => 'Old article', 'status' => 'ARCHIVED']);

        $this->actingAs($admin)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSeeText('Showing 1–4 of 4 articles')
            ->assertSeeText('Ada Lovelace')
            ->assertSeeText('#Inference')
            ->assertSeeText('1,234')
            ->assertSeeText('min read')
            ->assertSeeText('Releases in')
            ->assertSeeText('Edited')
            ->assertSee('/articles/'.$published->slug, false)
            ->assertViewHas('statusCounts', fn ($counts): bool => $counts['PUBLISHED'] === 1
                && $counts['SCHEDULED'] === 1
                && $counts['DRAFT'] === 1
                && $counts['ARCHIVED'] === 1)
            ->assertViewHas('articles', fn ($articles): bool => $articles->first()->bookmarks_count === 0
                || $articles->contains(fn (Post $post): bool => $post->bookmarks_count === 1 && $post->comments_count === 1));
    }

    public function test_article_list_can_filter_by_author_and_search_author_name(): void
    {
        $admin = User::factory()->admin()->create();
        $ada = User::factory()->create(['name' => 'Ada Lovelace']);
        $grace = User::factory()->create(['name' => 'Grace Hopper']);
        Post::factory()->create(['author_id' => $ada->id, 'title' => 'By Ada']);
        Post::factory()->create(['author_id' => $grace->id, 'title' => 'By Grace']);

        $this->actingAs($admin)
            ->get(route('admin.articles.index', ['author_id' => $ada->id]))
            ->assertOk()
            ->assertSeeText('By Ada')
            ->assertDontSeeText('By Grace');

        $this->get(route('admin.articles.index', ['q' => 'Hopper']))
            ->assertOk()
            ->assertSeeText('By Grace')
            ->assertDontSeeText('By Ada');
    }

    public function test_article_list_can_be_sorted_by_views_and_title(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create(['title' => 'Zeta', 'view_count' => 50]);
        Post::factory()->create(['title' => 'Alpha', 'view_count' => 5]);

        $this->actingAs($admin)
            ->get(route('admin.articles.index', ['sort' => 'views']))
            ->assertOk()
            ->assertSeeInOrder(['Zeta', 'Alpha']);

        $this->get(route('admin.articles.index', ['sort' => 'title']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha', 'Zeta']);

        $this->get(route('admin.articles.index', ['sort' => 'bogus']))
            ->assertSessionHasErrors('sort');
    }

    public function test_deleted_articles_appear_in_trash_and_can_be_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create(['title' => 'Accidentally deleted']);
        Post::factory()->create(['title' => 'Still here']);

        $this->actingAs($admin)
            ->delete(route('admin.articles.destroy', $article))
            ->assertRedirect(route('admin.articles.index'));
        $this->assertSoftDeleted($article);

        $this->get(route('admin.articles.index'))
            ->assertOk()
            ->assertDontSeeText('Accidentally deleted')
            ->assertSeeText('Still here')
            ->assertViewHas('trashedCount', 1);

        $this->get(route('admin.articles.index', ['trashed' => 1]))
            ->assertOk()
            ->assertSeeText('Accidentally deleted')
            ->assertSeeText('Restore')
            ->assertSeeText('Deleted')
            ->assertDontSeeText('Still here');

        $this->patch(route('admin.articles.restore', $article))
            ->assertRedirect(route('admin.articles.index'))
            ->assertSessionHas('status', 'Article "Accidentally deleted" restored.');

        $this->assertNotSoftDeleted($article);
        $this->assertSame('PUBLISHED', $article->fresh()->status);
        $this->get(route('admin.articles.index'))->assertSeeText('Accidentally deleted');
    }

    public function test_restoring_an_article_that_is_not_deleted_is_a_noop(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.articles.restore', $article))
            ->assertRedirect(route('admin.articles.index'));

        $this->assertNotSoftDeleted($article);
    }

    public function test_non_admin_cannot_restore_articles(): void
    {
        $article = Post::factory()->create();
        $article->delete();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.articles.restore', $article))
            ->assertForbidden();

        $this->assertSoftDeleted($article);
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
            ->assertSee('name="markdown_content"', false)
            ->assertSee('Write')
            ->assertSee('Preview')
            ->assertSeeText('Nothing to preview yet');

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

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'A New Article',
                'slug' => '',
                'category_id' => $category->id,
                'tags' => $tags->modelKeys(),
                'excerpt' => 'A short summary.',
                'markdown_content' => '# Article content',
                'status' => 'PUBLISHED',
            ])
            ->assertRedirect(route('admin.articles.index'))
            ->assertSessionHas('status', 'Article created successfully.');

        $article = Post::query()->where('slug', 'a-new-article')->firstOrFail();
        $this->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSeeText('Article created successfully.');
        $this->assertSame($admin->id, $article->author_id);
        $this->assertSame('PUBLISHED', $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(
            $tags->modelKeys(),
            $article->tags()->orderBy('tags.id')->get()->modelKeys(),
        );
    }

    public function test_admin_can_create_article_with_a_new_tag(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'Article with a new tag',
                'markdown_content' => '# Article content',
                'status' => 'DRAFT',
                'new_tags' => ['Editorial'],
            ])
            ->assertRedirect();

        $article = Post::query()->where('slug', 'article-with-a-new-tag')->firstOrFail();
        $tag = Tag::query()->where('slug', 'editorial')->firstOrFail();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Editorial',
            'slug' => 'editorial',
        ]);
        $this->assertSame([$tag->id], $article->tags()->get()->modelKeys());
    }

    public function test_admin_reuses_a_tag_with_a_matching_slug_when_creating_an_article(): void
    {
        $admin = User::factory()->admin()->create();
        $existingTag = Tag::factory()->create([
            'name' => 'Editorial',
            'slug' => 'editorial',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'Article with an existing tag',
                'markdown_content' => '# Article content',
                'status' => 'DRAFT',
                'new_tags' => ['Editorial!'],
            ])
            ->assertRedirect();

        $article = Post::query()->where('slug', 'article-with-an-existing-tag')->firstOrFail();

        $this->assertSame(1, Tag::query()->where('slug', 'editorial')->count());
        $this->assertSame([$existingTag->id], $article->tags()->get()->modelKeys());
    }

    public function test_admin_does_not_create_a_new_tag_when_article_validation_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Invalid article',
                'status' => 'DRAFT',
                'new_tags' => ['Not persisted'],
            ])
            ->assertRedirect(route('admin.articles.create'))
            ->assertSessionHasErrors('markdown_content');

        $this->assertDatabaseMissing('tags', [
            'name' => 'Not persisted',
            'slug' => 'not-persisted',
        ]);
    }

    public function test_admin_can_upload_article_thumbnail_to_rustfs(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        $disk = Storage::fake('rustfs');
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
        $disk->assertExists($media->storage_key);
        $this->assertDatabaseHas('posts', [
            'slug' => 'article-with-image',
            'thumbnail_media_id' => $media->id,
        ]);
    }

    public function test_admin_upload_is_removed_when_article_database_transaction_fails(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        $disk = Storage::fake('rustfs');
        $admin = User::factory()->admin()->create();
        Post::created(function (Post $post): void {
            DB::table('missing_article_table')->insert(['id' => $post->id]);
        });

        $this->actingAs($admin)
            ->post(route('admin.articles.store'), [
                'title' => 'Article with rolled back upload',
                'markdown_content' => 'Content',
                'status' => 'DRAFT',
                'thumbnail' => UploadedFile::fake()->image('cover.png'),
            ])
            ->assertInternalServerError();

        $this->assertSame([], $disk->allFiles('articles'));
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_admin_replaces_thumbnail_and_removes_unreferenced_old_file(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        $disk = Storage::fake('rustfs');
        $admin = User::factory()->admin()->create();
        $disk->put('articles/old-cover.png', 'old image');
        $oldMedia = Media::factory()->create([
            'uploaded_by' => $admin->id,
            'storage_key' => 'articles/old-cover.png',
        ]);
        $article = Post::factory()->create([
            'thumbnail_media_id' => $oldMedia->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'revision' => ArticleRevision::for($article),
                'title' => 'Article with replacement thumbnail',
                'markdown_content' => 'Updated content',
                'status' => 'DRAFT',
                'thumbnail' => UploadedFile::fake()->image('new-cover.png'),
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertNotSame($oldMedia->id, $article->thumbnail_media_id);
        $this->assertDatabaseMissing('media', ['id' => $oldMedia->id]);
        $disk->assertMissing('articles/old-cover.png');
        $disk->assertExists($article->thumbnail->storage_key);
    }

    public function test_admin_keeps_old_thumbnail_file_referenced_by_a_soft_deleted_article(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        $disk = Storage::fake('rustfs');
        $admin = User::factory()->admin()->create();
        $disk->put('articles/shared-cover.png', 'shared image');
        $oldMedia = Media::factory()->create([
            'uploaded_by' => $admin->id,
            'storage_key' => 'articles/shared-cover.png',
        ]);
        $article = Post::factory()->create([
            'thumbnail_media_id' => $oldMedia->id,
        ]);
        $trashedArticle = Post::factory()->create([
            'thumbnail_media_id' => $oldMedia->id,
        ]);
        $trashedArticle->delete();

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'revision' => ArticleRevision::for($article),
                'title' => 'Article with replacement thumbnail',
                'markdown_content' => 'Updated content',
                'status' => 'DRAFT',
                'thumbnail' => UploadedFile::fake()->image('new-cover.png'),
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertNotSame($oldMedia->id, $article->thumbnail_media_id);
        $this->assertDatabaseHas('media', ['id' => $oldMedia->id]);
        $disk->assertExists('articles/shared-cover.png');
        $disk->assertExists($article->thumbnail->storage_key);
        $this->assertSoftDeleted('posts', ['id' => $trashedArticle->id]);
    }

    public function test_admin_keeps_old_thumbnail_referenced_in_article_markdown(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        $disk = Storage::fake('rustfs');
        $admin = User::factory()->admin()->create();
        $disk->put('articles/old-cover.png', 'old image');
        $oldMedia = Media::factory()->create([
            'uploaded_by' => $admin->id,
            'storage_key' => 'articles/old-cover.png',
            'url' => '/storage/articles/old-cover.png',
        ]);
        $article = Post::factory()->create([
            'thumbnail_media_id' => $oldMedia->id,
            'markdown_content' => '![Old cover](/storage/articles/old-cover.png)',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), [
                'revision' => ArticleRevision::for($article),
                'title' => 'Article with a referenced old thumbnail',
                'markdown_content' => '![Old cover](/storage/articles/old-cover.png)',
                'status' => 'DRAFT',
                'thumbnail' => UploadedFile::fake()->image('new-cover.png'),
            ])
            ->assertRedirect();

        $this->assertModelExists($oldMedia);
        $disk->assertExists('articles/old-cover.png');
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
                'revision' => ArticleRevision::for($article),
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

    public function test_admin_form_keeps_all_tags_unselected_after_failed_update(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $article = Post::factory()->create();
        $article->tags()->attach($tag);

        $this->actingAs($admin)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), [
                'revision' => ArticleRevision::for($article),
                'title' => '',
                'slug' => $article->slug,
                'markdown_content' => '# Content',
                'status' => 'DRAFT',
                'tags_submitted' => '1',
            ])
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHasErrors('title');

        $this->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertDontSee('name="tags[]" value="'.$tag->id.'" checked', false);

        $this->assertDatabaseHas('post_tags', [
            'post_id' => $article->id,
            'tag_id' => $tag->id,
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
