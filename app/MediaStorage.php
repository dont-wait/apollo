<?php

namespace App;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class MediaStorage
{
    public function __construct(private Factory $filesystems, private Repository $config) {}

    /**
     * Callers validate and authorize uploads before storing these attributes in Media.
     *
     * @return array{file_name: string, mime_type: string, size_bytes: int, storage_key: string, url: string}
     */
    public function store(UploadedFile $file, string $directory = 'media'): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->disk(
            $this->config->get('filesystems.media_disk')
        );
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $size = $file->getSize();
        $path = $disk->putFile($directory, $file);

        if (! is_string($path)) {
            throw new RuntimeException('Không thể lưu tệp media.');
        }

        return [
            'file_name' => basename($file->getClientOriginalName()),
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'storage_key' => $path,
            'url' => $disk->url($path),
        ];
    }

    public function delete(string $storageKey): void
    {
        if (! $this->filesystems->disk($this->config->get('filesystems.media_disk'))->delete($storageKey)) {
            throw new RuntimeException('Không thể xóa tệp media.');
        }
    }
}
