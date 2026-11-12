<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaStorageCleanup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_command_removes_due_storage_object_and_cleanup_record(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $disk->put(
            'media/orphaned.jpg',
            'orphaned-file'
        );

        MediaStorageCleanup::create([
            'disk' => 'media-test',
            'storage_key' => 'media/orphaned.jpg',
            'attempts' => 0,
            'available_at' => now()->subMinute(),
        ]);

        $disk->assertExists(
            'media/orphaned.jpg'
        );

        $this->artisan(
            'media:cleanup-storage',
            [
                '--limit' => 100,
            ]
        )
            ->expectsOutput(
                'Cleaned up 1 media storage object(s).'
            )
            ->assertExitCode(0);

        $disk->assertMissing(
            'media/orphaned.jpg'
        );

        $this->assertDatabaseMissing(
            'media_storage_cleanups',
            [
                'disk' => 'media-test',
                'storage_key' => 'media/orphaned.jpg',
            ]
        );
    }

    public function test_cleanup_command_keeps_a_storage_object_referenced_by_media(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');
        $admin = User::factory()->admin()->create();

        $disk->put('media/live.jpg', 'live-file');

        Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'live.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/live.jpg',
            'url' => '/storage/media/live.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        $cleanup = MediaStorageCleanup::create([
            'disk' => 'media-test',
            'storage_key' => 'media/live.jpg',
            'attempts' => 0,
            'available_at' => now()->subMinute(),
        ]);

        $this->artisan('media:cleanup-storage')
            ->expectsOutput('Cleaned up 1 media storage object(s).')
            ->assertExitCode(0);

        $disk->assertExists('media/live.jpg');
        $this->assertModelMissing($cleanup);
    }
}
