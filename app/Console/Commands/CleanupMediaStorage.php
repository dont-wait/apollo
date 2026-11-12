<?php

namespace App\Console\Commands;

use App\MediaStorage;
use App\Services\MediaStorageCleanupService;
use Illuminate\Console\Command;

class CleanupMediaStorage extends Command
{
    protected $signature = 'media:cleanup-storage {--limit=100}';

    protected $description = 'Retry pending media storage cleanup operations';

    public function handle(
        MediaStorageCleanupService $cleanupService,
        MediaStorage $mediaStorage
    ): int {
        $limit = max(1, (int) $this->option('limit'));

        $processed = $cleanupService->processDue(
            $mediaStorage,
            $limit
        );

        $this->info(
            "Cleaned up {$processed} media storage object(s)."
        );

        return self::SUCCESS;
    }
}
