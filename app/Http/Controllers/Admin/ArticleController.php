<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:DRAFT,REVIEW,SCHEDULED,PUBLISHED,ARCHIVED'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $articles = Post::query()
            ->with(['category:id,name', 'thumbnail:id,url'])
            ->when($filters['category_id'] ?? null, function (Builder $query, int $categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.create', [
            'article' => null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $thumbnail = $data['thumbnail'] ?? null;
        unset($data['tags'], $data['thumbnail']);

        $storedThumbnailPath = null;

        try {
            $article = DB::transaction(function () use ($data, $tagIds, $thumbnail, $request, &$storedThumbnailPath): Post {
                if ($thumbnail instanceof UploadedFile) {
                    $media = $this->storeThumbnail($thumbnail, $request->user()->id, $storedThumbnailPath);
                    $data['thumbnail_media_id'] = $media->id;
                }

                $article = Post::create([
                    ...$data,
                    'author_id' => $request->user()->id,
                    'published_at' => $data['status'] === 'PUBLISHED' ? now() : null,
                ]);
                $article->tags()->sync($tagIds);

                return $article;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredThumbnail($storedThumbnailPath);

            throw $exception;
        }

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('status', 'Bài viết đã được tạo.');
    }

    public function edit(Post $article): View
    {
        $article->load(['tags:id,name', 'thumbnail:id,url']);

        return view('admin.articles.edit', [
            'article' => $article,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateArticleRequest $request, Post $article): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $thumbnail = $data['thumbnail'] ?? null;
        unset($data['tags'], $data['thumbnail']);

        $previousMedia = $article->thumbnail;
        $storedThumbnailPath = null;

        try {
            DB::transaction(function () use ($article, $data, $tagIds, $thumbnail, $request, &$storedThumbnailPath): void {
                if ($thumbnail instanceof UploadedFile) {
                    $media = $this->storeThumbnail($thumbnail, $request->user()->id, $storedThumbnailPath);
                    $data['thumbnail_media_id'] = $media->id;
                }

                $data['published_at'] = $data['status'] === 'PUBLISHED'
                    ? ($article->published_at ?? now())
                    : null;

                $article->update($data);
                $article->tags()->sync($tagIds);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredThumbnail($storedThumbnailPath);

            throw $exception;
        }

        if ($storedThumbnailPath !== null && $previousMedia !== null) {
            $mediaIsStillInUse = Post::withTrashed()
                ->where('thumbnail_media_id', $previousMedia->id)
                ->whereKeyNot($article->id)
                ->exists();

            if (! $mediaIsStillInUse) {
                if (! Storage::disk('public')->delete($previousMedia->storage_key)) {
                    throw new RuntimeException('Không thể xóa ảnh thumbnail cũ khỏi public storage.');
                }

                $previousMedia->delete();
            }
        }

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('status', 'Bài viết đã được cập nhật.');
    }

    public function destroy(Post $article): RedirectResponse
    {
        $article->delete();

        return redirect()
            ->route('admin.articles.index')
            ->with('status', 'Bài viết đã được chuyển vào thùng rác.');
    }

    private function storeThumbnail(UploadedFile $thumbnail, int $uploadedBy, ?string &$storedPath): Media
    {
        $path = $thumbnail->storePublicly('articles', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('Không thể lưu ảnh thumbnail vào public storage.');
        }

        $storedPath = $path;

        return Media::create([
            'uploaded_by' => $uploadedBy,
            'file_name' => basename($path),
            'mime_type' => $thumbnail->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $thumbnail->getSize(),
            'storage_key' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    private function deleteStoredThumbnail(?string $storedPath): void
    {
        if ($storedPath !== null) {
            Storage::disk('public')->delete($storedPath);
        }
    }
}
