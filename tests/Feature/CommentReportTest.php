<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentReportTest extends TestCase
{
    use RefreshDatabase;

    private function reportUrl(Comment $comment): string
    {
        return route('articles.comments.report', ['article' => $comment->post->slug, 'comment' => $comment->id]);
    }

    public function test_guest_cannot_report(): void
    {
        $comment = Comment::factory()->create();

        $this->postJson($this->reportUrl($comment), ['reason' => 'SPAM'])->assertUnauthorized();
    }

    public function test_user_can_report_a_comment_once(): void
    {
        $comment = Comment::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($reader)->postJson($this->reportUrl($comment), ['reason' => 'OFFENSIVE', 'details' => 'Rude'])
            ->assertCreated();
        $this->actingAs($reader)->postJson($this->reportUrl($comment), ['reason' => 'SPAM'])->assertOk();

        $this->assertDatabaseCount('comment_reports', 1);
        $this->assertDatabaseHas('comment_reports', ['comment_id' => $comment->id, 'reporter_id' => $reader->id, 'reason' => 'OFFENSIVE', 'status' => 'OPEN']);
    }

    public function test_user_cannot_report_own_or_hidden_comment_or_use_invalid_reason(): void
    {
        $own = Comment::factory()->create();
        $hidden = Comment::factory()->hidden()->create();
        $other = Comment::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($own->user)->postJson($this->reportUrl($own), ['reason' => 'SPAM'])->assertForbidden();
        $this->actingAs($reader)->postJson($this->reportUrl($hidden), ['reason' => 'SPAM'])->assertNotFound();
        $this->actingAs($reader)->postJson($this->reportUrl($other), ['reason' => 'nope'])->assertUnprocessable();
    }

    public function test_non_admin_cannot_access_reports(): void
    {
        $report = CommentReport::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('admin.comment-reports.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->patch(route('admin.comment-reports.update', $report), ['action' => 'dismiss'])->assertForbidden();
    }

    public function test_admin_can_list_and_hide_reported_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->create(['content' => 'Toxic words here']);
        $first = CommentReport::factory()->create(['comment_id' => $comment->id]);
        $second = CommentReport::factory()->create(['comment_id' => $comment->id]);

        $this->actingAs($admin)->get(route('admin.comment-reports.index'))
            ->assertOk()->assertSeeText('Toxic words here');

        $this->actingAs($admin)->patch(route('admin.comment-reports.update', $first), ['action' => 'hide'])->assertRedirect();

        $this->assertSame(Comment::STATUS_HIDDEN, $comment->fresh()->status);
        $this->assertSame('RESOLVED', $first->fresh()->status);
        $this->assertSame('RESOLVED', $second->fresh()->status);
    }

    public function test_admin_can_dismiss_report_without_touching_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $report = CommentReport::factory()->create();

        $this->actingAs($admin)->patch(route('admin.comment-reports.update', $report), ['action' => 'dismiss'])->assertRedirect();

        $this->assertSame('DISMISSED', $report->fresh()->status);
        $this->assertSame(Comment::STATUS_VISIBLE, $report->comment->fresh()->status);
    }

    public function test_hiding_an_already_deleted_comment_keeps_it_deleted_and_closes_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->deleted()->create(['deleted_by' => User::factory()->create()->id, 'deleted_at' => now()]);
        $report = CommentReport::factory()->create(['comment_id' => $comment->id]);

        $this->actingAs($admin)->patch(route('admin.comment-reports.update', $report), ['action' => 'hide'])->assertRedirect();

        $this->assertSame(Comment::STATUS_DELETED, $comment->fresh()->status);
        $this->assertNotNull($comment->fresh()->deleted_at);
        $this->assertSame('RESOLVED', $report->fresh()->status);
    }

    public function test_handling_an_already_handled_report_shows_an_error_instead_of_success(): void
    {
        $admin = User::factory()->admin()->create();
        $report = CommentReport::factory()->create(['status' => 'DISMISSED']);

        $this->actingAs($admin)->patch(route('admin.comment-reports.update', $report), ['action' => 'hide'])
            ->assertRedirect()
            ->assertSessionHasErrors('report')
            ->assertSessionMissing('status');
        $this->assertSame(Comment::STATUS_VISIBLE, $report->comment->fresh()->status);
    }

    public function test_moderating_from_the_comments_page_closes_open_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->create();
        $report = CommentReport::factory()->create(['comment_id' => $comment->id]);
        $other = Comment::factory()->create();
        $otherReport = CommentReport::factory()->create(['comment_id' => $other->id]);

        $this->actingAs($admin)->patch(route('admin.comments.update-status', $comment), [
            'status' => Comment::STATUS_HIDDEN,
            'version' => $comment->version,
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.comments.bulk-status'), [
            'status' => Comment::STATUS_DELETED,
            'comment_ids' => [$other->id],
            'comment_versions' => [$other->id => $other->version],
        ])->assertRedirect();

        $this->assertSame('RESOLVED', $report->fresh()->status);
        $this->assertSame('RESOLVED', $otherReport->fresh()->status);
    }

    public function test_cannot_report_comment_on_article_in_inactive_category(): void
    {
        $category = Category::factory()->create(['status' => 'INACTIVE']);
        $article = Post::factory()->published()->create(['category_id' => $category->id]);
        $comment = Comment::factory()->create(['post_id' => $article->id]);

        $this->actingAs(User::factory()->create())->postJson($this->reportUrl($comment), ['reason' => 'SPAM'])->assertNotFound();
        $this->assertDatabaseCount('comment_reports', 0);
    }

    public function test_report_page_keeps_context_for_deleted_article_and_deleted_handler(): void
    {
        $admin = User::factory()->admin()->create();
        $handler = User::factory()->admin()->create(['name' => 'Gone Moderator']);
        $article = Post::factory()->published()->create(['title' => 'Removed article']);
        $comment = Comment::factory()->create(['post_id' => $article->id]);
        CommentReport::factory()->create([
            'comment_id' => $comment->id,
            'status' => 'RESOLVED',
            'handled_by' => $handler->id,
            'handled_at' => now(),
        ]);
        $article->delete();
        $handler->delete();

        $this->actingAs($admin)->get(route('admin.comment-reports.index', ['status' => 'RESOLVED']))
            ->assertOk()
            ->assertSeeText('Removed article')
            ->assertSeeText('Gone Moderator');
    }
}
