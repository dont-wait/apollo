<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MediaOperationLockService
{
    public function lock(): void
    {
        $lock = DB::table('media_operation_locks')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($lock === null) {
            throw new RuntimeException('Không thể khóa các thao tác media.');
        }
    }
}
