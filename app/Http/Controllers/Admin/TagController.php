<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Http\Requests\Admin\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = Tag::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $name = $request->input('name');
        $slug = $request->input('slug');

        $existingTag = Tag::query()
            ->where('name', $name)
            ->where('slug', $slug)
            ->first();

        // Nếu trùng hoàn toàn name + slug thì tái sử dụng Tag cũ.
        if ($existingTag) {
            return response()->json([
                'message' => 'Tag đã tồn tại, sử dụng lại tag hiện có.',
                'data' => $existingTag,
                'reused' => true,
            ]);
        }

        $errors = [];

        if (Tag::where('name', $name)->exists()) {
            $errors['name'] = [
                'Tên tag đã được sử dụng.',
            ];
        }

        if (Tag::where('slug', $slug)->exists()) {
            $errors['slug'] = [
                'Slug tag đã được sử dụng.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $tag = Tag::create([
            'name' => $name,
            'slug' => $slug,
        ]);

        return response()->json([
            'message' => 'Tạo tag thành công.',
            'data' => $tag,
            'reused' => false,
        ], 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        $tag->load([
            'posts:id,title,slug,status',
        ]);

        return response()->json([
            'data' => $tag,
        ]);
    }

    public function update(
        UpdateTagRequest $request,
        Tag $tag
    ): JsonResponse {
        $tag->update($request->validated());

        return response()->json([
            'message' => 'Cập nhật tag thành công.',
            'data' => $tag,
        ]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        if ($tag->posts()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa tag vì tag đang được sử dụng bởi bài viết.',
            ], 409);
        }

        $tag->delete();

        return response()->json([
            'message' => 'Xóa tag thành công.',
        ]);
    }
}
