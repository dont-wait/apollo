<?php

namespace Tests\Feature;

use App\AiWeekly\DraftPostCreator;
use App\AiWeekly\ModelClient;
use App\AiWeekly\SectionEditor;
use App\AiWeekly\WeeklyDraftBuilder;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
use App\Models\AiArticle;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Models\User;
use App\Models\WeeklyReview;
use App\Notifications\WeeklyReviewReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_notifies_active_admins_once_after_review_is_ready(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $inactive = User::factory()->create(['role' => 'ADMIN', 'status' => 'INACTIVE']);
        $reader = User::factory()->create(['role' => 'USER']);
        $article = AiArticle::factory()->create(['published_at' => '2026-10-13 00:00:00']);
        $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'Summary']);
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'attempt' => 1, 'payload' => ['week_start' => '2026-10-12']]);
        $markdown = implode("\n\n", array_map(fn (string $heading): string => $heading."\nSummary [[{$article->id}]].", SectionEditor::HEADINGS));
        $this->mock(ModelClient::class)->shouldReceive('complete')->once()->andReturn([
            'title' => 'Local weekly review', 'excerpt' => 'Summary', 'markdown_content' => $markdown, 'citation_ids' => [$article->id],
        ]);

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));
        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));

        $review = WeeklyReview::sole();
        $post = Post::sole();
        $notification = $admin->unreadNotifications()->sole();
        $this->assertSame($review->id, $notification->data['review_id']);
        $this->assertSame($post->id, $notification->data['post_id']);
        $this->assertSame('DRAFT', $post->status);
        $this->assertSame('AI_WEEKLY', $post->source_type);
        $this->assertNull($post->published_at);
        $this->assertNull($review->reviewed_by);
        $this->assertNull($review->reviewed_at);
        $this->assertDatabaseCount('posts', 1);
        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $reader->notifications()->count());
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($admin)->post('/admin/notifications/'.$notification->id.'/open')
            ->assertRedirect(route('admin.articles.edit', $post));
    }

    public function test_admin_can_list_and_open_only_their_own_notifications(): void
    {
        $this->getJson('/admin/notifications')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'USER']))->getJson('/admin/notifications')->assertForbidden();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $other = User::factory()->create(['role' => 'ADMIN']);
        $review = WeeklyReview::factory()->create();
        $run = AutomationRun::factory()->create(['payload' => ['week_start' => '2026-10-12']]);
        $admin->notify(new WeeklyReviewReady($run, $review->id, $review->title));
        $other->notify(new WeeklyReviewReady($run, $review->id, 'Other admin notification'));
        $own = $admin->notifications()->sole();
        $foreign = $other->notifications()->sole();

        $this->actingAs($admin)->getJson('/admin/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->post('/admin/notifications/'.$foreign->id.'/open')->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
        $this->post('/admin/notifications/'.$own->id.'/open')->assertRedirect(route('admin.weekly-reviews.show', $review));
        $this->assertNotNull($own->fresh()->read_at);
        $this->getJson('/admin/notifications')->assertOk()->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
    }
}
