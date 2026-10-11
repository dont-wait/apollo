<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\MediaStorage;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ArticleController extends Controller
{
    public function __construct(private MediaStorage $mediaStorage) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::in(array_keys(PostStatus::filterOptions()))],
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
            'statusFilterOptions' => PostStatus::filterOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.create', [
            'article' => null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => PostStatus::selectableOptions(),
            'maxThumbnailKb' => Post::MAX_THUMBNAIL_KB,
            'allowedImageMimes' => Post::ALLOWED_IMAGE_MIMES,
            'maxExcerptLength' => Post::MAX_EXCERPT_LENGTH,
        ]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $newTagNames = $data['new_tags'] ?? [];
        $thumbnail = $data['thumbnail'] ?? null;
        unset($data['tags'], $data['new_tags'], $data['thumbnail']);

        $storedThumbnailPath = null;

        try {
            $article = DB::transaction(function () use ($data, $tagIds, $newTagNames, $thumbnail, $request, &$storedThumbnailPath): Post {
                if ($thumbnail instanceof UploadedFile) {
                    $media = $this->storeThumbnail($thumbnail, $request->user()->id, $storedThumbnailPath);
                    $data['thumbnail_media_id'] = $media->id;
                }

                $article = Post::create([
                    ...$data,
                    'author_id' => $request->user()->id,
                    'published_at' => $data['status'] === 'PUBLISHED' ? now() : null,
                ]);
                $article->tags()->sync($this->resolveTagIds($tagIds, $newTagNames));

                return $article;
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredThumbnail($storedThumbnailPath);
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
            'statusOptions' => PostStatus::selectableOptions(),
            'maxThumbnailKb' => Post::MAX_THUMBNAIL_KB,
            'allowedImageMimes' => Post::ALLOWED_IMAGE_MIMES,
            'maxExcerptLength' => Post::MAX_EXCERPT_LENGTH,
        ]);
    }

    public function update(UpdateArticleRequest $request, Post $article): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $newTagNames = $data['new_tags'] ?? [];
        $thumbnail = $data['thumbnail'] ?? null;
        unset($data['tags'], $data['new_tags'], $data['thumbnail']);

        $previousMedia = $article->thumbnail;
        $storedThumbnailPath = null;

        try {
            DB::transaction(function () use ($article, $data, $tagIds, $newTagNames, $thumbnail, $request, &$storedThumbnailPath): void {
                if ($thumbnail instanceof UploadedFile) {
                    $media = $this->storeThumbnail($thumbnail, $request->user()->id, $storedThumbnailPath);
                    $data['thumbnail_media_id'] = $media->id;
                }

                $data['published_at'] = $data['status'] === 'PUBLISHED'
                    ? ($article->published_at ?? now())
                    : null;

                $article->update($data);
                $article->tags()->sync($this->resolveTagIds($tagIds, $newTagNames));
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredThumbnail($storedThumbnailPath);
            throw $exception;
        }

        if ($storedThumbnailPath !== null && $previousMedia !== null) {
            $mediaIsStillInUse = Post::withTrashed()
                ->where('thumbnail_media_id', $previousMedia->id)
                ->whereKeyNot($article->id)
                ->exists();

            if (! $mediaIsStillInUse) {
                $this->mediaStorage->delete($previousMedia->storage_key);
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
        $attributes = $this->mediaStorage->store($thumbnail, 'articles');
        $storedPath = $attributes['storage_key'];

        return Media::create([...$attributes, 'uploaded_by' => $uploadedBy]);
    }

    private function cleanupStoredThumbnail(?string $storedPath): void
    {
        if ($storedPath === null) {
            return;
        }

        try {
            $this->mediaStorage->delete($storedPath);
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }
    }

    /**
     * @param  array<int, int|string>  $tagIds
     * @param  array<int, string>  $newTagNames
     * @return list<int>
     */
    private function resolveTagIds(array $tagIds, array $newTagNames): array
    {
        foreach ($newTagNames as $newTagName) {
            $name = trim($newTagName);
            $slug = Str::slug($name);

            $tag = Tag::query()->where('name', $name)->first()
                ?? Tag::query()->where('slug', $slug)->first();

            if ($tag === null) {
                $tag = Tag::create([
                    'name' => $name,
                    'slug' => $slug,
                ]);
            }

            $tagIds[] = $tag->id;
        }

        return array_values(array_unique(array_map('intval', $tagIds)));
    }
}
