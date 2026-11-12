<?php

namespace Tests\Feature;

use App\MediaStorage;
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

    public function test_media_page_shows_the_configured_storage_label(): void
    {
        config([
            'filesystems.media_disk' => 'rustfs',
        ]);

        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/media');

        $response->assertOk();

        $response->assertSeeText('RUSTFS STORAGE ACTIVE');
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

    public function test_delete_keeps_durable_cleanup_when_storage_delete_fails(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'delete-fails.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/delete-fails.jpg',
            'url' => '/storage/media/delete-fails.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        $mediaStorage = \Mockery::mock(MediaStorage::class);

        $mediaStorage
            ->shouldReceive('diskName')
            ->once()
            ->andReturn('media-test');

        $mediaStorage
            ->shouldReceive('deleteIfExistsFromDisk')
            ->once()
            ->with(
                'media-test',
                'media/delete-fails.jpg'
            )
            ->andThrow(
                new \RuntimeException('Storage unavailable.')
            );

        $this->app->instance(
            MediaStorage::class,
            $mediaStorage
        );

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

        $this->assertDatabaseHas(
            'media_storage_cleanups',
            [
                'disk' => 'media-test',
                'storage_key' => 'media/delete-fails.jpg',
                'attempts' => 1,
            ]
        );
    }

    public function test_replace_succeeds_and_keeps_cleanup_when_old_storage_delete_fails(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

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

        $mediaStorage = \Mockery::mock(MediaStorage::class);

        $mediaStorage
            ->shouldReceive('diskName')
            ->once()
            ->andReturn('media-test');

        $mediaStorage
            ->shouldReceive('generateStorageKey')
            ->once()
            ->with(\Mockery::type(UploadedFile::class))
            ->andReturn('media/new-key.jpg');

        $mediaStorage
            ->shouldReceive('storeAt')
            ->once()
            ->with(
                \Mockery::type(UploadedFile::class),
                'media/new-key.jpg'
            )
            ->andReturn([
                'file_name' => 'new.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 1024,
                'storage_key' => 'media/new-key.jpg',
                'url' => '/storage/media/new-key.jpg',
            ]);

        $mediaStorage
            ->shouldReceive('deleteIfExistsFromDisk')
            ->once()
            ->with(
                'media-test',
                'media/old.jpg'
            )
            ->andThrow(
                new \RuntimeException('Storage unavailable.')
            );

        $this->app->instance(
            MediaStorage::class,
            $mediaStorage
        );

        $response = $this
            ->actingAs($admin)
            ->post(
                "/admin/media/{$media->id}/replace",
                [
                    'file' => $newFile,
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response->assertOk();

        $response->assertJson([
            'message' => 'Thay thế media thành công.',
        ]);

        $media->refresh();

        $this->assertSame(
            'media/new-key.jpg',
            $media->storage_key
        );

        $this->assertDatabaseHas(
            'media_storage_cleanups',
            [
                'disk' => 'media-test',
                'storage_key' => 'media/old.jpg',
                'attempts' => 1,
            ]
        );

        $this->assertDatabaseMissing(
            'media_storage_cleanups',
            [
                'disk' => 'media-test',
                'storage_key' => 'media/new-key.jpg',
            ]
        );
    }

    public function test_admin_cannot_delete_media_referenced_by_relative_markdown_url(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/relative.jpg', 'fake-image');

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'relative.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/relative.jpg',
            'url' => 'https://cdn.example.test/media/relative.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        Post::create([
            'author_id' => $admin->id,
            'title' => 'Relative media reference',
            'slug' => 'relative-media-reference',
            'markdown_content' => '![Image](/media/relative.jpg)',
            'status' => 'DRAFT',
            'source_type' => 'MANUAL',
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/admin/media/{$media->id}");

        $response->assertStatus(409);
        $this->assertModelExists($media);
        $disk->assertExists('media/relative.jpg');
    }

    public function test_thumbnail_replacement_respects_article_size_limit(): void
    {
        config([
            'filesystems.media_disk' => 'media-test',
        ]);

        $disk = Storage::fake('media-test');
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $disk->put('media/thumbnail.jpg', 'old-file');

        $media = Media::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'thumbnail.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_key' => 'media/thumbnail.jpg',
            'url' => '/storage/media/thumbnail.jpg',
            'width' => 800,
            'height' => 600,
        ]);

        Post::create([
            'author_id' => $admin->id,
            'thumbnail_media_id' => $media->id,
            'title' => 'Thumbnail size limit',
            'slug' => 'thumbnail-size-limit',
            'markdown_content' => 'Content',
            'status' => 'DRAFT',
            'source_type' => 'MANUAL',
        ]);

        $newFile = UploadedFile::fake()
            ->image('too-large.png', 1200, 900)
            ->size(Post::MAX_THUMBNAIL_KB + 1);

        $response = $this
            ->actingAs($admin)
            ->post('/admin/media/'.$media->id.'/replace', [
                'file' => $newFile,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file']);

        $media->refresh();
        $this->assertSame('media/thumbnail.jpg', $media->storage_key);
        $disk->assertExists('media/thumbnail.jpg');
        $this->assertCount(1, $disk->allFiles());
    }
}
