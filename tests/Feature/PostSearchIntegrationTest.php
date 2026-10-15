<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use RuntimeException;
use Tests\TestCase;

class PostSearchIntegrationTest extends TestCase
{
    use DatabaseTruncation;

    private ?string $indexName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! config('scout.integration_tests')) {
            $this->markTestSkipped('Set MEILISEARCH_INTEGRATION_TESTS=true and start the Meilisearch Docker service.');
        }
        config([
            'scout.driver' => 'meilisearch',
            'scout.queue' => false,
            'scout.prefix' => 'apollo_test_'.Str::lower(Str::random(12)).'_',
        ]);
        $this->indexName = (new Post)->searchableAs();
        $client = app(Client::class);
        $task = $client->createIndex($this->indexName, ['primaryKey' => 'id']);
        $this->assertSame('succeeded', $client->waitForTask($task['taskUid'])['status']);
        $task = $client->index($this->indexName)->updateSettings(config('scout.meilisearch.index-settings')[Post::class]);
        $this->assertSame('succeeded', $client->waitForTask($task['taskUid'])['status']);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->indexName !== null) {
                $client = app(Client::class);
                $task = $client->deleteIndex($this->indexName);
                $client->waitForTask($task['taskUid']);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_typo_prefix_relevance_and_search_pagination(): void
    {
        $exact = Post::factory()->published()->create(['title' => 'Laravel deployment guide']);
        Post::factory()->published()->create(['title' => 'Laravell deployment guide']);
        Post::factory()->published()->count(10)->create(['title' => 'Laravel deployment patterns']);
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Larvel']))
            ->assertOk()->assertJsonPath('pagination.total', 12);
        $first = $this->getJson(route('home', ['q' => 'Laravel deployment guide']))
            ->assertOk()->assertJsonPath('data.0.id', $exact->id);
        $first = $this->getJson(route('home', ['q' => 'Larav']))
            ->assertOk()->assertJsonCount(10, 'data');
        $second = $this->getJson($first->json('pagination.next_url'))
            ->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
    }

    public function test_category_tags_visibility_and_publication_time_are_synced(): void
    {
        $category = Category::factory()->create(['name' => 'Astronomy']);
        $tag = Tag::factory()->create(['name' => 'Spaceflight']);
        $post = Post::factory()->published()->create(['category_id' => $category->id, 'title' => 'Orbital research']);
        $post->tags()->attach($tag);
        Post::factory()->create(['title' => 'Orbital draft']);
        Post::factory()->published()->create(['title' => 'Orbital future', 'published_at' => now()->addDay()]);
        $body = Post::factory()->published()->create(['title' => 'Spectral research', 'markdown_content' => 'Supernovaquasar observations']);
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Supernovaquasar']))
            ->assertOk()->assertJsonPath('data.0.id', $body->id);
        $this->getJson(route('home', ['q' => 'Spaceflght', 'category' => $category->slug]))
            ->assertOk()->assertJsonPath('data.0.id', $post->id);
        $this->getJson(route('home', ['q' => 'Orbital']))
            ->assertOk()->assertJsonPath('pagination.total', 1);

        $tag->update(['name' => 'Astrobiology']);
        $category->update(['name' => 'Planetology']);
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Astrobiology']))->assertOk()->assertJsonPath('data.0.id', $post->id);
        $this->getJson(route('home', ['q' => 'Planetology']))->assertOk()->assertJsonPath('data.0.id', $post->id);

        $post->tags()->detach($tag);
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Astrobiology']))->assertOk()->assertJsonPath('pagination.total', 0);

        $category->update(['status' => 'INACTIVE']);
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Orbital']))->assertOk()->assertJsonPath('pagination.total', 0);
        $category->update(['status' => 'ACTIVE']);
        $post->delete();
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Orbital']))->assertOk()->assertJsonPath('pagination.total', 0);
        $post->restore();
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Orbital']))->assertOk()->assertJsonPath('data.0.id', $post->id);

        $post->update(['status' => 'DRAFT']);
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Orbital']))->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_rolled_back_posts_are_not_indexed(): void
    {
        try {
            DB::transaction(function (): void {
                Post::factory()->published()->create(['title' => 'Rollbackquasar']);
                throw new RuntimeException('Rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback', $exception->getMessage());
        }
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Rollbackquasar']))
            ->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_explicit_sorts_and_bookmark_count_updates(): void
    {
        $older = Post::factory()->published()->create(['title' => 'Searchsort quasar', 'published_at' => now()->subDays(2), 'markdown_content' => str_repeat('word ', 600)]);
        $newer = Post::factory()->published()->create(['title' => 'Searchsort quasar', 'published_at' => now()->subDay(), 'markdown_content' => 'short']);
        $older->bookmarks()->attach(User::factory()->create());
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Searchsort', 'sort' => 'latest']))->assertOk()->assertJsonPath('data.0.id', $newer->id);
        $this->getJson(route('home', ['q' => 'Searchsort', 'sort' => 'reading']))->assertOk()->assertJsonPath('data.0.id', $older->id);
        $this->getJson(route('home', ['q' => 'Searchsort', 'sort' => 'popular']))->assertOk()->assertJsonPath('data.0.id', $older->id);
    }

    public function test_deleted_category_and_tag_are_removed_from_search_documents(): void
    {
        $category = Category::factory()->create(['name' => 'Obsoletecategory']);
        $tag = Tag::factory()->create(['name' => 'Obsoletetag']);
        $post = Post::factory()->published()->create(['title' => 'Preservedpost', 'category_id' => $category->id]);
        $post->tags()->attach($tag);
        $this->waitForIndex();
        $this->getJson(route('home', ['q' => 'Obsoletetag']))->assertOk()->assertJsonPath('data.0.id', $post->id);

        $category->delete();
        $tag->delete();
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Obsoletecategory']))->assertOk()->assertJsonPath('pagination.total', 0);
        $this->getJson(route('home', ['q' => 'Obsoletetag']))->assertOk()->assertJsonPath('pagination.total', 0);
        $this->getJson(route('home', ['q' => 'Preservedpost']))->assertOk()->assertJsonPath('data.0.id', $post->id);
    }

    public function test_rebuild_command_syncs_settings_and_imports_existing_posts(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Rebuildable search post']);
        $this->waitForIndex();

        $client = app(Client::class);
        $task = $client->deleteIndex($this->indexName);
        $this->assertSame('succeeded', $client->waitForTask($task['taskUid'])['status']);

        Artisan::call('search:rebuild');
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Rebuildable']))
            ->assertOk()->assertJsonPath('data.0.id', $post->id);
    }

    public function test_outbox_processor_recovers_an_update_without_a_queued_job(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Recoverable search post']);
        $this->waitForIndex();

        $client = app(Client::class);
        $task = $client->index($this->indexName)->deleteAllDocuments();
        $this->assertSame('succeeded', $client->waitForTask($task['taskUid'])['status']);

        DB::table('search_index_outbox')->insert([
            'post_id' => $post->id,
            'version' => 1,
            'available_at' => now(),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('search:process-outbox');
        $this->waitForIndex();

        $this->getJson(route('home', ['q' => 'Recoverable']))
            ->assertOk()->assertJsonPath('data.0.id', $post->id);
    }

    private function waitForIndex(): void
    {
        $client = app(Client::class);
        $tasks = $client->getTasks((new TasksQuery)->setIndexUids([$this->indexName])->setLimit(100));
        foreach ($tasks->getResults() as $task) {
            $completed = $client->waitForTask($task['uid'], 10000, 50);
            $this->assertSame('succeeded', $completed['status'], json_encode($completed['error'] ?? null));
        }
    }
}
