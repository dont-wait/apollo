<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticleCommentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_read_visible_root_comments_with_reply_counts(): void
    {
        $article = Post::factory()->published()->create();
        $author = User::factory()->create(['name' => 'Ada Lovelace']);
        $root = Comment::factory()->create([
            'post_id' => $article->id,
            'user_id' => $author->id,
            'content' => 'A visible root comment',
        ]);
        Comment::factory()->create([
            'post_id' => $article->id,
            'user_id' => User::factory(),
            'parent_id' => $root->id,
            'content' => 'A visible reply',
        ]);
        Comment::factory()->hidden()->create([
            'post_id' => $article->id,
            'user_id' => User::factory(),
            'content' => 'A hidden root comment',
        ]);
        Comment::factory()->create([
            'post_id' => $article->id,
            'user_id' => User::factory(),
            'parent_id' => $root->id,
            'status' => 'HIDDEN',
            'content' => 'A hidden reply',
        ]);

        $this->getJson(route('articles.comments.index', ['article' => $article->slug]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.content', 'A visible root comment')
            ->assertJsonPath('data.0.author.name', 'Ada Lovelace')
            ->assertJsonPath('data.0.author.initial', 'A')
            ->assertJsonPath('data.0.replies_count', 1)
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_guest_can_load_visible_replies_for_a_root_comment(): void
    {
        $article = Post::factory()->published()->create();
        $root = Comment::factory()->create(['post_id' => $article->id]);
        $olderReply = Comment::factory()->create([
            'post_id' => $article->id,
            'parent_id' => $root->id,
            'content' => 'The first reply',
            'created_at' => now()->subMinute(),
        ]);
        $newerReply = Comment::factory()->create([
            'post_id' => $article->id,
            'parent_id' => $root->id,
            'content' => 'The latest reply',
        ]);
        Comment::factory()->hidden()->create([
            'post_id' => $article->id,
            'parent_id' => $root->id,
        ]);

        $this->getJson(route('articles.comments.replies', [
            'article' => $article->slug,
            'comment' => $root,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerReply->id)
            ->assertJsonPath('data.0.content', 'The latest reply')
            ->assertJsonPath('data.1.id', $olderReply->id);
    }

    public function test_article_page_renders_the_guest_comment_prompt(): void
    {
        $article = Post::factory()->published()->create();

        $this->get(route('articles.show', ['article' => $article->slug]))
            ->assertOk()
            ->assertSeeText('Comments')
            ->assertSeeText('Join the conversation')
            ->assertSeeText('Sign in to comment');
    }

    public function test_guest_cannot_create_a_comment(): void
    {
        $article = Post::factory()->published()->create();

        $this->postJson(route('articles.comments.store', ['article' => $article->slug]), [
            'content' => 'Guest comment',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('comments', ['content' => 'Guest comment']);
    }

    public function test_authenticated_user_can_create_a_root_comment_and_reply(): void
    {
        $article = Post::factory()->published()->create();
        $user = User::factory()->create(['name' => 'Grace Hopper']);

        $rootResponse = $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => '  A thoughtful root comment  '],
        );

        $rootResponse->assertCreated()
            ->assertJsonPath('data.content', 'A thoughtful root comment')
            ->assertJsonPath('data.status', Comment::STATUS_PENDING)
            ->assertJsonPath('data.author.name', 'Grace Hopper');
        $root = Comment::query()->where('content', 'A thoughtful root comment')->firstOrFail();
        $root->update(['status' => Comment::STATUS_VISIBLE]);

        $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => 'A helpful reply', 'parent_id' => $root->id],
        )->assertCreated()->assertJsonPath('data.content', 'A helpful reply');

        $this->assertDatabaseHas('comments', [
            'post_id' => $article->id,
            'user_id' => $user->id,
            'parent_id' => $root->id,
            'status' => Comment::STATUS_PENDING,
            'content' => 'A helpful reply',
        ]);
    }

    public function test_invalid_cursors_return_validation_error_instead_of_server_error(): void
    {
        $article = Post::factory()->published()->create();
        $cursor = rtrim(strtr(base64_encode(json_encode(['_pointsToNextItems' => true], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $this->getJson(route('articles.comments.index', [
            'article' => $article->slug,
            'comments_cursor' => $cursor,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The pagination cursor is invalid.');
    }

    public function test_pending_comments_are_not_public_and_hidden_threads_return_not_found(): void
    {
        $article = Post::factory()->published()->create();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => 'Waiting for review'],
        )->assertCreated();
        $pendingId = Comment::query()->where('content', 'Waiting for review')->value('id');

        $this->getJson(route('articles.comments.index', ['article' => $article->slug]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->assertSame(Comment::STATUS_PENDING, $response->json('data.status'));

        $hidden = Comment::factory()->hidden()->create(['post_id' => $article->id]);
        $this->getJson(route('articles.comments.replies', [
            'article' => $article->slug,
            'comment' => $hidden,
            'replies_cursor' => 'invalid',
        ]))->assertNotFound();
        $this->assertNotNull($pendingId);
    }

    public function test_comment_validation_rejects_invalid_or_cross_article_parents(): void
    {
        $article = Post::factory()->published()->create();
        $otherArticle = Post::factory()->published()->create();
        $user = User::factory()->create();
        $otherRoot = Comment::factory()->create(['post_id' => $otherArticle->id]);
        $root = Comment::factory()->create(['post_id' => $article->id]);
        $nestedReply = Comment::factory()->create([
            'post_id' => $article->id,
            'parent_id' => $root->id,
        ]);

        $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => ''],
        )->assertUnprocessable()->assertJsonValidationErrors('content');

        $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => 'Cross article reply', 'parent_id' => $otherRoot->id],
        )->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => 'Nested reply', 'parent_id' => $nestedReply->id],
        )->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_comments_are_unavailable_for_unpublished_articles(): void
    {
        $article = Post::factory()->create();
        $user = User::factory()->create();

        $this->getJson(route('articles.comments.index', ['article' => $article->slug]))
            ->assertNotFound();
        $this->actingAs($user)->postJson(
            route('articles.comments.store', ['article' => $article->slug]),
            ['content' => 'Should not be stored'],
        )->assertNotFound();
    }

    public function test_comment_creation_is_rate_limited_per_authenticated_user(): void
    {
        $article = Post::factory()->published()->create();
        $user = User::factory()->create();
        $url = route('articles.comments.store', ['article' => $article->slug]);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($user)->postJson($url, ['content' => "Comment {$attempt}"])->assertCreated();
        }

        $this->actingAs($user)->postJson($url, ['content' => 'Rate limited comment'])
            ->assertTooManyRequests();
    }

    public function test_comment_list_query_count_does_not_grow_with_more_comments(): void
    {
        $article = Post::factory()->published()->create();
        Comment::factory()->count(2)->create(['post_id' => $article->id]);

        DB::enableQueryLog();
        $this->getJson(route('articles.comments.index', ['article' => $article->slug]))->assertOk();
        $smallResultQueryCount = count(DB::getQueryLog());
        DB::flushQueryLog();

        Comment::factory()->count(20)->create(['post_id' => $article->id]);
        DB::flushQueryLog();
        $this->getJson(route('articles.comments.index', ['article' => $article->slug]))->assertOk();
        $largeResultQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($smallResultQueryCount, $largeResultQueryCount);
    }
}
