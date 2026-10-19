<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->paginate(10)
            ->withQueryString();

        return view('bookmarks.index', [
            'posts' => $posts,
            'bookmarkUrls' => $this->bookmarkUrls(),
        ]);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        $this->ensurePublished($post);

        try {
            $request->user()->bookmarkedPosts()->syncWithoutDetachingOrFail([$post->id]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request may have created the same bookmark already.
        }

        return $this->bookmarkResponse($request, $post);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->ensurePublished($post);
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
        $post->loadCount('bookmarks');

        return response()->json([
            'bookmarked' => $request->user()->bookmarkedPosts()->whereKey($post->id)->exists(),
            'bookmarks_count' => (int) $post->bookmarks_count,
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
