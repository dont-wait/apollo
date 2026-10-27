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
use App\Services\MediaOperationLockService;
use App\Services\MediaStorageCleanupService;
use App\Services\MediaUsageService;
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
    public function __construct(
        private MediaStorage $mediaStorage,
        private MediaOperationLockService $mediaOperationLock,
        private MediaStorageCleanupService $cleanupService,
        private MediaUsageService $mediaUsageService
    ) {}

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
                $this->mediaOperationLock->lock();

                if ($thumbnail instanceof UploadedFile) {
                    $media = $this->storeThumbnail($thumbnail, $request->user()->id, $storedThumbnailPath);
                    $data['thumbnail_media_id'] = $media->id;
                }

                $data = $this->normalizePublicationState($data);

                $article = Post::create([
                    ...$data,
                    'author_id' => $request->user()->id,
                ]);
                $article->tags()->sync($this->resolveTagIds($tagIds, $newTagNames));

                return $article;
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredThumbnail($storedThumbnailPath);
            throw $exception;
        }

        return redirect()
            ->route('admin.articles.index')
            ->with('status', 'Bài viết đã được tạo thành công');
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

        $storedThumbnailPath = null;
        $thumbnailCleanup = null;

        try {
            DB::transaction(function () use ($article, $data, $tagIds, $newTagNames, $thumbnail, $request, &$storedThumbnailPath, &$thumbnailCleanup): void {
                $this->mediaOperationLock->lock();

                $lockedArticle = Post::query()
                    ->lockForUpdate()
                    ->findOrFail($article->getKey());

                $previousMedia = $lockedArticle->thumbnail_media_id === null
                    ? null
                    : Media::query()
                        ->lockForUpdate()
                        ->find($lockedArticle->thumbnail_media_id);

                if ($thumbnail instanceof UploadedFile) {
                    $media = $this->storeThumbnail($thumbnail, $request->user()->id, $storedThumbnailPath);
                    $data['thumbnail_media_id'] = $media->id;
                }

                $data = $this->normalizePublicationState($data, $lockedArticle);

                $lockedArticle->update($data);
                $lockedArticle->tags()->sync($this->resolveTagIds($tagIds, $newTagNames));

                if ($storedThumbnailPath !== null && $previousMedia !== null && ! $this->mediaUsageService->isInUse($previousMedia)) {
                    $thumbnailCleanup = $this->cleanupService->schedule(
                        $this->mediaStorage->diskName(),
                        $previousMedia->storage_key
                    );

                    $previousMedia->delete();
                }
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredThumbnail($storedThumbnailPath);
            throw $exception;
        }

        if ($thumbnailCleanup !== null) {
            $this->cleanupService->attempt(
                $thumbnailCleanup,
                $this->mediaStorage
            );
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePublicationState(array $data, ?Post $current = null): array
    {
        $status = PostStatus::from($data['status']);

        if ($status === PostStatus::Published) {
            $data['published_at'] = $current?->status === PostStatus::Published->value
                ? ($current->published_at ?? now())
                : now();
            $data['scheduled_at'] = null;

            return $data;
        }

        if ($status === PostStatus::Scheduled) {
            $data['published_at'] = null;

            return $data;
        }

        if ($status === PostStatus::Archived) {
            $data['published_at'] = $current?->published_at;
            $data['scheduled_at'] = null;

            return $data;
        }

        $data['published_at'] = null;
        $data['scheduled_at'] = null;

        return $data;
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
