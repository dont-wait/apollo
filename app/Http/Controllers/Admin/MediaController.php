<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\MediaStorage;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $media = Media::query()
            ->with('uploader:id,name,email')
            ->latest()
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $media,
            ]);
        }

        return view('admin.media.index', [
            'media' => $media,
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
            $dimensions = getimagesize($file->getRealPath());

            if ($dimensions !== false) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
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
            'message' => 'Upload media thành công.',
            'data' => $media,
        ], 201);
    }
}
