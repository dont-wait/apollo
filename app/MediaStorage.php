<?php

namespace App;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorage
{
    public function __construct(
        private Factory $filesystems,
        private Repository $config
    ) {}

    public function diskName(): string
    {
        return (string) $this->config->get(
            'filesystems.media_disk'
        );
    }

    public function generateStorageKey(
        UploadedFile $file,
        string $directory = 'media'
    ): string {
        $extension = $file->extension();

        $fileName = (string) Str::uuid();

        if ($extension !== '') {
            $fileName .= '.'.$extension;
        }

        return trim($directory, '/').'/'.$fileName;
    }

    /**
     * Callers validate and authorize uploads before storing these attributes in Media.
     *
     * @return array{file_name: string, mime_type: string, size_bytes: int, storage_key: string, url: string}
     */
    public function store(
        UploadedFile $file,
        string $directory = 'media'
    ): array {
        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->disk(
            $this->diskName()
        );

        $mimeType =
            $file->getMimeType()
            ?? 'application/octet-stream';

        $size = $file->getSize();

        $path = $disk->putFile(
            $directory,
            $file
        );

        if (! is_string($path)) {
            throw new RuntimeException(
                'Không thể lưu tệp media.'
            );
        }

        return [
            'file_name' => basename(
                $file->getClientOriginalName()
            ),
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'storage_key' => $path,
            'url' => $disk->url($path),
        ];
    }

    /**
     * @return array{file_name: string, mime_type: string, size_bytes: int, storage_key: string, url: string}
     */
    public function storeAt(
        UploadedFile $file,
        string $storageKey
    ): array {
        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->disk(
            $this->diskName()
        );

        $mimeType =
            $file->getMimeType()
            ?? 'application/octet-stream';

        $size = $file->getSize();

        $stored = $disk->putFileAs(
            dirname($storageKey),
            $file,
            basename($storageKey)
        );

        if (! is_string($stored)) {
            throw new RuntimeException(
                'Không thể lưu tệp media.'
            );
        }

        return [
            'file_name' => basename(
                $file->getClientOriginalName()
            ),
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'storage_key' => $stored,
            'url' => $disk->url($stored),
        ];
    }

    public function deleteIfExistsFromDisk(
        string $diskName,
        string $storageKey
    ): void {
        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->disk($diskName);

        if (! $disk->exists($storageKey)) {
            return;
        }

        if (! $disk->delete($storageKey)) {
            throw new RuntimeException(
                'Không thể xóa tệp media.'
            );
        }
    }

    public function delete(string $storageKey): void
    {
        if (
            ! $this->filesystems
                ->disk($this->diskName())
                ->delete($storageKey)
        ) {
            throw new RuntimeException(
                'Không thể xóa tệp media.'
            );
        }
    }
}
