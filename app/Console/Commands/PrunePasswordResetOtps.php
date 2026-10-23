<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('app:prune-password-reset-otps')]
#[Description('Remove expired and orphaned password reset OTPs')]
class PrunePasswordResetOtps extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = DB::table('password_reset_otps')
            ->where(function (Builder $query): void {
                $query->whereNull('user_id')
                    ->orWhere('expires_at', '<', now());
            })
            ->delete();

        $this->info("Deleted {$deleted} password reset OTP(s).");

        return self::SUCCESS;
    }
}
