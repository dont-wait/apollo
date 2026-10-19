<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_saved_articles(): void
    {
        $this->get(route('bookmarks.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_change_bookmarks(): void
    {
        $post = Post::factory()->published()->create();

        $this->postJson(route('bookmarks.store', $post))
            ->assertUnauthorized();
    }

    public function test_user_can_bookmark_and_unbookmark_a_published_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        $bookmarkResponse = $this->actingAs($user)
            ->postJson(route('bookmarks.store', $post));

        $bookmarkResponse->assertOk()
            ->assertJson([
                'bookmarked' => true,
                'bookmarks_count' => 1,
            ]);
        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $unbookmarkResponse = $this->actingAs($user)
            ->deleteJson(route('bookmarks.destroy', $post));

        $unbookmarkResponse->assertOk()
            ->assertJson([
                'bookmarked' => false,
                'bookmarks_count' => 0,
            ]);
        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_repeated_bookmark_requests_are_idempotent(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        $this->actingAs($user)->postJson(route('bookmarks.store', $post))->assertOk();
        $response = $this->actingAs($user)->postJson(route('bookmarks.store', $post));

        $response->assertOk()->assertJson([
            'bookmarked' => true,
            'bookmarks_count' => 1,
        ]);
        $this->assertDatabaseCount('bookmarks', 1);
    }

    public function test_repeated_unbookmark_requests_are_idempotent(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('bookmarks.destroy', $post));

        $response->assertOk()->assertJson([
            'bookmarked' => false,
            'bookmarks_count' => 0,
        ]);
    }

    public function test_user_cannot_bookmark_an_unpublished_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('bookmarks.store', $post))
            ->assertNotFound();

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_cannot_unbookmark_an_unpublished_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        $user->bookmarkedPosts()->attach($post);
        $post->update(['status' => 'DRAFT', 'published_at' => null]);

        $this->actingAs($user)
            ->deleteJson(route('bookmarks.destroy', $post))
            ->assertNotFound();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_guest_article_save_link_preserves_the_article_path_for_login(): void
    {
        $post = Post::factory()->published()->create();

        $this->get(route('articles.show', ['article' => $post->slug]))
            ->assertSee(route('login', ['redirect' => '/articles/'.$post->slug]), false);
    }

    public function test_saved_articles_only_include_visible_posts_owned_by_the_current_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $savedPost = Post::factory()->published()->create(['title' => 'Saved article']);
        $otherPost = Post::factory()->published()->create(['title' => 'Another user article']);
        $hiddenPost = Post::factory()->published()->create(['title' => 'Hidden saved article']);

        $user->bookmarkedPosts()->attach($savedPost);
        $otherUser->bookmarkedPosts()->attach($otherPost);
        $user->bookmarkedPosts()->attach($hiddenPost);
        $hiddenPost->update(['status' => 'DRAFT', 'published_at' => null]);

        $response = $this->actingAs($user)->get(route('bookmarks.index'));

        $response->assertOk()
            ->assertViewIs('bookmarks.index')
            ->assertSee($savedPost->title, false)
            ->assertDontSee($otherPost->title, false)
            ->assertDontSee($hiddenPost->title, false);
    }

    public function test_saved_articles_are_paginated_in_bookmark_order(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $posts = Post::factory()->published()->count(11)->create();

        foreach ($posts as $index => $post) {
            $user->bookmarkedPosts()->attach($post, [
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $response = $this->actingAs($user)->get(route('bookmarks.index'));
        $paginator = $response->viewData('posts');

        $response->assertOk();
        $this->assertCount(10, $paginator->items());
        $this->assertTrue($paginator->hasPages());
        $this->assertSame($posts->first()->id, $paginator->first()->id);
    }

    public function test_home_feed_marks_bookmarked_posts_for_the_current_user(): void
    {
        $user = User::factory()->create();
        $bookmarkedPost = Post::factory()->published()->create();
        $unbookmarkedPost = Post::factory()->published()->create();
        $user->bookmarkedPosts()->attach($bookmarkedPost);

        $response = $this->actingAs($user)->getJson(route('home'));
        $posts = collect($response->json('data'))->keyBy('id');

        $response->assertOk();
        $this->assertTrue($posts->get($bookmarkedPost->id)['bookmarked']);
        $this->assertFalse($posts->get($unbookmarkedPost->id)['bookmarked']);
    }

    public function test_article_page_exposes_bookmark_state_and_count(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create();
        $user->bookmarkedPosts()->attach($post);

        $response = $this->actingAs($user)
            ->get(route('articles.show', ['article' => $post->slug]));

        $response->assertOk()
            ->assertViewHas('isBookmarked', true);
        $this->assertSame(1, $response->viewData('article')->bookmarks_count);
    }
}
