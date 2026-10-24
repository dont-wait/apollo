<?php

namespace Tests\Feature;

use App\AiWeekly\ArticleNormalizer;
use App\AiWeekly\ModelClient;
use App\AiWeekly\RunCoordinator;
use App\AiWeekly\SourceFetcher;
use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Jobs\AiWeekly\CollectSource;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
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

    public function test_reusing_an_active_run_dispatches_jobs_that_were_never_queued(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'PENDING', 'payload' => []]);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin/automation-runs/analyze')
            ->assertAccepted()->assertJsonPath('run_id', $run->id)->assertJsonPath('reused', true);

        Queue::assertPushed(AnalyzeArticles::class, 1);
        $this->assertNotNull($run->fresh()->dispatched_at);
    }

    public function test_article_reanalysis_creates_a_new_run_when_another_article_is_active(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $activeArticle = AiArticle::factory()->create();
        $requestedArticle = AiArticle::factory()->create();
        $active = AutomationRun::factory()->create([
            'run_type' => 'ANALYZE', 'status' => 'RUNNING', 'payload' => ['article_id' => $activeArticle->id],
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/admin/automation-runs/analyze', ['article_id' => $requestedArticle->id])
            ->assertAccepted()->assertJsonPath('reused', false)->json('run_id');

        $this->assertNotSame($active->id, $id);
        $this->assertSame($requestedArticle->id, AutomationRun::findOrFail($id)->payload['article_id']);
        Queue::assertPushed(AnalyzeArticles::class, 1);
    }

    public function test_collection_creates_a_new_run_when_a_new_source_is_not_covered_by_the_active_run(): void
    {
        Queue::fake([CollectSource::class]);
        AiSource::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $id = $this->postJson('/admin/automation-runs/collect')->assertAccepted()->json('run_id');

        $newSource = AiSource::factory()->create();
        $newId = $this->postJson('/admin/automation-runs/collect')->assertAccepted()->assertJsonPath('reused', false)->json('run_id');

        $this->assertNotSame($id, $newId);
        $this->assertArrayHasKey($newSource->id, AutomationRun::findOrFail($newId)->payload['sources']);
        Queue::assertPushed(CollectSource::class, 3);
    }

    public function test_collection_reuses_the_active_run_when_all_sources_become_inactive(): void
    {
        Queue::fake([CollectSource::class]);
        $source = AiSource::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $id = $this->postJson('/admin/automation-runs/collect')->assertAccepted()->json('run_id');
        $source->update(['status' => 'INACTIVE']);

        $this->postJson('/admin/automation-runs/collect')
            ->assertAccepted()->assertJsonPath('run_id', $id)->assertJsonPath('reused', true);

        $this->assertDatabaseCount('automation_runs', 1);
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

    public function test_retry_starts_a_failed_review_while_another_week_is_active(): void
    {
        Queue::fake([GenerateWeeklyReview::class]);
        $failed = AutomationRun::factory()->create([
            'run_type' => 'GENERATE_REVIEW', 'status' => 'FAILED', 'attempt' => 1, 'payload' => ['week_start' => '2026-10-12'],
        ]);
        AutomationRun::factory()->create([
            'run_type' => 'GENERATE_REVIEW', 'status' => 'RUNNING', 'payload' => ['week_start' => '2026-10-19'],
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson("/admin/automation-runs/{$failed->id}/retry")->assertAccepted();

        $this->assertSame('PENDING', $failed->fresh()->status);
        $this->assertSame(2, $failed->fresh()->attempt);
        Queue::assertPushed(GenerateWeeklyReview::class, 1);
    }

    public function test_monitor_reports_active_jobs_even_if_the_history_is_filtered(): void
    {
        $run = AutomationRun::factory()->create(['status' => 'RUNNING', 'payload' => ['sources' => [
            1 => ['status' => 'SUCCESS'], 2 => ['status' => 'FAILED'], 3 => ['status' => 'RUNNING', 'name' => 'Research feed'],
        ]]]);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/admin/automation-runs?status=SUCCESS')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonCount(0, 'listing.records')
            ->assertJsonPath('listing.filtered', true)->assertJsonPath('listing.total', 0)
            ->assertJsonPath('current_runs.0.id', $run->id)
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
        $this->assertNull($run->fresh()->progress()['current_item']);
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

    public function test_paginated_records_include_progress_without_hiding_global_active_jobs(): void
    {
        $oldest = AutomationRun::factory()->create(['status' => 'SUCCESS', 'created_at' => now()->subDay(), 'payload' => ['sources' => [1 => ['status' => 'SUCCESS']]]]);
        AutomationRun::factory()->count(30)->create(['status' => 'SUCCESS']);
        $active = AutomationRun::factory()->create(['status' => 'RUNNING']);
        $this->actingAs(User::factory()->admin()->create());

        $this->getJson('/admin/automation-runs?status=SUCCESS&page=2')->assertOk()
            ->assertJsonPath('listing.page', 2)->assertJsonPath('listing.total', 31)
            ->assertJsonCount(1, 'listing.records')->assertJsonPath('listing.records.0.id', $oldest->id)
            ->assertJsonPath('listing.records.0.progress.completed', 1)
            ->assertJsonPath('listing.records.0.progress.percent', 100)
            ->assertJsonPath('current_runs.0.id', $active->id);
    }

    public function test_polling_projects_large_analysis_payloads_and_preserves_the_regular_json_contract(): void
    {
        $results = array_fill(1, 2000, ['status' => 'SUCCESS']);
        for ($id = 1; $id <= 3; $id++) {
            $results[$id] = ['status' => 'FAILED', 'error' => str_repeat('error ', 100)];
        }
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'status' => 'PARTIAL',
            'payload' => ['article_results' => $results, 'failed' => 3, 'total' => 2000, 'current_item' => 'Last article']]);
        AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'status' => 'RUNNING',
            'payload' => ['stage' => 'generating', 'snapshot' => ['content' => str_repeat('source content ', 10000)]]]);
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/admin/automation-runs?monitor=1')->assertOk()->assertJsonMissingPath('data');

        $record = collect($response->json('listing.records'))->firstWhere('id', $run->id);
        $this->assertSame($run->progress(), $record['progress']);
        $this->assertLessThan(10000, strlen($response->getContent()));
        $this->get('/admin/automation-runs')->assertOk()->assertViewHas('runs', function ($runs) use ($run): bool {
            $record = $runs->getCollection()->firstWhere('id', $run->id);

            return strlen($record->getRawOriginal('payload')) < 1000 && $record->progress() === $run->progress();
        });
        $this->getJson('/admin/automation-runs?run_type=ANALYZE')->assertOk()->assertJsonCount(2000, 'data.0.payload.article_results');
    }

    public function test_polling_updates_page_count_and_preserves_filters_in_navigation_urls(): void
    {
        AutomationRun::factory()->count(30)->create(['status' => 'SUCCESS']);
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson('/admin/automation-runs?monitor=1&status=SUCCESS')->assertOk()->assertJsonPath('listing.last_page', 1);
        $extra = AutomationRun::factory()->create(['status' => 'SUCCESS']);

        $response = $this->getJson('/admin/automation-runs?monitor=1&status=SUCCESS')->assertOk()
            ->assertJsonPath('listing.last_page', 2)->assertJsonPath('listing.per_page', 30);

        parse_str(parse_url($response->json('listing.page_url'), PHP_URL_QUERY), $query);
        $this->assertSame('SUCCESS', $query['status']);
        $this->assertArrayNotHasKey('monitor', $query);
        $this->getJson('/admin/automation-runs?monitor=1&status=SUCCESS&page=2')->assertOk()
            ->assertJsonPath('listing.page', 2)->assertJsonCount(1, 'listing.records');
        $extra->delete();
        $this->getJson('/admin/automation-runs?monitor=1&status=SUCCESS&page=2')->assertOk()
            ->assertJsonPath('listing.page', 1)->assertJsonPath('listing.last_page', 1)->assertJsonCount(30, 'listing.records');
    }

    public function test_source_editor_preserves_an_adapter_missing_from_configuration(): void
    {
        AiSource::factory()->create(['source_type' => 'WEBSITE', 'adapter_key' => 'retired_adapter']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/ai-sources')->assertOk()->assertSee('<option value="retired_adapter" selected>', false);
    }

    public function test_progress_endpoints_require_an_admin(): void
    {
        $run = AutomationRun::factory()->create();
        $this->get('/admin/automation-runs')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'USER']))->getJson("/admin/automation-runs/{$run->id}")->assertForbidden();
    }
}
