<?php

namespace App\Console\Commands;

use App\Services\PostPublicationService;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled {--limit=100 : Maximum number of due posts to process}';

    protected $description = 'Publish scheduled posts whose publish time has arrived';

    public function handle(PostPublicationService $publicationService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $publicationService->publishDue(now(), $limit);

        $this->info("Published {$result['published']} scheduled post(s).");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} post(s) failed and remain scheduled for retry.");
        }

        return self::SUCCESS;
    }
}
