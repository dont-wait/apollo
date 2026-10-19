<?php

namespace Tests\Feature;

use App\AiWeekly\ArticleNormalizer;
use App\AiWeekly\DraftPostCreator;
use App\AiWeekly\ModelClient;
use App\AiWeekly\SourceFetcher;
use App\AiWeekly\WeeklyDraftBuilder;
use App\Jobs\AiWeekly\AnalyzeArticles;
use App\Jobs\AiWeekly\CollectSource;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
use App\Models\AiArticle;
use App\Models\AiSource;
use App\Models\AiTopic;
use App\Models\AutomationRun;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AiWeeklyReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_sends_topic_schema_and_bounded_input_to_the_real_client(): void
    {
        config(['ai_weekly.model' => 'qwen2.5:7b', 'ai_weekly.model_url' => 'http://127.0.0.1:11435/v1/chat/completions']);
        $article = AiArticle::factory()->create(['raw_content' => str_repeat('a', 7000)]);
        AiTopic::factory()->create(['slug' => 'research', 'status' => 'ACTIVE']);
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $output = $this->analysis();
        $output['topics'] = [['slug' => 'research', 'confidence' => 0.9]];
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode($output)]]]])]);
        Queue::fake([AnalyzeArticles::class]);

        (new AnalyzeArticles($run->id))->handle(app(ModelClient::class));

        $this->assertSame('ANALYZED', $article->fresh()->status);
        $this->assertSame('research', $article->topics()->sole()->slug);
        Http::assertSent(function ($request): bool {
            $schema = $request['response_format']['json_schema']['schema'];
            $input = json_decode($request['messages'][1]['content'], true);

            return $request['response_format']['type'] === 'json_schema'
                && $schema['properties']['topics']['items']['properties']['slug']['enum'] === ['research']
                && $schema['properties']['topics']['items']['properties']['confidence']['type'] === 'number'
                && (float) $request['temperature'] === 0.0 && $request['max_tokens'] === 1000
                && mb_strlen($input['content']) === 6000 && $input['input_truncated'] === true;
        });
        Queue::assertPushed(AnalyzeArticles::class, 1);
    }

    public function test_generation_sends_snapshot_citation_schema_to_the_real_client(): void
    {
        config(['ai_weekly.model' => 'qwen2.5:7b', 'ai_weekly.model_url' => 'http://127.0.0.1:11435/v1/chat/completions']);
        $article = AiArticle::factory()->create();
        $run = $this->generationRun($article);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->draft($article->id))]]]])]);

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));

        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame($article->id, WeeklyReview::sole()->sourceArticles()->sole()->id);
        Http::assertSent(fn ($request): bool => $request['response_format']['json_schema']['schema']['properties']['citation_ids']['items'] === ['type' => 'integer', 'enum' => [$article->id]]
            && $request['max_tokens'] === 1800);
    }

    public function test_uppercase_https_pins_the_actual_connection_port(): void
    {
        $source = AiSource::factory()->make(['feed_url' => 'HTTPS://1.1.1.1/feed']);
        Http::fake(function ($request, array $options) {
            $this->assertSame(['1.1.1.1:443:1.1.1.1'], $options['curl'][CURLOPT_RESOLVE]);

            return Http::response('<rss><channel/></rss>');
        });

        $this->assertSame([], app(SourceFetcher::class)->fetch($source));
        Http::assertSentCount(1);
    }

    public function test_feed_limit_aborts_writing_before_the_whole_response_is_buffered(): void
    {
        config(['ai_weekly.max_feed_bytes' => 8]);
        $source = AiSource::factory()->make(['feed_url' => 'https://1.1.1.1/feed']);
        $received = null;
        Http::fake(function ($request, array $options) use (&$received) {
            $received = $options['sink'];
            $received->write('12345678');
            $received->write('9');

            return Http::response('unreachable');
        });

        try {
            app(SourceFetcher::class)->fetch($source);
            $this->fail('An oversized stream must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Source response is too large.', $exception->getMessage());
            $this->assertSame(8, $received->getSize());
        }
    }

    public function test_generated_references_escape_feed_markdown_and_url_delimiters(): void
    {
        $article = AiArticle::factory()->create([
            'title' => 'Story](https://example.org/) ![track](https://tracker.example/pixel) [More',
            'url' => 'https://example.org/story)![track](https://tracker.example/pixel)',
        ]);
        $run = $this->generationRun($article);
        $this->mock(ModelClient::class)->shouldReceive('complete')->once()->andReturn($this->draft($article->id));

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));

        $html = Str::markdown(WeeklyReview::sole()->markdown_content);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('%29', $html);
        $this->assertSame('SUCCESS', $run->fresh()->status);
    }

    public function test_review_preview_disables_remote_markdown_images(): void
    {
        $review = WeeklyReview::factory()->create(['markdown_content' => 'Text ![tracker](https://tracker.example/pixel)']);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->get('/admin/weekly-reviews/'.$review->id)
            ->assertOk()->assertViewHas('previewHtml', fn (string $html): bool => ! str_contains($html, '<img') && str_contains($html, 'Text'));
    }

    public static function obsoleteGenerationCases(): array
    {
        return ['draft success' => [false, false], 'candidate success' => [true, false], 'draft failure' => [false, true], 'candidate failure' => [true, true]];
    }

    #[DataProvider('obsoleteGenerationCases')]
    public function test_obsolete_generation_cannot_write_output_or_status(bool $candidate, bool $fails): void
    {
        $article = AiArticle::factory()->create();
        $run = $this->generationRun($article, $candidate ? ['mode' => 'candidate', 'review_id' => 123, 'revision' => 1] : []);
        $this->mock(ModelClient::class)->shouldReceive('complete')->once()->andReturnUsing(function () use ($run, $article, $fails): array {
            $run->refresh()->update(['attempt' => 2, 'status' => 'PENDING', 'payload' => ['new_attempt' => true]]);
            if ($fails) {
                throw new RuntimeException('Old attempt failed.');
            }

            return $this->draft($article->id);
        });

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));

        $this->assertSame('PENDING', $run->fresh()->status);
        $this->assertSame(2, $run->fresh()->attempt);
        $this->assertSame(['new_attempt' => true], $run->fresh()->payload);
        $this->assertDatabaseCount('weekly_reviews', 0);
    }

    public static function analysisOutcomes(): array
    {
        return ['successful model response' => [false], 'invalid model response' => [true]];
    }

    #[DataProvider('analysisOutcomes')]
    public function test_obsolete_analysis_preserves_newer_analysis_and_retry_payload(bool $invalid): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturnUsing(function () use ($run, $article, $invalid): array {
            $run->refresh()->update(['attempt' => 2, 'status' => 'PENDING', 'payload' => ['new_attempt' => true]]);
            $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'New result']);
            $article->update(['status' => 'ANALYZED']);

            return $invalid ? [] : $this->analysis();
        });

        (new AnalyzeArticles($run->id))->handle($model);

        $this->assertSame('New result', $article->analyses()->sole()->summary);
        $this->assertSame(['new_attempt' => true], $run->fresh()->payload);
        $this->assertSame('PENDING', $run->fresh()->status);
        Queue::assertNotPushed(AnalyzeArticles::class);
    }

    public function test_overlapping_analysis_runs_call_the_model_only_once(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $first = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $second = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturnUsing(function () use ($second, $model): array {
            (new AnalyzeArticles($second->id))->handle($model);

            return $this->analysis();
        });

        (new AnalyzeArticles($first->id))->handle($model);
        (new AnalyzeArticles($second->id))->handle($model);

        $this->assertSame(1, $article->analyses()->count());
        $this->assertSame($article->id, $first->fresh()->payload['cursor']);
        $this->assertSame('SUCCESS', $second->fresh()->status);
        Queue::assertPushed(AnalyzeArticles::class, fn (AnalyzeArticles $job): bool => $job->runId === $second->id && $job->delay === 5);
    }

    public function test_batch_analysis_excludes_articles_without_usable_content(): void
    {
        AiArticle::factory()->create(['raw_summary' => null, 'raw_content' => '']);
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldNotReceive('complete');
        Queue::fake([AnalyzeArticles::class]);

        (new AnalyzeArticles($run->id))->handle($model);

        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame([], $run->fresh()->payload);
        Queue::assertNotPushed(AnalyzeArticles::class);
    }

    public function test_review_edits_remove_unused_sources_and_rebuild_numbered_references(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $review = WeeklyReview::factory()->create(['status' => 'REVIEW']);
        $first = AiArticle::factory()->create();
        $second = AiArticle::factory()->create();
        $review->sourceArticles()->attach([$first->id => ['citation_order' => 1], $second->id => ['citation_order' => 2]]);

        $this->putJson('/admin/weekly-reviews/'.$review->id, [
            'revision' => 1, 'title' => 'Edited', 'markdown_content' => "Only second [2].\n\n## Nguồn tham khảo\nUntrusted reference list",
        ])->assertOk()->assertJsonPath('data.revision', 2);

        $this->assertSame($second->id, $review->sourceArticles()->sole()->id);
        $this->assertStringContainsString('Only second [1].', $review->fresh()->markdown_content);
        $this->assertStringContainsString($second->url, $review->fresh()->markdown_content);
        $this->assertStringNotContainsString('Untrusted reference list', $review->fresh()->markdown_content);

        $this->putJson('/admin/weekly-reviews/'.$review->id, [
            'revision' => 2, 'title' => 'No sources', 'markdown_content' => 'All references removed.',
        ])->assertOk();
        $this->assertSame(0, $review->sourceArticles()->count());
        $this->postJson('/admin/weekly-reviews/'.$review->id.'/convert-to-post')->assertConflict();
    }

    public function test_review_edit_rejects_unknown_citations_without_saving(): void
    {
        $review = WeeklyReview::factory()->create(['status' => 'REVIEW']);
        $article = AiArticle::factory()->create();
        $review->sourceArticles()->attach($article, ['citation_order' => 1]);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))->putJson('/admin/weekly-reviews/'.$review->id, [
            'revision' => 1, 'title' => 'Invalid', 'markdown_content' => 'Unknown [999].',
        ])->assertUnprocessable()->assertJsonValidationErrors('markdown_content');
        $this->assertSame(1, $review->fresh()->revision);
        $this->assertSame($article->id, $review->sourceArticles()->sole()->id);
    }

    public static function collectionStatuses(): array
    {
        return ['pending' => ['PENDING', false], 'running' => ['RUNNING', false], 'complete' => ['SUCCESS', true]];
    }

    #[DataProvider('collectionStatuses')]
    public function test_baseline_requires_all_prior_collection_runs_to_finish(string $status, bool $available): void
    {
        User::factory()->create(['role' => 'ADMIN']);
        $current = AiArticle::factory()->create(['published_at' => '2026-10-13 00:00:00']);
        $previous = AiArticle::factory()->create(['published_at' => '2026-10-06 00:00:00']);
        $topic = AiTopic::factory()->create(['slug' => 'research', 'status' => 'ACTIVE']);
        foreach ([$current, $previous] as $article) {
            $article->analyses()->create(['version' => 1, 'is_current' => true, 'status' => 'SUCCESS', 'summary' => 'Summary']);
            $article->topics()->attach($topic, ['confidence' => 0.9]);
        }
        AutomationRun::factory()->create(['run_type' => 'COLLECT', 'status' => 'SUCCESS', 'created_at' => '2026-10-06 00:00:00']);
        AutomationRun::factory()->create(['run_type' => 'COLLECT', 'status' => $status, 'created_at' => '2026-10-07 00:00:00']);
        $run = AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'attempt' => 1, 'payload' => ['week_start' => '2026-10-12']]);
        $this->mock(ModelClient::class)->shouldReceive('complete')->once()->andReturn($this->draft($current->id));

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));

        $this->assertSame('SUCCESS', $run->fresh()->status);
        $this->assertSame($available, $run->fresh()->payload['snapshot']['topics']['research']['baseline_available']);
    }

    public function test_generation_rejects_section_responses_with_top_level_headings(): void
    {
        $article = AiArticle::factory()->create();
        $run = $this->generationRun($article, ['mode' => 'candidate', 'section' => 'overview', 'review_id' => 123, 'revision' => 1]);
        $draft = $this->draft($article->id);
        $draft['markdown_content'] = "## Tổng quan tuần\nDuplicate heading [[{$article->id}]]";
        $this->mock(ModelClient::class)->shouldReceive('complete')->once()->andReturn($draft);

        (new GenerateWeeklyReview($run->id))->handle(app(WeeklyDraftBuilder::class), app(DraftPostCreator::class));

        $this->assertSame('FAILED', $run->fresh()->status);
        $this->assertStringContainsString('top-level headings', $run->fresh()->error_message);
        $this->assertArrayNotHasKey('candidate', $run->fresh()->payload);
    }

    public function test_analysis_failures_identify_invalid_fields_without_storing_model_values(): void
    {
        Queue::fake([AnalyzeArticles::class]);
        $article = AiArticle::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'ANALYZE', 'attempt' => 1]);
        $model = $this->mock(ModelClient::class);
        $model->shouldReceive('complete')->once()->andReturn(['topics' => [['slug' => 'private-token', 'confidence' => 2]]]);

        (new AnalyzeArticles($run->id))->handle($model);

        $error = $article->analyses()->sole()->error_message;
        $this->assertStringContainsString('ValidationException', $error);
        $this->assertStringContainsString('summary', $error);
        $this->assertStringContainsString('topics.0.slug', $error);
        $this->assertStringNotContainsString('private-token', $error);
        $this->assertSame($error, $run->fresh()->payload['article_results'][$article->id]['error']);
        Queue::assertPushed(AnalyzeArticles::class, 1);
    }

    public function test_obsolete_collection_cannot_insert_articles_after_retry(): void
    {
        $source = AiSource::factory()->create(['feed_url' => 'https://1.1.1.1/feed']);
        $run = AutomationRun::factory()->create(['run_type' => 'COLLECT', 'attempt' => 1, 'payload' => ['sources' => [$source->id => ['status' => 'PENDING']]]]);
        Http::fake(function () use ($run) {
            $run->refresh()->update(['attempt' => 2, 'status' => 'PENDING', 'payload' => ['new_attempt' => true]]);

            return Http::response('<rss><channel><item><title>Old story</title><link>https://example.org/old</link><description>Summary</description></item></channel></rss>');
        });

        (new CollectSource($run->id, $source->id))->handle(app(SourceFetcher::class), app(ArticleNormalizer::class));

        $this->assertDatabaseCount('ai_articles', 0);
        $this->assertSame(['new_attempt' => true], $run->fresh()->payload);
        $this->assertNull($source->fresh()->last_fetched_at);
        Http::assertSentCount(1);
    }

    /** @param array<string, mixed> $payload */
    private function generationRun(AiArticle $article, array $payload = []): AutomationRun
    {
        User::factory()->create(['role' => 'ADMIN']);

        return AutomationRun::factory()->create(['run_type' => 'GENERATE_REVIEW', 'attempt' => 1, 'payload' => $payload + [
            'week_start' => '2026-10-12',
            'snapshot' => [
                'week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'model' => 'test-model',
                'articles' => [['id' => $article->id, 'title' => $article->title, 'url' => $article->url, 'source_name' => 'Source']],
            ],
        ]]);
    }

    /** @return array<string, mixed> */
    private function draft(int $articleId): array
    {
        return [
            'title' => 'AI Weekly', 'excerpt' => 'Summary', 'citation_ids' => [$articleId], 'suggested_tags' => [],
            'markdown_content' => "## Tổng quan tuần\nOverview [[{$articleId}]].\n\n## Tin nổi bật\nStories.\n\n## Nghiên cứu đáng chú ý\nResearch.\n\n## Chủ đề và xu hướng\nTrends.\n\n## Cơ hội\nOpportunities.\n\n## Rủi ro và giới hạn\nRisks.",
        ];
    }

    /** @return array<string, mixed> */
    private function analysis(): array
    {
        return ['summary' => 'Summary', 'key_points' => [], 'technologies' => [], 'companies' => [], 'opportunities' => [], 'risks' => [], 'topics' => []];
    }
}
