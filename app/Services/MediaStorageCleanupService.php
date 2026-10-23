<?php

namespace App\Services;

use App\MediaStorage;
use App\Models\MediaStorageCleanup;
use Carbon\CarbonInterface;
use Throwable;

class MediaStorageCleanupService
{
    public function schedule(
        string $disk,
        string $storageKey,
        ?CarbonInterface $availableAt = null
    ): MediaStorageCleanup {
        return MediaStorageCleanup::updateOrCreate(
            [
                'disk' => $disk,
                'storage_key' => $storageKey,
            ],
            [
                'available_at' => $availableAt ?? now(),
            ]
        );
    }

    public function cancel(
        string $disk,
        string $storageKey
    ): void {
        MediaStorageCleanup::query()
            ->where('disk', $disk)
            ->where('storage_key', $storageKey)
            ->delete();
    }

    public function attempt(
        MediaStorageCleanup $cleanup,
        MediaStorage $mediaStorage
    ): bool {
        try {
            $mediaStorage->deleteIfExistsFromDisk(
                $cleanup->disk,
                $cleanup->storage_key
            );

            $cleanup->delete();

            return true;
        } catch (Throwable $exception) {
            try {
                $attempts = $cleanup->attempts + 1;

                $cleanup->update([
                    'attempts' => $attempts,
                    'last_error' => $exception->getMessage(),
                    'available_at' => now()->addMinutes(
                        min(60, 2 ** min($attempts, 5))
                    ),
                ]);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            report($exception);

            return false;
        }
    }

    public function processDue(
        MediaStorage $mediaStorage,
        int $limit = 100
    ): int {
        $processed = 0;

        MediaStorageCleanup::query()
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (MediaStorageCleanup $cleanup) use (
                $mediaStorage,
                &$processed
            ): void {
                if ($this->attempt($cleanup, $mediaStorage)) {
                    $processed++;
                }
            });

        return $processed;
    }
}
