<?php

namespace Tests\Feature;

use App\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rustfs')]
class RustfsStorageTest extends TestCase
{
    public function test_media_can_be_uploaded_read_publicly_and_deleted_on_rustfs(): void
    {
        if (getenv('RUSTFS_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Run make storage-up, then set RUSTFS_INTEGRATION_TESTS=1 to test local RustFS.');
        }

        config(['filesystems.media_disk' => 'rustfs']);
        $storage = app(MediaStorage::class);
        $contents = 'Apollo RustFS integration test';
        $file = UploadedFile::fake()->createWithContent('storage-test.txt', $contents);
        $attributes = $storage->store($file, 'integration-tests');

        try {
            $this->assertSame($contents, Storage::disk('rustfs')->get($attributes['storage_key']));
            $response = Http::timeout(10)->get($attributes['url']);
            $this->assertSame(200, $response->status());
            $this->assertSame($contents, $response->body());
        } finally {
            $storage->delete($attributes['storage_key']);
        }

        $this->assertFalse(Storage::disk('rustfs')->exists($attributes['storage_key']));
    }
}
