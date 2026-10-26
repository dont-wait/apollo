<?php

namespace Tests\Feature;

use App\Services\MediaOperationLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaOperationLockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_recreates_the_mutex_row_when_it_is_missing(): void
    {
        DB::table('media_operation_locks')->where('id', 1)->delete();

        DB::transaction(function (): void {
            app(MediaOperationLockService::class)->lock();
        });

        $this->assertDatabaseHas('media_operation_locks', [
            'id' => 1,
        ]);
    }
}
