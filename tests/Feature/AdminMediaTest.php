<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_media(): void
    {
        $response = $this->get('/admin/media');

        $response->assertRedirect('/login');
    }

    public function test_normal_user_cannot_access_admin_media(): void
    {
        $user = User::factory()->create([
            'role' => 'USER',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/media');

        $response->assertForbidden();
    }

    public function test_admin_can_view_media_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'example.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/example.jpg',
            'url' => '/storage/media/example.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson('/admin/media');

        $response->assertOk();

        $response->assertJsonFragment([
            'file_name' => 'example.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function test_admin_can_upload_image(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $file = UploadedFile::fake()->image(
            'cover.jpg',
            800,
            600
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media', [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated();

        $media = Media::query()->first();

        $this->assertNotNull($media);

        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(800, $media->width);
        $this->assertSame(600, $media->height);

        $disk->assertExists($media->storage_key);
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $file = UploadedFile::fake()->create(
            'example.txt',
            100,
            'text/plain'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media', [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'file',
        ]);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_rejects_file_larger_than_ten_megabytes(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $file = UploadedFile::fake()->create(
            'large.pdf',
            11000,
            'application/pdf'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media', [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'file',
        ]);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_rejects_filename_longer_than_255_characters(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        // 252 ký tự + ".jpg" = 256 ký tự
        $fileName = str_repeat('a', 252).'.jpg';

        $file = UploadedFile::fake()->image(
            $fileName,
            100,
            100
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media', [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'file',
        ]);

        $this->assertDatabaseCount('media', 0);
        $this->assertEmpty($disk->allFiles());
    }

    public function test_upload_rejects_malformed_image(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $brokenJpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        $file = UploadedFile::fake()->createWithContent(
            'broken.jpg',
            $brokenJpeg
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media', [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'file',
        ]);

        $this->assertDatabaseCount('media', 0);
        $this->assertEmpty($disk->allFiles());
    }
}
