<?php

namespace Tests\Feature;

use App\AiWeekly\ArticleNormalizer;
use App\AiWeekly\ModelClient;
use App\AiWeekly\RunCoordinator;
use App\AiWeekly\SourceFetcher;
use App\AiWeekly\WeeklyDraftBuilder;
use App\AiWeekly\WeeklyWindow;
use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Jobs\AiWeekly\CollectSource;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
use App\Models\AiArticle;
use App\Models\AiSource;
use App\Models\AiTopic;
use App\Models\AutomationRun;
use App\Models\Post;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiWeeklyTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_management_requires_admin_and_rejects_private_urls(): void
    {
        $this->get('/admin/ai-sources')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'USER']))->postJson('/admin/ai-sources', [])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))->postJson('/admin/ai-sources', [
            'name' => 'Internal', 'source_type' => 'RSS', 'site_url' => 'http://127.0.0.1',
            'feed_url' => 'http://127.0.0.1/rss', 'trust_score' => 5, 'priority' => 5, 'status' => 'ACTIVE',
        ])->assertJsonValidationErrors(['site_url', 'feed_url']);

        $this->postJson('/admin/ai-sources', [
            'name' => 'Public feed', 'source_type' => 'RSS', 'site_url' => 'https://1.1.1.1',
            'feed_url' => 'https://1.1.1.1/rss', 'trust_score' => 8, 'priority' => 9, 'status' => 'ACTIVE',
        ])->assertCreated();
        $this->assertDatabaseHas('ai_sources', ['name' => 'Public feed', 'status' => 'ACTIVE']);
    }

    public function test_collector_keeps_other_sources_when_one_fails_and_deduplicates_tracking_urls(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $good = AiSource::factory()->create(['site_url' => 'https://1.1.1.1', 'feed_url' => 'https://1.1.1.1/rss']);
        $bad = AiSource::factory()->create(['site_url' => 'https://8.8.8.8', 'feed_url' => 'https://8.8.8.8/rss']);
        AiSource::factory()->create(['status' => 'INACTIVE']);
        Queue::fake([CollectSource::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://1.1.1.1/rss' => Http::response('<rss><channel><item><title>Story</title><link>https://example.org/story?id=1&amp;utm_source=a</link><description>Summary</description></item><item><title>Story</title><link>https://example.org/story?utm_source=b&amp;id=1</link><description>Summary</description></item></channel></rss>', 200),
            'https://8.8.8.8/rss' => Http::response('No feed', 404),
        ]);

        $runId = $this->postJson('/admin/automation-runs/collect')->assertAccepted()->json('run_id');
        Queue::assertPushed(CollectSource::class, 2);
        (new CollectSource($runId, $good->id))->handle(app(SourceFetcher::class), app(ArticleNormalizer::class));
        (new CollectSource($runId, $bad->id))->handle(app(SourceFetcher::class), app(ArticleNormalizer::class));

        $this->assertSame(1, AiArticle::count());
        $this->assertSame('PARTIAL', AutomationRun::findOrFail($runId)->status);
        $this->assertSame(1, AutomationRun::findOrFail($runId)->payload['sources'][$good->id]['duplicate']);
        $this->assertNotNull($good->fresh()->last_fetched_at);
        $this->assertNull($bad->fresh()->last_fetched_at);
        $this->assertSame('https://example.org/story?id=1', AiArticle::firstOrFail()->canonical_url);
    }

    public function test_api_adapter_uses_configured_secret_without_exposing_it_on_source(): void
    {
        config()->set('ai_weekly.api_adapters.generic_json.headers', ['Authorization' => 'Bearer private-token']);
        $source = AiSource::factory()->create([
            'source_type' => 'API', 'adapter_key' => 'generic_json', 'feed_url' => 'https://1.1.1.1/articles',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://1.1.1.1/articles' => Http::response(['items' => [['title' => 'Article', 'url' => 'https://example.org/article']]])]);

        $items = app(SourceFetcher::class)->fetch($source);

        $this->assertSame('Article', $items[0]['title']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer private-token'));
        $this->assertStringNotContainsString('private-token', json_encode($source->toArray()));
    }

    public function test_analysis_validates_output_and_keeps_current_success_after_failed_reanalysis(): void
    {
        $source = AiSource::factory()->create();
        $article = AiArticle::factory()->create(['source_id' => $source->id]);
        AiTopic::create(['name' => 'LLM', 'slug' => 'llm', 'status' => 'ACTIVE']);
        Queue::fake([AnalyzeArticles::class]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturn([
            'summary' => 'Bản tóm tắt', 'key_points' => ['Điểm chính'], 'technologies' => ['LLM'],
            'companies' => [], 'opportunities' => [], 'risks' => [], 'topics' => [['slug' => 'llm', 'confidence' => 0.9]],
        ]);
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'payload' => [], 'attempt' => 1]);
        (new AnalyzeArticles($run->id))->handle($model);
        (new AnalyzeArticles($run->id))->handle($model);

        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame(1, $article->analyses()->where('is_current', true)->count());
        $this->assertSame(1, $article->topics()->count());

        $model->shouldReceive('complete')->once()->andReturn(['summary' => 'invalid']);
        $failedRun = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'payload' => ['article_id' => $article->id], 'attempt' => 1]);
        (new AnalyzeArticles($failedRun->id))->handle($model);
        (new AnalyzeArticles($failedRun->id))->handle($model);
        $this->assertSame('FAILED', $failedRun->fresh()->status);
        $this->assertSame(1, $article->analyses()->where('is_current', true)->count());
        $this->assertSame(1, $article->analyses()->where('status', 'FAILED')->count());
        $this->assertSame('ANALYZED', $article->fresh()->status);
    }

    public function test_generation_uses_snapshot_citations_and_never_creates_a_published_post(): void
    {
        $source = AiSource::factory()->create();
        $article = AiArticle::factory()->create(['source_id' => $source->id, 'published_at' => '2026-10-13 00:00:00']);
        $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'Research summary']);
        $topic = AiTopic::create(['name' => 'Research', 'slug' => 'research', 'status' => 'ACTIVE']);
        $article->topics()->attach($topic, ['confidence' => 0.8]);
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'payload' => ['week_start' => '2026-10-12'], 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturn([
            'title' => 'AI Weekly', 'excerpt' => 'Summary', 'markdown_content' => $this->structuredMarkdown($article->id),
            'citation_ids' => [$article->id], 'suggested_tags' => ['Research'],
        ]);
        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class));

        $review = WeeklyReview::firstOrFail();
        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame('REVIEW', $review->status);
        $this->assertSame(1, $review->sourceArticles()->count());
        $this->assertSame(0, Post::count());
        $this->assertNotNull($review->quality_warning);
    }

    public function test_generation_rejects_citation_outside_snapshot(): void
    {
        $article = AiArticle::factory()->create(['published_at' => '2026-10-13 00:00:00']);
        $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'Research summary']);
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'payload' => ['week_start' => '2026-10-12'], 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturn([
            'title' => 'Fabricated', 'excerpt' => 'Fabricated', 'markdown_content' => $this->structuredMarkdown(999999),
            'citation_ids' => [999999], 'suggested_tags' => [],
        ]);

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class));

        $this->assertSame('FAILED', $run->fresh()->status);
        $this->assertSame(0, WeeklyReview::count());
    }

    public function test_exhausted_generation_can_start_new_linked_run(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $old = AutomationRun::factory()->create([
            'run_type' => 'GENERATE_REVIEW', 'status' => 'FAILED', 'attempt' => 3, 'max_attempts' => 3,
            'idempotency_key' => 'weekly:2026-10-12', 'payload' => ['week_start' => '2026-10-12'],
        ]);
        Queue::fake([GenerateWeeklyReview::class]);

        $newId = $this->postJson('/admin/weekly-reviews/generate', ['week_start' => '2026-10-12'])->assertAccepted()->json('run_id');
        $this->assertNotSame($old->id, $newId);
        $this->assertSame($old->id, AutomationRun::findOrFail($newId)->payload['previous_run_id']);
        Queue::assertPushed(GenerateWeeklyReview::class, 1);
    }

    public function test_review_revision_and_conversion_are_idempotent_even_after_post_soft_delete(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin);
        $review = WeeklyReview::factory()->create(['status' => 'REVIEW']);
        $article = AiArticle::factory()->create();
        $review->sourceArticles()->attach($article, ['citation_order' => 1]);

        $this->putJson("/admin/weekly-reviews/{$review->id}", [
            'revision' => 1, 'title' => 'Edited', 'excerpt' => 'Edited excerpt', 'markdown_content' => 'Edited body',
        ])->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson("/admin/weekly-reviews/{$review->id}", [
            'revision' => 1, 'title' => 'Stale', 'markdown_content' => 'Stale body',
        ])->assertStatus(409);

        $postId = $this->postJson("/admin/weekly-reviews/{$review->id}/convert-to-post")->assertCreated()->json('data.id');
        $this->assertDatabaseHas('posts', ['id' => $postId, 'status' => 'DRAFT', 'source_type' => 'AI_WEEKLY', 'author_id' => $admin->id]);
        Post::findOrFail($postId)->delete();
        $this->postJson("/admin/weekly-reviews/{$review->id}/convert-to-post")->assertOk()->assertJsonPath('data.id', $postId);
        $this->assertSame(1, Post::withTrashed()->count());
    }

    public function test_regenerate_keeps_review_unchanged_until_matching_revision_applies_candidate(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $review = WeeklyReview::factory()->create(['status' => 'REVIEW', 'title' => 'Original']);
        $article = AiArticle::factory()->create();
        $review->sourceArticles()->attach($article, ['citation_order' => 1]);
        Queue::fake([GenerateWeeklyReview::class]);

        $runId = $this->postJson("/admin/weekly-reviews/{$review->id}/regenerate", ['revision' => 1])->assertAccepted()->json('run_id');
        Queue::assertPushed(GenerateWeeklyReview::class);
        $this->assertSame('Original', $review->fresh()->title);
        $run = AutomationRun::findOrFail($runId);
        $payload = $run->payload;
        $payload['candidate'] = ['revision' => 1, 'draft' => [
            'title' => 'Candidate', 'excerpt' => 'New', 'markdown_content' => 'New body',
            'quality_warning' => null, 'suggested_tags' => [],
        ], 'citations' => [['article_id' => $article->id, 'citation_order' => 1]]];
        $run->update(['status' => 'SUCCESS', 'payload' => $payload]);
        $this->postJson("/admin/weekly-reviews/{$review->id}/apply-candidate", ['run_id' => $runId, 'revision' => 1])->assertOk();
        $this->assertSame('Candidate', $review->fresh()->title);
        $this->assertSame(2, $review->fresh()->revision);
        $this->postJson("/admin/weekly-reviews/{$review->id}/apply-candidate", ['run_id' => $runId, 'revision' => 1])->assertStatus(409);
    }

    public function test_browser_revision_strings_can_save_regenerate_and_apply_without_accepting_stale_revisions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $review = WeeklyReview::factory()->create(['status' => 'REVIEW']);
        $article = AiArticle::factory()->create();
        Queue::fake([GenerateWeeklyReview::class]);

        $this->putJson("/admin/weekly-reviews/{$review->id}", [
            'revision' => '1', 'title' => 'Browser edit', 'markdown_content' => 'Browser body',
        ])->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertDatabaseHas('weekly_reviews', ['id' => $review->id, 'title' => 'Browser edit', 'revision' => 2]);
        $this->putJson("/admin/weekly-reviews/{$review->id}", [
            'revision' => '1', 'title' => 'Stale edit', 'markdown_content' => 'Stale body',
        ])->assertStatus(409);
        $this->postJson("/admin/weekly-reviews/{$review->id}/regenerate", ['revision' => '1'])->assertStatus(409);
        $runId = $this->postJson("/admin/weekly-reviews/{$review->id}/regenerate", ['revision' => '2'])->assertAccepted()->json('run_id');
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job): bool => $job->runId === $runId);

        $run = AutomationRun::findOrFail($runId);
        $payload = $run->payload;
        $payload['candidate'] = [
            'revision' => 2,
            'draft' => ['title' => 'Browser candidate', 'excerpt' => 'Candidate excerpt', 'markdown_content' => 'Candidate body', 'quality_warning' => null, 'suggested_tags' => []],
            'citations' => [['article_id' => $article->id, 'citation_order' => 1]],
        ];
        $run->update(['status' => 'SUCCESS', 'payload' => $payload]);

        $this->postJson("/admin/weekly-reviews/{$review->id}/apply-candidate", [
            'run_id' => (string) $runId, 'revision' => '2',
        ])->assertOk()->assertJsonPath('data.revision', 3);
        $this->assertDatabaseHas('weekly_reviews', ['id' => $review->id, 'title' => 'Browser candidate', 'revision' => 3]);
        $this->assertSame($article->id, $review->sourceArticles()->sole()->id);
        $this->postJson("/admin/weekly-reviews/{$review->id}/apply-candidate", [
            'run_id' => (string) $runId, 'revision' => '2',
        ])->assertStatus(409);
    }

    public function test_numeric_string_citation_ids_keep_their_reference_numbers(): void
    {
        $first = AiArticle::factory()->create();
        $second = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'payload' => [
            'week_start' => '2026-10-12',
            'snapshot' => [
                'week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'model' => 'frozen-model',
                'articles' => [
                    ['id' => $first->id, 'title' => $first->title, 'url' => $first->url, 'source_name' => 'First source'],
                    ['id' => $second->id, 'title' => $second->title, 'url' => $second->url, 'source_name' => 'Second source'],
                ],
            ],
        ]]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturn([
            'title' => 'Numbered citations', 'excerpt' => 'Two sources',
            'markdown_content' => $this->structuredMarkdown($first->id)."\nSecond claim [[{$second->id}]].",
            'citation_ids' => [(string) $second->id, (string) $first->id],
        ]);

        app(WeeklyDraftBuilder::class)->build($run);

        $review = WeeklyReview::sole();
        $this->assertStringContainsString('Overview [2].', $review->markdown_content);
        $this->assertStringContainsString('Second claim [1].', $review->markdown_content);
        $this->assertStringContainsString('1. ['.$second->title.']('.$second->url.')', $review->markdown_content);
        $this->assertStringContainsString('2. ['.$first->title.']('.$first->url.')', $review->markdown_content);
        $this->assertSame([$second->id, $first->id], $review->sourceArticles()->orderByPivot('citation_order')->pluck('ai_articles.id')->all());
    }

    public function test_generation_retry_uses_the_model_frozen_before_configuration_changes(): void
    {
        config()->set([
            'ai_weekly.model' => 'original-model',
            'ai_weekly.model_url' => 'https://model.example.test/chat',
            'ai_weekly.model_key' => 'test-key',
        ]);
        $article = AiArticle::factory()->create(['published_at' => '2026-10-13 00:00:00']);
        $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'Research summary']);
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'payload' => ['week_start' => '2026-10-12'], 'attempt' => 1]);
        Http::preventStrayRequests();
        Http::fake(['https://model.example.test/chat' => Http::sequence()
            ->push([], 503)
            ->push(['choices' => [['message' => ['content' => json_encode([
                'title' => 'Retried review', 'excerpt' => 'Retry summary',
                'markdown_content' => $this->structuredMarkdown($article->id), 'citation_ids' => [$article->id],
            ])]]]])]);
        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class));
        $this->assertSame('FAILED', $run->fresh()->status);
        $this->assertSame('original-model', $run->fresh()->payload['snapshot']['model']);
        config()->set('ai_weekly.model', 'replacement-model');
        Queue::fake([GenerateWeeklyReview::class]);

        $this->assertTrue(app(RunCoordinator::class)->retry($run->fresh()));
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job): bool => $job->runId === $run->id && $job->attempt === 2);
        (new GenerateWeeklyReview($run->id, 2))->handle(app(WeeklyDraftBuilder::class));

        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertDatabaseHas('weekly_reviews', ['generation_run_id' => $run->id, 'model_name' => 'original-model']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request): bool => $request['model'] !== 'original-model');
    }

    public function test_section_candidate_preserves_other_sections_and_rebuilds_references(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $article = AiArticle::factory()->create();
        $review = WeeklyReview::factory()->create([
            'status' => 'REVIEW',
            'markdown_content' => str_replace('[['.$article->id.']]', '[1]', $this->structuredMarkdown($article->id))."\n\n## Nguồn tham khảo\n1. [Source](".$article->url.')',
        ]);
        $review->sourceArticles()->attach($article, ['citation_order' => 1]);
        Queue::fake([GenerateWeeklyReview::class]);

        $runId = $this->postJson("/admin/weekly-reviews/{$review->id}/regenerate", ['revision' => 1, 'section' => 'research'])->assertAccepted()->json('run_id');
        $run = AutomationRun::findOrFail($runId);
        $payload = $run->payload;
        $payload['candidate'] = ['revision' => 1, 'section' => 'research', 'draft' => [
            'title' => 'Ignore this title', 'excerpt' => 'Ignore this excerpt', 'markdown_content' => 'New research [['.$article->id.']].',
            'quality_warning' => null, 'suggested_tags' => [],
        ], 'citations' => [['article_id' => $article->id, 'citation_order' => 1]]];
        $run->update(['status' => 'SUCCESS', 'payload' => $payload]);

        $this->postJson("/admin/weekly-reviews/{$review->id}/apply-candidate", ['run_id' => $runId, 'revision' => 1])->assertOk();
        $this->assertSame($review->title, $review->fresh()->title);
        $this->assertStringContainsString('Overview [1].', $review->fresh()->markdown_content);
        $this->assertStringContainsString('New research [1].', $review->fresh()->markdown_content);
        $this->assertSame(1, $review->sourceArticles()->count());
    }

    public function test_retry_requeues_only_failed_source_and_respects_retry_after(): void
    {
        $source1 = AiSource::factory()->create();
        $source2 = AiSource::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'COLLECT', 'status' => 'PARTIAL', 'attempt' => 1, 'payload' => ['sources' => [
            $source1->id => ['status' => 'SUCCESS'],
            $source2->id => ['status' => 'FAILED', 'retryable' => true, 'retry_after_at' => now()->addHour()->toIso8601String()],
        ]]]);
        Queue::fake([CollectSource::class]);
        $this->assertFalse(app(RunCoordinator::class)->retry($run));
        Queue::assertNotPushed(CollectSource::class);

        $payload = $run->payload;
        unset($payload['sources'][$source2->id]['retry_after_at']);
        $run->update(['payload' => $payload]);
        $this->assertTrue(app(RunCoordinator::class)->retry($run->fresh()));
        Queue::assertPushed(CollectSource::class, 1);
        $this->assertSame(2, $run->fresh()->attempt);
        $this->assertSame('SUCCESS', $run->fresh()->payload['sources'][$source1->id]['status']);
    }

    public function test_week_window_uses_local_monday_boundary_and_collected_date_fallback(): void
    {
        $window = app(WeeklyWindow::class);
        $before = AiArticle::factory()->create(['published_at' => '2026-10-11 16:59:59']);
        $after = AiArticle::factory()->create(['published_at' => '2026-10-11 17:00:00']);
        $fallback = AiArticle::factory()->create(['published_at' => null, 'collected_at' => '2026-10-11 17:00:00']);
        foreach ([$before, $after, $fallback] as $article) {
            $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'Summary']);
        }

        $ids = $window->articles($window->start('2026-10-12'))->pluck('id')->all();
        $this->assertNotContains($before->id, $ids);
        $this->assertContains($after->id, $ids);
        $this->assertContains($fallback->id, $ids);
    }

    public function test_admin_can_open_pipeline_and_review_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $article = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW']);
        $review = WeeklyReview::factory()->create(['generation_run_id' => $run->id]);
        $review->sourceArticles()->attach($article, ['citation_order' => 1]);

        $this->get('/admin/ai-sources')->assertOk()->assertSee('Nguồn AI');
        $this->get('/admin/ai-articles')->assertOk()->assertSee($article->title);
        $this->get("/admin/ai-articles/{$article->id}")->assertOk()->assertSee($article->title);
        $this->get('/admin/automation-runs')->assertOk()->assertSee('Pipeline runs');
        $this->get("/admin/automation-runs/{$run->id}")->assertOk()->assertSee('Run #'.$run->id);
        $this->get('/admin/weekly-reviews')->assertOk()->assertSee($review->title);
        $this->get("/admin/weekly-reviews/{$review->id}")->assertOk()->assertSee($review->title);
    }

    private function structuredMarkdown(int $articleId): string
    {
        return "## Tổng quan tuần\nOverview [[{$articleId}]].\n\n## Tin nổi bật\nTop stories.\n\n## Nghiên cứu đáng chú ý\nResearch.\n\n## Chủ đề và xu hướng\nTrends.\n\n## Cơ hội\nOpportunities.\n\n## Rủi ro và giới hạn\nRisks.";
    }

    public function test_url_normalizer_preserves_meaningful_repeated_query_parameters(): void
    {
        $normalizer = new ArticleNormalizer;
        $this->assertSame('https://example.org/Story?a=1&a=2&b=3', $normalizer->canonicalUrl('HTTPS://EXAMPLE.ORG/Story?utm_source=x&b=3&a=2&a=1#top'));
        $this->assertSame($normalizer->titleFingerprint(' AI &amp; News '), $normalizer->titleFingerprint('ai & news'));
    }
}
