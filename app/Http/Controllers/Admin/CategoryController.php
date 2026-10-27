<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryStatusRequest;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $categories = Category::query()
            ->with('parent')
            ->withCount(['children', 'posts'])
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $categories,
            ]);
        }

        $tags = Tag::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return view('admin.categories.index', [
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'parent_id' => $request->input('parent_id'),
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'status' => $request->input('status') ?? 'ACTIVE',
        ]);

        return response()->json([
            'message' => 'Tạo category thành công.',
            'data' => $category,
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load([
            'parent:id,name',
            'children:id,parent_id,name,slug,status',
        ]);

        return response()->json([
            'data' => $category,
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $category->update($request->validated());

        return response()->json([
            'message' => 'Cập nhật category thành công.',
            'data' => $category->fresh(),
        ]);
    }

    public function updateStatus(
        UpdateCategoryStatusRequest $request,
        Category $category
    ): JsonResponse {
        $category->update([
            'status' => $request->validated()['status'],
        ]);

        return response()->json([
            'message' => 'Cập nhật trạng thái category thành công.',
            'data' => $category->fresh(),
        ]);
    }
}
