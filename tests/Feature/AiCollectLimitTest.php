<?php

namespace Tests\Feature;

use App\AiWeekly\ArticleNormalizer;
use App\AiWeekly\SourceFetcher;
use App\Jobs\AiWeekly\CollectSource;
use App\Models\AiSource;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class AiCollectLimitTest extends TestCase
{
    use RefreshDatabase;

    private function rss(int $items): string
    {
        $entries = collect(range(1, $items))->map(fn (int $number): string => "<item><title>Story {$number}</title><link>https://example.org/story-{$number}</link><description>Summary {$number}</description></item>")->implode('');

        return "<rss><channel>{$entries}</channel></rss>";
    }

    public function test_fetcher_stops_at_the_requested_item_limit_for_rss(): void
    {
        $source = AiSource::factory()->create(['feed_url' => 'https://1.1.1.1/rss']);
        Http::fake(['https://1.1.1.1/rss' => Http::response($this->rss(10), 200)]);

        $this->assertCount(3, app(SourceFetcher::class)->fetch($source, 3));
        $this->assertCount(10, app(SourceFetcher::class)->fetch($source));
    }

    public function test_fetcher_stops_at_the_requested_item_limit_for_api_sources(): void
    {
        $source = AiSource::factory()->create(['source_type' => 'API', 'feed_url' => 'https://1.1.1.1/articles']);
        $items = array_map(fn (int $number): array => ['title' => "A{$number}", 'url' => "https://example.org/a{$number}"], range(1, 6));
        Http::fake(['https://1.1.1.1/articles' => Http::response(['items' => $items])]);

        $this->assertCount(2, app(SourceFetcher::class)->fetch($source, 2));
    }

    public function test_the_default_limit_still_comes_from_config(): void
    {
        config(['ai_weekly.max_items_per_source' => 4]);
        $source = AiSource::factory()->create(['feed_url' => 'https://1.1.1.1/rss']);
        Http::fake(['https://1.1.1.1/rss' => Http::response($this->rss(10), 200)]);

        $this->assertCount(4, app(SourceFetcher::class)->fetch($source));
    }

    public function test_collect_job_passes_the_runs_item_limit_to_the_fetcher(): void
    {
        $source = AiSource::factory()->create();
        $run = AutomationRun::factory()->create(['run_type' => 'COLLECT', 'attempt' => 1, 'payload' => ['sources' => [$source->id => ['status' => 'PENDING']], 'max_items' => 3]]);
        $fetcher = $this->mock(SourceFetcher::class, function (MockInterface $mock) use ($source): void {
            $mock->shouldReceive('fetch')->once()->withArgs(fn ($given, $limit): bool => $given->is($source) && $limit === 3)->andReturn([]);
        });

        (new CollectSource($run->id, $source->id))->handle($fetcher, app(ArticleNormalizer::class));
    }

    public function test_admin_can_start_a_collection_with_an_item_limit_and_chosen_sources(): void
    {
        Queue::fake([CollectSource::class]);
        $chosen = AiSource::factory()->create();
        AiSource::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/admin/automation-runs/collect', ['max_items' => 25, 'source_ids' => [$chosen->id]])
            ->assertAccepted()
            ->json('run_id');

        $payload = AutomationRun::findOrFail($id)->payload;
        $this->assertSame(25, $payload['max_items']);
        $this->assertSame([(string) $chosen->id], array_map('strval', array_keys($payload['sources'])));
        Queue::assertPushed(CollectSource::class, 1);
    }

    public function test_collection_without_options_uses_every_active_source_and_the_default_limit(): void
    {
        Queue::fake([CollectSource::class]);
        AiSource::factory()->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/admin/automation-runs/collect')->assertAccepted()->json('run_id');

        $payload = AutomationRun::findOrFail($id)->payload;
        $this->assertCount(2, $payload['sources']);
        $this->assertArrayNotHasKey('max_items', $payload);
    }

    public function test_item_limit_and_sources_are_validated(): void
    {
        $inactive = AiSource::factory()->create(['status' => 'INACTIVE']);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin/automation-runs/collect', ['max_items' => 0])->assertUnprocessable()->assertJsonValidationErrors('max_items');
        $this->postJson('/admin/automation-runs/collect', ['max_items' => 5000])->assertUnprocessable()->assertJsonValidationErrors('max_items');
        $this->postJson('/admin/automation-runs/collect', ['max_items' => 'many'])->assertUnprocessable();
        $this->postJson('/admin/automation-runs/collect', ['source_ids' => [999999]])->assertUnprocessable();
        // An inactive source leaves nothing to collect.
        $this->postJson('/admin/automation-runs/collect', ['source_ids' => [$inactive->id]])->assertUnprocessable();

        $this->assertDatabaseCount('automation_runs', 0);
    }

    public function test_a_run_with_a_different_item_limit_is_not_folded_into_an_active_one(): void
    {
        Queue::fake([CollectSource::class]);
        AiSource::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $first = $this->postJson('/admin/automation-runs/collect', ['max_items' => 10])->assertAccepted()->assertJsonPath('reused', false)->json('run_id');
        $same = $this->postJson('/admin/automation-runs/collect', ['max_items' => 10])->assertAccepted()->assertJsonPath('reused', true)->json('run_id');
        $other = $this->postJson('/admin/automation-runs/collect', ['max_items' => 50])->assertAccepted()->assertJsonPath('reused', false)->json('run_id');

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $other);
    }

    public function test_pipeline_page_offers_the_collect_dialog_with_the_default_limit(): void
    {
        AiSource::factory()->create(['name' => 'Example Feed']);
        config(['ai_weekly.max_items_per_source' => 120]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/automation-runs')
            ->assertOk()
            ->assertSee('open-collect-dialog', false)
            ->assertSee('collectDialog(', false)
            ->assertSee('Example Feed')
            ->assertSee('defaultMax: 120', false)
            ->assertSeeText('Số tài liệu tối đa mỗi nguồn');
    }
}
