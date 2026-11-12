<?php

namespace Tests\Feature;

use App\Models\AutomationRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AiPipelineDispatchConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_analysis_requests_create_and_dispatch_only_one_run(): void
    {
        $script = <<<'WORKER'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.connections.mysql' => json_decode(getenv('AI_WEEKLY_TEST_DATABASE'), true),
    'database.default' => 'mysql', 'cache.default' => 'database',
]);
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\Queue::fake([App\Jobs\AiWeekly\AnalyzeArticles::class]);
if ($argv[1] === 'first') {
    Illuminate\Support\Facades\DB::listen(function (Illuminate\Database\Events\QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'automation_runs')) {
            fwrite(STDOUT, "active-read\n");
            usleep(1500000);
        }
    });
}
$run = app(App\AiWeekly\RunCoordinator::class)->analyze('MANUAL');
fwrite(STDOUT, 'run='.$run->id.';queued='.Illuminate\Support\Facades\Queue::pushed(App\Jobs\AiWeekly\AnalyzeArticles::class)->count()."\n");
WORKER;
        $environment = ['AI_WEEKLY_TEST_DATABASE' => json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR)];
        $first = new Process([PHP_BINARY, '-r', $script, 'first'], base_path(), $environment, timeout: 15);
        $second = new Process([PHP_BINARY, '-r', $script, 'second'], base_path(), $environment, timeout: 15);

        try {
            $first->start();
            $this->assertTrue($first->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'active-read')), $first->getErrorOutput());
            $second->start();
            $first->wait();
            $second->wait();

            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
            $run = AutomationRun::query()->sole();
            $this->assertSame('PENDING', $run->status);
            $this->assertStringContainsString('run='.$run->id.';queued=1', $first->getOutput());
            $this->assertStringContainsString('run='.$run->id.';queued=0', $second->getOutput());
        } finally {
            $first->stop();
            $second->stop();
        }
    }
}
