<?php

namespace Tests\Feature;

use App\AiWeekly\ModelClient;
use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Models\AiArticle;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiAnalysisSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_batch_size_is_five(): void
    {
        $this->assertSame(5, config('ai_weekly.analyze_batch_size'));
    }

    public function test_admin_can_analyze_only_the_selected_articles(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $articles = AiArticle::factory()->count(4)->create();
        $this->actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [$articles[2]->id, $articles[0]->id]])
            ->assertAccepted()
            ->assertJsonPath('reused', false)
            ->json('run_id');

        $this->assertSame([$articles[0]->id, $articles[2]->id], AutomationRun::findOrFail($id)->payload['article_ids']);
        Queue::assertPushed(AnalyzeArticles::class, 1);
    }

    public function test_selection_is_limited_to_the_batch_size(): void
    {
        $articles = AiArticle::factory()->count(6)->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin/automation-runs/analyze', ['article_ids' => $articles->pluck('id')->all()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('article_ids');

        $this->assertDatabaseCount('automation_runs', 0);
    }

    public function test_selection_rejects_duplicates_articles_without_content_and_mixed_modes(): void
    {
        $good = AiArticle::factory()->create();
        $original = AiArticle::factory()->create();
        $duplicate = AiArticle::factory()->create(['duplicate_of' => $original->id]);
        $empty = AiArticle::factory()->create(['raw_summary' => null, 'raw_content' => '']);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [$good->id, $duplicate->id]])->assertUnprocessable();
        $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [$good->id, $empty->id]])->assertUnprocessable();
        $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [$good->id], 'article_id' => $good->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('article_id');
        $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [999999]])->assertUnprocessable();

        $this->assertDatabaseCount('automation_runs', 0);
    }

    public function test_an_active_automatic_run_does_not_swallow_a_hand_picked_selection(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $auto = $this->postJson('/admin/automation-runs/analyze')->assertAccepted()->json('run_id');

        $selected = $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [$article->id]])
            ->assertAccepted()
            ->assertJsonPath('reused', false)
            ->json('run_id');
        $again = $this->postJson('/admin/automation-runs/analyze', ['article_ids' => [$article->id]])
            ->assertAccepted()
            ->assertJsonPath('reused', true)
            ->json('run_id');

        $this->assertNotSame($auto, $selected);
        $this->assertSame($selected, $again);
    }

    public function test_job_analyzes_only_the_selected_articles_even_if_already_analyzed(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $picked = AiArticle::factory()->create(['status' => 'ANALYZED']);
        $other = AiArticle::factory()->create();
        $alsoPicked = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1, 'payload' => ['article_ids' => [$picked->id, $alsoPicked->id]]]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->twice()->andReturn(['summary' => 'S', 'key_points' => [], 'technologies' => [], 'companies' => [], 'opportunities' => [], 'risks' => [], 'topics' => []]);

        foreach (range(1, 3) as $step) {
            (new AnalyzeArticles($run->id))->handle($model);
        }

        $run->refresh();
        $this->assertSame('SUCCESS', $run->status);
        $this->assertSame(2, $run->payload['total']);
        $this->assertSame(2, $run->payload['success']);
        $this->assertSame(1, $picked->analyses()->count());
        $this->assertSame(1, $alsoPicked->analyses()->count());
        $this->assertSame(0, $other->analyses()->count());
        $this->assertSame('COLLECTED', $other->fresh()->status);
    }

    public function test_article_list_lets_admins_pick_analyzable_articles_only(): void
    {
        $usable = AiArticle::factory()->create(['title' => 'Usable article']);
        $original = AiArticle::factory()->create();
        AiArticle::factory()->create(['title' => 'Copy article', 'duplicate_of' => $original->id]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/ai-articles')
            ->assertOk()
            ->assertSee('data-ai-select-form', false)
            ->assertSee('data-max="5"', false)
            ->assertSee('name="article_ids[]" value="'.$usable->id.'"', false)
            ->assertSeeText('0 / 5 selected')
            ->assertSee('title="Duplicate article"', false);
    }
}
