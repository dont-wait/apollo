<?php

namespace Tests\Feature;

use App\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    public function test_upload_returns_database_metadata_and_a_public_url(): void
    {
        config(['filesystems.media_disk' => 'media-test', 'filesystems.disks.media-test.url' => 'https://media.example.test/apollo-media']);
        $disk = Storage::fake('media-test', ['url' => 'https://media.example.test/apollo-media']);
        $file = UploadedFile::fake()->createWithContent('example.txt', 'Media contents');

        $attributes = app(MediaStorage::class)->store($file, 'articles');

        $disk->assertExists($attributes['storage_key']);
        $this->assertSame('Media contents', $disk->get($attributes['storage_key']));
        $this->assertSame('https://media.example.test/apollo-media/'.$attributes['storage_key'], $attributes['url']);
        $this->assertSame('text/plain', $attributes['mime_type']);
        $this->assertSame(14, $attributes['size_bytes']);
        $this->assertSame(
            'example.txt',
            $attributes['file_name']
        );
        $this->assertStringStartsWith('articles/', $attributes['storage_key']);
    }

    public function test_delete_removes_the_object_by_storage_key(): void
    {
        config(['filesystems.media_disk' => 'media-test']);
        $disk = Storage::fake('media-test');
        $disk->put('articles/example.txt', 'contents');

        app(MediaStorage::class)->delete('articles/example.txt');

        $disk->assertMissing('articles/example.txt');
    }

    public function test_failed_upload_does_not_return_metadata(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        Storage::shouldReceive('disk')->with('rustfs')->once()->andReturnSelf();
        Storage::shouldReceive('putFile')->once()->andReturn(false);

        $this->expectException(RuntimeException::class);

        app(MediaStorage::class)->store(UploadedFile::fake()->createWithContent('example.txt', 'contents'));
    }

    public function test_failed_delete_is_reported(): void
    {
        config(['filesystems.media_disk' => 'rustfs']);
        Storage::shouldReceive('disk')->with('rustfs')->once()->andReturnSelf();
        Storage::shouldReceive('delete')->with('articles/example.txt')->once()->andReturn(false);

        $this->expectException(RuntimeException::class);

        app(MediaStorage::class)->delete('articles/example.txt');
    }
}
