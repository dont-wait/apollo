<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AdminArticlePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_a_draft_article(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create(['status' => PostStatus::Draft->value]);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), $this->payload($article, [
                'status' => PostStatus::Published->value,
            ]))
            ->assertRedirect(route('admin.articles.edit', $article));

        $article->refresh();
        $this->assertSame(PostStatus::Published->value, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertNull($article->scheduled_at);
        $this->get(route('articles.show', $article->slug))->assertOk();
    }

    public function test_admin_can_schedule_an_article_for_the_future(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create();
        $scheduledAt = now()->addHour()->startOfMinute();

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), $this->payload($article, [
                'status' => PostStatus::Scheduled->value,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            ]))
            ->assertRedirect(route('admin.articles.edit', $article));

        $article->refresh();
        $this->assertSame(PostStatus::Scheduled->value, $article->status);
        $this->assertNull($article->published_at);
        $this->assertTrue($article->scheduled_at->equalTo($scheduledAt));
        $this->get(route('articles.show', $article->slug))->assertNotFound();
    }

    public function test_schedule_requires_a_future_publish_time(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), $this->payload($article, [
                'status' => PostStatus::Scheduled->value,
                'scheduled_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            ]))
            ->assertRedirect(route('admin.articles.edit', $article))
            ->assertSessionHasErrors('scheduled_at');

        $this->assertSame(PostStatus::Draft->value, $article->fresh()->status);
    }

    public function test_scheduler_publishes_due_articles_and_keeps_future_articles_scheduled(): void
    {
        $due = Post::factory()->create([
            'status' => PostStatus::Scheduled->value,
            'scheduled_at' => now()->subMinute(),
            'published_at' => null,
        ]);
        $future = Post::factory()->create([
            'status' => PostStatus::Scheduled->value,
            'scheduled_at' => now()->addHour(),
            'published_at' => null,
        ]);

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));

        $due->refresh();
        $future->refresh();
        $this->assertSame(PostStatus::Published->value, $due->status);
        $this->assertNotNull($due->published_at);
        $this->assertSame(PostStatus::Scheduled->value, $future->status);
        $this->assertNull($future->published_at);
        $this->get(route('articles.show', $due->slug))->assertOk();
        $this->get(route('articles.show', $future->slug))->assertNotFound();
    }

    public function test_failed_scheduled_publish_stays_scheduled_for_retry_and_is_logged(): void
    {
        $article = Post::factory()->create([
            'status' => PostStatus::Scheduled->value,
            'scheduled_at' => now()->subMinute(),
            'published_at' => null,
        ]);

        Post::updating(function (Post $post) use ($article): void {
            if ($post->getKey() === $article->getKey()
                && $post->status === PostStatus::Published->value) {
                throw new RuntimeException('Simulated scheduled publish failure.');
            }
        });
        Log::spy();

        $this->assertSame(0, Artisan::call('posts:publish-scheduled'));

        $article->refresh();
        $this->assertSame(PostStatus::Scheduled->value, $article->status);
        $this->assertNull($article->published_at);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_admin_can_archive_a_published_article_without_losing_publish_history(): void
    {
        $admin = User::factory()->admin()->create();
        $publishedAt = now()->subDay()->startOfSecond();
        $article = Post::factory()->published()->create(['published_at' => $publishedAt]);

        $this->actingAs($admin)
            ->put(route('admin.articles.update', $article), $this->payload($article, [
                'status' => PostStatus::Archived->value,
            ]))
            ->assertRedirect(route('admin.articles.edit', $article));

        $article->refresh();
        $this->assertSame(PostStatus::Archived->value, $article->status);
        $this->assertTrue($article->published_at->equalTo($publishedAt));
        $this->assertNull($article->scheduled_at);
        $this->get(route('articles.show', $article->slug))->assertNotFound();
    }

    public function test_publication_controls_are_available_on_article_form(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertSee('value="SCHEDULED"', false)
            ->assertSee('value="PUBLISHED"', false)
            ->assertSee('value="ARCHIVED"', false)
            ->assertSee('name="scheduled_at"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Post $article, array $overrides = []): array
    {
        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'category_id' => $article->category_id,
            'excerpt' => $article->excerpt,
            'markdown_content' => $article->markdown_content,
            'status' => $article->status,
            ...$overrides,
        ];
    }
}
