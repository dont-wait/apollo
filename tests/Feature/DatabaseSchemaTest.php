<?php

namespace Tests\Feature;

use App\Models\AiSource;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_blog_schema_contains_the_core_tables_and_columns(): void
    {
        foreach (['categories', 'tags', 'media', 'posts', 'post_tags', 'comments', 'post_likes', 'bookmarks', 'ai_sources', 'ai_articles', 'ai_article_analysis', 'ai_topics', 'ai_article_topics', 'weekly_reviews', 'weekly_review_sources', 'automation_runs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} table to exist.");
        }

        $this->assertTrue(Schema::hasColumns('users', ['role', 'status', 'avatar_url', 'last_login_at', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('posts', ['author_id', 'category_id', 'weekly_review_id', 'status', 'published_at', 'deleted_at']));
    }

    public function test_post_relationships_persist_tags_and_author(): void
    {
        $post = Post::factory()->published()->create();
        $tag = Tag::factory()->create();

        $post->tags()->attach($tag);
        $post->load(['author', 'category', 'tags']);

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertInstanceOf(Category::class, $post->category);
        $this->assertTrue($post->tags->contains($tag));
        $this->assertSame('PUBLISHED', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_soft_deleted_posts_are_not_returned_by_default(): void
    {
        $post = Post::factory()->published()->create();

        $post->delete();

        $this->assertNull(Post::find($post->id));
        $this->assertNotNull(Post::withTrashed()->find($post->id));
    }

    public function test_database_rejects_a_published_post_without_a_publish_date(): void
    {
        $this->expectException(QueryException::class);

        Post::factory()->create(['status' => 'PUBLISHED']);
    }

    public function test_database_rejects_an_ai_source_with_an_invalid_trust_score(): void
    {
        $this->expectException(QueryException::class);

        AiSource::factory()->create(['trust_score' => 11]);
    }
}
