<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenameMediaRequest;
use App\Http\Requests\Admin\ReplaceMediaRequest;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\MediaStorage;
use App\Models\Media;
use App\Models\Post;
use App\Services\MediaOperationLockService;
use App\Services\MediaStorageCleanupService;
use App\Services\MediaUsageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const int PER_PAGE = 24;

    public function index(Request $request): JsonResponse|View|RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['image', 'pdf'])],
        ]);

        $media = Media::query()
            ->with('uploader:id,name,email')
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where('file_name', 'like', '%'.addcslashes($search, '%_\\').'%');
            })
            ->when(($filters['type'] ?? null) === 'image', fn (Builder $query) => $query->where('mime_type', 'like', 'image/%'))
            ->when(($filters['type'] ?? null) === 'pdf', fn (Builder $query) => $query->where('mime_type', 'application/pdf'))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($media);
        }

        // Deleting the last item of the last page would leave an empty page behind.
        if ($media->isEmpty() && $media->currentPage() > 1) {
            return redirect()->route('admin.media.index', [...$request->query(), 'page' => $media->lastPage()]);
        }

        return view('admin.media.index', [
            'media' => $media,
            'filters' => $filters,
            'totalAssets' => Media::query()->count(),
            'totalBytes' => (int) Media::query()->sum('size_bytes'),
        ]);
    }

    public function store(
        StoreMediaRequest $request,
        MediaStorage $mediaStorage
    ): JsonResponse {
        $file = $request->file('file');

        $width = null;
        $height = null;

        if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());

            if ($dimensions === false) {
                throw ValidationException::withMessages([
                    'file' => 'The image file is invalid or corrupted.',
                ]);
            }

            [$width, $height] = $dimensions;
        }

        $attributes = $mediaStorage->store($file);

        try {
            $media = Media::create([
                'uploaded_by' => $request->user()->id,
                'file_name' => $attributes['file_name'],
                'mime_type' => $attributes['mime_type'],
                'size_bytes' => $attributes['size_bytes'],
                'storage_key' => $attributes['storage_key'],
                'url' => $attributes['url'],
                'width' => $width,
                'height' => $height,
            ]);
        } catch (\Throwable $exception) {
            $mediaStorage->delete($attributes['storage_key']);

            throw $exception;
        }

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'data' => $media,
        ], 201);
    }

    public function destroy(
        Media $media,
        MediaStorage $mediaStorage,
        MediaUsageService $mediaUsageService,
        MediaStorageCleanupService $cleanupService,
        MediaOperationLockService $operationLock
    ): JsonResponse {

        $cleanup = DB::transaction(function () use (
            $media,
            $mediaStorage,
            $mediaUsageService,
            $cleanupService,
            $operationLock
        ) {
            $operationLock->lock();

            $lockedMedia = Media::query()
                ->lockForUpdate()
                ->findOrFail($media->getKey());

            if ($mediaUsageService->isInUse($lockedMedia)) {
                return null;
            }

            $cleanup = $cleanupService->schedule(
                $mediaStorage->diskName(),
                $lockedMedia->storage_key
            );

            $lockedMedia->delete();

            return $cleanup;
        });

        if ($cleanup === null) {
            return response()->json([
                'message' => 'Cannot delete this media because it is in use.',
            ], 409);
        }

        $cleanupService->attempt(
            $cleanup,
            $mediaStorage
        );

        return response()->json([
            'message' => 'Media deleted successfully.',
        ]);
    }

    public function rename(
        RenameMediaRequest $request,
        Media $media,
        MediaOperationLockService $operationLock
    ): JsonResponse {
        $name = trim($request->validated('name'));

        $renamedMedia = DB::transaction(function () use (
            $media,
            $name,
            $operationLock
        ): Media {
            $operationLock->lock();

            $lockedMedia = Media::query()
                ->lockForUpdate()
                ->findOrFail($media->getKey());

            $extension = pathinfo(
                $lockedMedia->file_name,
                PATHINFO_EXTENSION
            );

            $fileName = $extension !== ''
                ? $name.'.'.$extension
                : $name;

            if (mb_strlen($fileName) > 255) {
                throw ValidationException::withMessages([
                    'name' => 'The media name is too long.',
                ]);
            }

            $lockedMedia->update([
                'file_name' => $fileName,
            ]);

            return $lockedMedia->fresh();
        });

        return response()->json([
            'message' => 'Media renamed successfully.',
            'data' => $renamedMedia,
        ]);
    }

    public function replace(
        ReplaceMediaRequest $request,
        Media $media,
        MediaStorage $mediaStorage,
        MediaUsageService $mediaUsageService,
        MediaStorageCleanupService $cleanupService,
        MediaOperationLockService $operationLock
    ): JsonResponse {
        $file = $request->file('file');

        $width = null;
        $height = null;

        if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
            $dimensions = @getimagesize(
                $file->getRealPath()
            );

            if ($dimensions === false) {
                throw ValidationException::withMessages([
                    'file' => 'The image file is invalid or corrupted.',
                ]);
            }

            [$width, $height] = $dimensions;
        }

        $disk = $mediaStorage->diskName();

        $newStorageKey = $mediaStorage->generateStorageKey(
            $file
        );

        /*
         * Create a durable cleanup record before uploading.
         * If the request is interrupted after the upload,
         * the orphaned object can still be removed later.
         */
        $newCleanup = $cleanupService->schedule(
            $disk,
            $newStorageKey,
            now()->addHour()
        );

        try {
            $oldCleanup = DB::transaction(function () use (
                $media,
                $width,
                $height,
                $mediaStorage,
                $cleanupService,
                $disk,
                $newStorageKey,
                $mediaUsageService,
                $operationLock,
                $file
            ) {
                $operationLock->lock();

                $lockedMedia = Media::query()
                    ->lockForUpdate()
                    ->findOrFail($media->getKey());

                if ($mediaUsageService->isUsedInMarkdown($lockedMedia)) {
                    return null;
                }

                if ($mediaUsageService->isUsedAsThumbnail($lockedMedia)) {
                    $extension = strtolower((string) $file->extension());
                    $size = (int) ($file->getSize() ?? 0);

                    if (
                        ! in_array($extension, Post::ALLOWED_IMAGE_MIMES, true)
                        || $size > Post::MAX_THUMBNAIL_KB * 1024
                    ) {
                        throw ValidationException::withMessages([
                            'file' => 'This media is used as a thumbnail, so only JPG, JPEG, PNG and WebP files up to 5 MB are accepted.',
                        ]);
                    }
                }

                $attributes = $mediaStorage->storeAt(
                    $file,
                    $newStorageKey
                );

                $oldStorageKey = $lockedMedia->storage_key;

                $lockedMedia->update([
                    'file_name' => $attributes['file_name'],
                    'mime_type' => $attributes['mime_type'],
                    'size_bytes' => $attributes['size_bytes'],
                    'storage_key' => $attributes['storage_key'],
                    'url' => $attributes['url'],
                    'width' => $width,
                    'height' => $height,
                ]);

                $cleanupService->cancel(
                    $disk,
                    $newStorageKey
                );

                return $cleanupService->schedule(
                    $disk,
                    $oldStorageKey
                );
            });
        } catch (\Throwable $exception) {
            $cleanupService->attempt(
                $newCleanup,
                $mediaStorage
            );

            throw $exception;
        }

        if ($oldCleanup === null) {
            $cleanupService->attempt(
                $newCleanup,
                $mediaStorage
            );

            return response()->json([
                'message' => 'Cannot replace this media because its URL is used in Markdown content.',
            ], 409);
        }

        $cleanupService->attempt(
            $oldCleanup,
            $mediaStorage
        );

        /*
         * The database already points to the new object here.
         * Cleanup failure must not turn a successful replacement
         * into an HTTP error. The durable cleanup record will retry.
         */
        return response()->json([
            'message' => 'Media replaced successfully.',
            'data' => $media->fresh(),
        ]);
    }
}
