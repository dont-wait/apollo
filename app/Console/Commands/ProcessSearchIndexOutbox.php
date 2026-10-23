<?php

namespace App\Console\Commands;

use App\Jobs\SyncPostSearch;
use App\Models\SearchIndexOutbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('search:process-outbox {--limit=100 : Maximum number of pending records to process}')]
#[Description('Process pending transactional search-index updates')]
class ProcessSearchIndexOutbox extends Command
{
    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $staleLock = now()->subMinutes(10);
        $pending = SearchIndexOutbox::query()
            ->where(function (Builder $query) use ($staleLock): void {
                $query->whereNull('locked_at')->orWhere('locked_at', '<', $staleLock);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pending as $outbox) {
            $claimed = DB::transaction(function () use ($outbox, $staleLock): bool {
                $claim = SearchIndexOutbox::query()
                    ->whereKey($outbox->id)
                    ->where('version', $outbox->version)
                    ->where(function (Builder $query) use ($staleLock): void {
                        $query->whereNull('locked_at')->orWhere('locked_at', '<', $staleLock);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($claim === null) {
                    return false;
                }

                $claim->increment('attempts');
                $claim->forceFill([
                    'locked_at' => now(),
                    'available_at' => null,
                ])->save();

                return true;
            });

            if (! $claimed) {
                continue;
            }

            try {
                (new SyncPostSearch((int) $outbox->post_id))->handle();
            } catch (Throwable $exception) {
                SearchIndexOutbox::query()
                    ->whereKey($outbox->id)
                    ->update([
                        'available_at' => now()->addMinute(),
                        'locked_at' => null,
                        'last_error' => $exception->getMessage(),
                        'updated_at' => now(),
                    ]);

                $this->warn("Search index sync failed for post {$outbox->post_id}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
