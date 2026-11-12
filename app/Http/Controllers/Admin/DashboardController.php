<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDays(30);

        $postCounts = Post::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as draft', [PostStatus::Draft->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as pending', [PostStatus::Review->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as scheduled', [PostStatus::Scheduled->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as published', [PostStatus::Published->value])
            ->selectRaw('COALESCE(SUM(view_count), 0) as total_views')
            ->firstOrFail();

        $postStats = [
            'total' => (int) $postCounts->total,
            'draft' => (int) $postCounts->draft,
            'pending' => (int) $postCounts->pending,
            'scheduled' => (int) $postCounts->scheduled,
            'published' => (int) $postCounts->published,
            'total_views' => (int) $postCounts->total_views,
        ];

        $commentCounts = Comment::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as recent', [$periodStart, $periodEnd])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as pending', [Comment::STATUS_PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as hidden', [Comment::STATUS_HIDDEN])
            ->firstOrFail();

        $commentStats = [
            'total' => (int) $commentCounts->total,
            'recent' => (int) $commentCounts->recent,
            'pending' => (int) $commentCounts->pending,
            'hidden' => (int) $commentCounts->hidden,
        ];

        $userCounts = User::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as recent', [$periodStart, $periodEnd])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as active', [User::STATUS_ACTIVE])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as inactive', [User::STATUS_INACTIVE])
            ->firstOrFail();

        $userStats = [
            'total' => (int) $userCounts->total,
            'recent' => (int) $userCounts->recent,
            'active' => (int) $userCounts->active,
            'inactive' => (int) $userCounts->inactive,
        ];

        $recentPosts = Post::query()
            ->select(['id', 'title', 'slug', 'excerpt', 'status', 'published_at', 'updated_at', 'view_count', 'category_id', 'author_id'])
            ->with(['category:id,name,status', 'author:id,name'])
            ->withCount('bookmarks')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $topViewedPosts = Post::query()
            ->select(['id', 'title', 'slug', 'view_count'])
            ->orderByDesc('view_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $topBookmarkedPosts = Post::query()
            ->select(['id', 'title', 'slug'])
            ->withCount('bookmarks')
            ->whereHas('bookmarks')
            ->orderByDesc('bookmarks_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $totalBookmarks = DB::table('bookmarks')->count();

        return view('admin.dashboard', compact(
            'commentStats',
            'periodStart',
            'postStats',
            'recentPosts',
            'topBookmarkedPosts',
            'topViewedPosts',
            'totalBookmarks',
            'userStats',
        ));
    }
}
