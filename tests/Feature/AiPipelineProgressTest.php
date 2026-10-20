<?php

namespace Tests\Feature;

use App\AiWeekly\ArticleNormalizer;
use App\AiWeekly\ModelClient;
use App\AiWeekly\RunCoordinator;
use App\AiWeekly\SourceFetcher;
use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Jobs\AiWeekly\CollectSource;
use App\Models\AiArticle;
use App\Models\AiSource;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiPipelineProgressTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_collection_clicks_reuse_pending_and_running_jobs_across_admins(): void
    {
        Queue::fake([CollectSource::class]);
        AiSource::factory()->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());
        $id = $this->postJson('/admin/automation-runs/collect')->assertAccepted()->assertJsonPath('reused', false)->json('run_id');
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin/automation-runs/collect')->assertAccepted()->assertJsonPath('run_id', $id)->assertJsonPath('reused', true);
        AutomationRun::findOrFail($id)->update(['status' => 'RUNNING']);
        $this->postJson('/admin/automation-runs/collect')->assertAccepted()->assertJsonPath('run_id', $id)->assertJsonPath('reused', true);

        $this->assertDatabaseCount('automation_runs', 1);
        Queue::assertPushed(CollectSource::class, 2);
    }

    public function test_analysis_clicks_and_article_reanalysis_reuse_an_active_job(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $id = $this->postJson('/admin/automation-runs/analyze')->assertAccepted()->json('run_id');

        $this->postJson('/admin/automation-runs/analyze', ['article_id' => $article->id])->assertAccepted()->assertJsonPath('run_id', $id)->assertJsonPath('reused', true);
        AutomationRun::findOrFail($id)->update(['status' => 'RUNNING']);
        $this->postJson('/admin/automation-runs/analyze')->assertAccepted()->assertJsonPath('run_id', $id);

        $this->assertDatabaseCount('automation_runs', 1);
        Queue::assertPushed(AnalyzeArticles::class, 1);
    }

    public function test_analysis_can_start_again_after_a_terminal_run(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $old = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'SUCCESS']);
        $this->actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/admin/automation-runs/analyze')->assertAccepted()->assertJsonPath('reused', false)->json('run_id');

        $this->assertNotSame($old->id, $id);
        $this->assertDatabaseHas('automation_runs', ['id' => $id, 'status' => 'PENDING']);
        Queue::assertPushed(AnalyzeArticles::class, 1);
    }

    public function test_retry_returns_409_if_another_run_of_the_same_type_is_active(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $failed = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'FAILED', 'attempt' => 1]);
        AutomationRun::factory()->create(['run_type' => 'ANALYZE']);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$failed->id}/retry")->assertConflict();

        $this->assertSame('FAILED', $failed->fresh()->status);
        $this->assertSame(1, $failed->fresh()->attempt);
        Queue::assertNothingPushed();
    }

    public function test_monitor_reports_active_jobs_even_if_the_history_is_filtered(): void
    {
        $run = AutomationRun::factory()->create(['status' => 'RUNNING', 'payload' => ['sources' => [
            1 => ['status' => 'SUCCESS'], 2 => ['status' => 'FAILED'], 3 => ['status' => 'RUNNING', 'name' => 'Research feed'],
        ]]]);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/admin/automation-runs?status=SUCCESS')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('current_runs.0.id', $run->id)
            ->assertJsonPath('current_runs.0.progress.completed', 2)
            ->assertJsonPath('current_runs.0.progress.total', 3)
            ->assertJsonPath('current_runs.0.progress.failed', 1)
            ->assertJsonPath('current_runs.0.progress.percent', 66)
            ->assertJsonPath('current_runs.0.progress.current_item', 'Research feed');
    }

    public function test_analysis_records_the_current_article_before_calling_the_model_and_finishes_progress(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $client = $this->mock(ModelClient::class);
        $client->shouldReceive('complete')->once()->andReturnUsing(function () use ($run, $article): array {
            $progress = $run->fresh()->progress();
            $this->assertSame($article->title, $progress['current_item']);
            $this->assertSame(1, $progress['total']);
            $this->assertSame(0, $progress['completed']);

            return ['summary' => 'Summary', 'key_points' => [], 'technologies' => [], 'companies' => [], 'opportunities' => [], 'risks' => [], 'topics' => []];
        });

        (new AnalyzeArticles($run->id))->handle($client);
        $this->assertSame(1, $run->fresh()->progress()['completed']);
        $this->assertSame(99, $run->fresh()->progress()['percent']);
        (new AnalyzeArticles($run->id))->handle($client);

        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->progress()['percent']);
        $this->assertNull($run->fresh()->progress()['current_item']);
    }

    public function test_collection_keeps_running_until_parallel_sources_finish(): void
    {
        $first = AiSource::factory()->create();
        $second = AiSource::factory()->create();
        $run = AutomationRun::factory()->create(['attempt' => 1, 'payload' => ['sources' => [
            $first->id => ['status' => 'PENDING'], $second->id => ['status' => 'RUNNING', 'name' => $second->name],
        ]]]);
        $fetcher = $this->mock(SourceFetcher::class);
        $fetcher->shouldReceive('fetch')->once()->andReturnUsing(function () use ($run, $first): array {
            $this->assertSame('RUNNING', $run->fresh()->payload['sources'][$first->id]['status']);
            $this->assertSame($first->name, $run->fresh()->payload['sources'][$first->id]['name']);

            return [];
        });

        (new CollectSource($run->id, $first->id))->handle($fetcher, app(ArticleNormalizer::class));

        $this->assertSame('RUNNING', $run->fresh()->status);
        $this->assertNull($run->fresh()->finished_at);
        $this->assertSame(50, $run->fresh()->progress()['percent']);
    }

    public function test_retry_clears_progress_from_the_previous_analysis_attempt(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'FAILED', 'attempt' => 1,
            'payload' => ['total' => 9, 'current_item' => 'Old article', 'article_results' => [1 => ['status' => 'FAILED']], 'failed' => 1]]);

        $this->assertTrue(app(RunCoordinator::class)->retry($run));

        $progress = $run->fresh()->progress();
        $this->assertSame(0, $progress['completed']);
        $this->assertNull($progress['total']);
        $this->assertNull($progress['current_item']);
        Queue::assertPushed(AnalyzeArticles::class, fn (AnalyzeArticles $job): bool => $job->attempt === 2);
    }

    public function test_reconciliation_marks_an_abandoned_running_source_failed(): void
    {
        $run = AutomationRun::factory()->create(['status' => 'RUNNING', 'attempt' => 3,
            'locked_at' => now()->subHour(), 'payload' => ['sources' => [1 => ['status' => 'RUNNING']]]]);

        app(RunCoordinator::class)->reconcile();

        $this->assertSame('FAILED', $run->fresh()->status);
        $this->assertSame('FAILED', $run->fresh()->payload['sources'][1]['status']);
    }

    public function test_progress_endpoints_require_an_admin(): void
    {
        $run = AutomationRun::factory()->create();
        $this->get('/admin/automation-runs')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'USER']))->getJson("/admin/automation-runs/{$run->id}")->assertForbidden();
    }
}
