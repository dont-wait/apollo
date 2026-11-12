<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookmarkController extends Controller
{
    public function index(Request $request): View
    {
        $posts = $request->user()
            ->bookmarkedPosts()
            ->published()
            ->with(['author:id,name', 'category:id,name', 'tags:id,name', 'thumbnail:id,url'])
            ->withCount('bookmarks')
            ->orderByPivotDesc('created_at')
            ->orderByDesc('posts.id')
            ->paginate(10)
            ->withQueryString();

        return view('bookmarks.index', [
            'posts' => $posts,
            'bookmarkUrls' => $this->bookmarkUrls(),
        ]);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $post): void {
                $lockedPost = Post::query()
                    ->with('category')
                    ->lockForUpdate()
                    ->findOrFail($post->id);

                $this->ensurePublished($lockedPost);
                $request->user()->bookmarkedPosts()->syncWithoutDetachingOrFail([$lockedPost->id]);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request may have created the same bookmark already.
        }

        return $this->bookmarkResponse($request, $post);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $request->user()->bookmarkedPosts()->detachOrFail($post->id);

        return $this->bookmarkResponse($request, $post);
    }

    private function ensurePublished(Post $post): void
    {
        $post->loadMissing('category');

        abort_unless($post->isPublished(), 404);
    }

    private function bookmarkResponse(Request $request, Post $post): JsonResponse
    {
        $post->loadMissing('category');

        [$bookmarked, $bookmarksCount] = DB::transaction(function () use ($request, $post): array {
            return [
                $request->user()->bookmarkedPosts()->whereKey($post->id)->exists(),
                $post->isPublished() ? $post->bookmarks()->count() : 0,
            ];
        });

        return response()->json([
            'bookmarked' => $bookmarked,
            'bookmarks_count' => (int) $bookmarksCount,
        ]);
    }

    /**
     * @return array{store: string, destroy: string}
     */
    private function bookmarkUrls(): array
    {
        return [
            'store' => route('bookmarks.store', ['post' => '__POST__']),
            'destroy' => route('bookmarks.destroy', ['post' => '__POST__']),
        ];
    }
}
