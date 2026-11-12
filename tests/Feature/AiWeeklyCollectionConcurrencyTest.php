<?php

namespace Tests\Feature;

use App\Models\AiArticle;
use App\Models\AiSource;
use App\Models\AutomationRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AiWeeklyCollectionConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_collectors_for_the_same_source_keep_only_one_primary_story(): void
    {
        $source = AiSource::factory()->create(['feed_url' => 'https://1.1.1.1/feed']);
        $runs = AutomationRun::factory()->count(2)->create([
            'run_type' => 'COLLECT', 'attempt' => 1,
            'payload' => ['sources' => [$source->id => ['status' => 'PENDING']]],
        ]);
        $finished = tempnam(sys_get_temp_dir(), 'apollo-collection-');
        unlink($finished);
        $script = <<<'WORKER'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.mysql' => json_decode(getenv('AI_WEEKLY_TEST_DATABASE'), true), 'database.default' => 'mysql']);
Illuminate\Support\Facades\DB::purge('mysql');
$worker = (int) $argv[1];
$finished = $argv[4];
if ($worker === 1) {
    Illuminate\Support\Facades\DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use ($finished): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'title_fingerprint')) {
            fwrite(STDOUT, "story-read\n");
            $deadline = microtime(true) + 2;
            while (! file_exists($finished) && microtime(true) < $deadline) {
                usleep(10000);
            }
        }
    });
}
Illuminate\Support\Facades\Http::fake([
    '*' => Illuminate\Support\Facades\Http::response('<rss><channel><item><title>Same story</title><link>https://example.org/story-'.$worker.'</link><description>Same summary</description></item></channel></rss>'),
]);
(new App\Jobs\AiWeekly\CollectSource((int) $argv[2], (int) $argv[3]))->handle(app(App\AiWeekly\SourceFetcher::class), app(App\AiWeekly\ArticleNormalizer::class));
Illuminate\Support\Facades\Http::assertSentCount(1);
if ($worker === 2) {
    touch($finished);
}
WORKER;
        $environment = ['AI_WEEKLY_TEST_DATABASE' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR)];
        $first = new Process([PHP_BINARY, '-r', $script, '1', (string) $runs[0]->id, (string) $source->id, $finished], base_path(), $environment, timeout: 15);
        $second = new Process([PHP_BINARY, '-r', $script, '2', (string) $runs[1]->id, (string) $source->id, $finished], base_path(), $environment, timeout: 15);

        try {
            $first->start();
            $this->assertTrue($first->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'story-read')), $first->getErrorOutput());
            $second->start();
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());

            $this->assertSame(2, AiArticle::count());
            $primary = AiArticle::query()->whereNull('duplicate_of')->sole();
            $this->assertSame($primary->id, AiArticle::query()->whereNotNull('duplicate_of')->sole()->duplicate_of);
            $this->assertSame(['SUCCESS', 'SUCCESS'], $runs->map(fn (AutomationRun $run): string => $run->fresh()->status)->all());
        } finally {
            $first->stop();
            $second->stop();
            if (file_exists($finished)) {
                unlink($finished);
            }
        }
    }
}
