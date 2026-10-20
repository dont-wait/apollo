<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
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

    public function test_admin_can_delete_unused_media(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/example.jpg', 'fake-image');

        $media = Media::create([
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
            ->deleteJson("/admin/media/{$media->id}");

        $response->assertOk();

        $response->assertJson([
            'message' => 'Xóa media thành công.',
        ]);

        $this->assertDatabaseMissing('media', [
            'id' => $media->id,
        ]);

        $disk->assertMissing('media/example.jpg');
    }

    public function test_admin_cannot_delete_media_used_as_post_thumbnail(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/cover.jpg', 'fake-image');

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/cover.jpg',
            'url' => '/storage/media/cover.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        Post::create([
            'author_id' => $admin->id,
            'thumbnail_media_id' => $media->id,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'markdown_content' => 'Test content',
            'status' => 'DRAFT',
            'source_type' => 'MANUAL',
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/admin/media/{$media->id}");

        $response->assertStatus(409);

        $response->assertJson([
            'message' => 'Không thể xóa media vì đang được sử dụng.',
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
        ]);

        $disk->assertExists('media/cover.jpg');
    }

    public function test_admin_cannot_delete_media_used_in_post_markdown(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/article-image.jpg', 'fake-image');

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'article-image.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/article-image.jpg',
            'url' => '/storage/media/article-image.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        Post::create([
            'author_id' => $admin->id,
            'title' => 'Markdown Media Post',
            'slug' => 'markdown-media-post',
            'markdown_content' => '![Image](/storage/media/article-image.jpg)',
            'status' => 'DRAFT',
            'source_type' => 'MANUAL',
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/admin/media/{$media->id}");

        $response->assertStatus(409);

        $response->assertJson([
            'message' => 'Không thể xóa media vì đang được sử dụng.',
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
        ]);

        $disk->assertExists('media/article-image.jpg');
    }

    public function test_admin_can_rename_media_without_changing_storage_reference(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'old-name.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/random-storage-key.jpg',
            'url' => '/storage/media/random-storage-key.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/admin/media/{$media->id}/rename", [
                'name' => 'new-name',
            ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'file_name' => 'new-name.jpg',
        ]);

        $media->refresh();

        $this->assertSame(
            'new-name.jpg',
            $media->file_name
        );

        $this->assertSame(
            'media/random-storage-key.jpg',
            $media->storage_key
        );

        $this->assertSame(
            '/storage/media/random-storage-key.jpg',
            $media->url
        );
    }

    public function test_media_rename_rejects_invalid_name(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'old-name.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/random-storage-key.jpg',
            'url' => '/storage/media/random-storage-key.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patchJson("/admin/media/{$media->id}/rename", [
                'name' => 'folder/new-name',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'file_name' => 'old-name.jpg',
        ]);
    }

    public function test_admin_can_replace_media_when_not_used_in_markdown(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/old.jpg', 'old-file');

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'old.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/old.jpg',
            'url' => '/storage/media/old.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        $newFile = UploadedFile::fake()->image(
            'new.jpg',
            1200,
            900
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media/'.$media->id.'/replace', [
                'file' => $newFile,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk();

        $media->refresh();

        $this->assertSame('new.jpg', $media->file_name);
        $this->assertSame(1200, $media->width);
        $this->assertSame(900, $media->height);

        $this->assertNotSame(
            'media/old.jpg',
            $media->storage_key
        );

        $disk->assertExists($media->storage_key);
        $disk->assertMissing('media/old.jpg');
    }

    public function test_admin_cannot_replace_media_used_in_post_markdown(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/old.jpg', 'old-file');

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'old.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/old.jpg',
            'url' => '/storage/media/old.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        Post::create([
            'author_id' => $admin->id,
            'title' => 'Post dùng media',
            'slug' => 'post-dung-media',
            'markdown_content' => '![Image](/storage/media/old.jpg)',
            'status' => 'DRAFT',
            'source_type' => 'MANUAL',
        ]);

        $newFile = UploadedFile::fake()->image(
            'new.jpg',
            1200,
            900
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media/'.$media->id.'/replace', [
                'file' => $newFile,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(409);

        $response->assertJson([
            'message' => 'Không thể thay thế media vì URL đang được sử dụng trong nội dung Markdown.',
        ]);

        $media->refresh();

        $this->assertSame('old.jpg', $media->file_name);
        $this->assertSame('media/old.jpg', $media->storage_key);

        $disk->assertExists('media/old.jpg');

        $this->assertCount(1, $disk->allFiles());
    }
}
