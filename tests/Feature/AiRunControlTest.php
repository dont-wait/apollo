<?php

namespace Tests\Feature;

use App\AiWeekly\ModelClient;
use App\AiWeekly\RunCoordinator;
use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Models\AiArticle;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiRunControlTest extends TestCase
{
    use RefreshDatabase;

    private function analysis(): array
    {
        return ['summary' => 'S', 'key_points' => [], 'technologies' => [], 'companies' => [], 'opportunities' => [], 'risks' => [], 'topics' => []];
    }

    public function test_admin_can_cancel_an_active_run_and_it_is_never_retried(): void
    {
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'RUNNING', 'attempt' => 1, 'finished_at' => null]);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$run->id}/cancel")
            ->assertOk()
            ->assertJsonPath('state.status', 'CANCELLED');

        $run->refresh();
        $this->assertSame('CANCELLED', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('Đã hủy bởi quản trị viên.', $run->error_message);
        $this->postJson("/admin/automation-runs/{$run->id}/retry")->assertStatus(409);
        $this->postJson("/admin/automation-runs/{$run->id}/cancel")->assertStatus(409);
    }

    public function test_cancelling_a_collect_run_closes_its_pending_sources(): void
    {
        $run = AutomationRun::factory()->create(['run_type' => 'COLLECT', 'status' => 'RUNNING', 'payload' => ['sources' => [
            '1' => ['status' => 'SUCCESS'], '2' => ['status' => 'RUNNING'], '3' => ['status' => 'PENDING'],
        ]]]);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$run->id}/cancel")->assertOk();

        $sources = $run->fresh()->payload['sources'];
        $this->assertSame('SUCCESS', $sources['1']['status']);
        $this->assertSame('FAILED', $sources['2']['status']);
        $this->assertFalse($sources['3']['retryable']);
    }

    public function test_pause_and_resume_an_analysis_run(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'RUNNING', 'attempt' => 1, 'started_at' => now()]);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$run->id}/pause")->assertOk()->assertJsonPath('state.status', 'PAUSED');
        $this->assertSame('PAUSED', $run->fresh()->status);
        $this->postJson("/admin/automation-runs/{$run->id}/pause")->assertStatus(409);
        Queue::assertNothingPushed();

        $this->postJson("/admin/automation-runs/{$run->id}/resume")->assertOk()->assertJsonPath('state.status', 'RUNNING');
        $this->assertSame('RUNNING', $run->fresh()->status);
        Queue::assertPushed(AnalyzeArticles::class, fn (AnalyzeArticles $job): bool => $job->runId === $run->id && $job->attempt === 1);
        $this->postJson("/admin/automation-runs/{$run->id}/resume")->assertStatus(409);
    }

    public function test_only_analysis_runs_can_be_paused(): void
    {
        $run = AutomationRun::factory()->create(['run_type' => 'COLLECT', 'status' => 'RUNNING']);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$run->id}/pause")->assertStatus(409);
        $this->assertSame('RUNNING', $run->fresh()->status);
    }

    public function test_a_paused_run_can_be_cancelled(): void
    {
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'PAUSED']);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$run->id}/cancel")->assertOk();

        $this->assertSame('CANCELLED', $run->fresh()->status);
    }

    public function test_non_admins_cannot_control_runs(): void
    {
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'RUNNING']);
        $this->actingAs(User::factory()->create());

        foreach (['cancel', 'pause', 'resume'] as $action) {
            $this->postJson("/admin/automation-runs/{$run->id}/{$action}")->assertForbidden();
        }
        $this->assertSame('RUNNING', $run->fresh()->status);
    }

    public function test_job_does_nothing_for_paused_or_cancelled_runs(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        AiArticle::factory()->create();
        $model = $this->mock(ModelClient::class);
        $model->shouldNotReceive('complete');

        foreach (['PAUSED', 'CANCELLED'] as $status) {
            $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => $status, 'attempt' => 1]);
            (new AnalyzeArticles($run->id))->handle($model);
            $this->assertSame($status, $run->fresh()->status);
        }

        Queue::assertNothingPushed();
    }

    public function test_pausing_mid_article_still_saves_it_but_does_not_queue_the_next_one(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $first = AiArticle::factory()->create();
        $second = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'PENDING', 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturnUsing(function () use ($run): array {
            // The admin hits "Hoãn" while the model is still working on this article.
            app(RunCoordinator::class)->pause($run);

            return $this->analysis();
        });

        (new AnalyzeArticles($run->id))->handle($model);

        $run->refresh();
        $this->assertSame('PAUSED', $run->status);
        $this->assertSame('ANALYZED', $first->fresh()->status);
        $this->assertSame('COLLECTED', $second->fresh()->status);
        $this->assertSame($first->id, $run->payload['cursor']);
        Queue::assertNotPushed(AnalyzeArticles::class);
    }

    public function test_cancelling_mid_article_discards_the_result(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'PENDING', 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturnUsing(function () use ($run): array {
            app(RunCoordinator::class)->cancel($run);

            return $this->analysis();
        });

        (new AnalyzeArticles($run->id))->handle($model);

        $this->assertSame('CANCELLED', $run->fresh()->status);
        $this->assertSame(0, $article->analyses()->count());
        Queue::assertNotPushed(AnalyzeArticles::class);
    }

    public function test_pipeline_page_shows_run_controls_and_lists_paused_runs_first(): void
    {
        AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'PAUSED']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/automation-runs')
            ->assertOk()
            ->assertSee("act(run, 'pause')", false)
            ->assertSee("act(run, 'cancel')", false)
            ->assertSee('data-csrf', false)
            // Alpine disables a button whose dotted :disabled expression is undefined, so it must be a real boolean.
            ->assertSee(':disabled="isActing(run)"', false)
            ->assertDontSee(':disabled="acting[', false);
        $this->getJson('/admin/automation-runs?monitor=1')
            ->assertOk()
            ->assertJsonPath('current_runs.0.status', 'PAUSED');
    }
}
