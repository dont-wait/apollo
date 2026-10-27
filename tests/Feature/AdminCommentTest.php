<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_comment_moderation(): void
    {
        $this->get(route('admin.comments.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_or_update_comments(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['status' => Comment::STATUS_PENDING]);

        $this->actingAs($user)->get(route('admin.comments.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.comments.update-status', $comment), [
            'status' => Comment::STATUS_VISIBLE,
            'version' => $comment->version,
        ])->assertForbidden();
    }

    public function test_admin_can_review_and_update_comment_status(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create(['title' => 'Moderated article']);
        $comment = Comment::factory()->create([
            'post_id' => $article->id,
            'status' => Comment::STATUS_PENDING,
            'content' => 'Needs review',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comments.index', ['status' => Comment::STATUS_PENDING]))
            ->assertOk()
            ->assertSeeText('Needs review')
            ->assertSeeText('Moderated article');

        $this->actingAs($admin)
            ->patch(route('admin.comments.update-status', $comment), [
                'status' => Comment::STATUS_VISIBLE,
                'version' => $comment->version,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'status' => Comment::STATUS_VISIBLE,
            'moderated_by' => $admin->id,
        ]);
    }

    public function test_admin_can_review_comments_from_a_soft_deleted_article(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create();
        $comment = Comment::factory()->create([
            'post_id' => $article->id,
            'status' => Comment::STATUS_PENDING,
            'content' => 'Comment on deleted article',
        ]);
        $article->delete();

        $this->actingAs($admin)
            ->get(route('admin.comments.index'))
            ->assertOk()
            ->assertSeeText($comment->content)
            ->assertSeeText('(deleted)');
    }

    public function test_admin_can_search_comments_and_bulk_update_status(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->published()->create(['title' => 'Searchable article']);
        $otherArticle = Post::factory()->published()->create(['title' => 'Other article']);
        $firstComment = Comment::factory()->create([
            'post_id' => $article->id,
            'content' => 'First searchable comment',
        ]);
        $secondComment = Comment::factory()->create([
            'post_id' => $article->id,
            'content' => 'Second searchable comment',
        ]);
        Comment::factory()->create([
            'post_id' => $otherArticle->id,
            'content' => 'Do not show this comment',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comments.index', ['article' => 'Searchable article']))
            ->assertOk()
            ->assertSeeText('First searchable comment')
            ->assertSeeText('Second searchable comment')
            ->assertDontSeeText('Do not show this comment');

        $this->actingAs($admin)
            ->patch(route('admin.comments.bulk-status'), [
                'comment_ids' => [$firstComment->id, $secondComment->id],
                'comment_versions' => [
                    $firstComment->id => $firstComment->version,
                    $secondComment->id => $secondComment->version,
                ],
                'status' => Comment::STATUS_HIDDEN,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'id' => $firstComment->id,
            'status' => Comment::STATUS_HIDDEN,
            'moderated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('comments', [
            'id' => $secondComment->id,
            'status' => Comment::STATUS_HIDDEN,
            'moderated_by' => $admin->id,
        ]);
    }

    public function test_stale_moderation_cannot_restore_a_user_deleted_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $article = Post::factory()->published()->create();
        $comment = Comment::factory()->create([
            'post_id' => $article->id,
            'user_id' => $owner->id,
            'status' => Comment::STATUS_VISIBLE,
        ]);
        $staleVersion = $comment->version;

        $this->actingAs($owner)
            ->deleteJson(route('articles.comments.destroy', [
                'article' => $article->slug,
                'comment' => $comment,
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('admin.comments.update-status', $comment), [
                'status' => Comment::STATUS_VISIBLE,
                'version' => $staleVersion,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'status' => Comment::STATUS_DELETED,
        ]);
    }

    public function test_admin_must_use_explicit_restore_action_for_deleted_comments(): void
    {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->deleted()->create();

        $this->actingAs($admin)
            ->patch(route('admin.comments.update-status', $comment), [
                'status' => Comment::STATUS_VISIBLE,
                'version' => $comment->version,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $comment->refresh();

        $this->actingAs($admin)
            ->patch(route('admin.comments.update-status', $comment), [
                'status' => Comment::STATUS_VISIBLE,
                'version' => $comment->version,
                'restore' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'status' => Comment::STATUS_VISIBLE,
        ]);
    }
}
