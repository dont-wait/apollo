<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\AiSource;
use App\Models\AutomationRun;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Contracts\View\View;

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
            ->selectRaw('COUNT(CASE WHEN status != ? THEN 1 END) as total', [Comment::STATUS_DELETED])
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
            ->select(['id', 'title', 'slug', 'excerpt', 'status', 'published_at', 'updated_at', 'view_count', 'like_count', 'category_id', 'author_id'])
            ->with(['category:id,name,status', 'author:id,name'])
            ->withCount('bookmarks')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $topViewedPosts = Post::query()
            ->select(['id', 'title', 'slug', 'view_count', 'like_count', 'category_id'])
            ->with('category:id,name')
            ->orderByDesc('view_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $topBookmarkedPosts = Post::query()
            ->select(['id', 'title', 'slug'])
            ->withCount('bookmarks')
            ->having('bookmarks_count', '>', 0)
            ->orderByDesc('bookmarks_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $totalBookmarks = Bookmark::query()->count();
        $engagementRate = $postStats['total_views'] > 0
            ? round(($commentStats['recent'] / $postStats['total_views']) * 100, 1)
            : 0;

        $latestWeeklyReview = WeeklyReview::query()
            ->select(['id', 'title', 'excerpt', 'status', 'generated_at', 'created_at'])
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        $pipelineSources = AiSource::query()
            ->select(['id', 'name', 'source_type', 'status', 'last_fetched_at', 'priority'])
            ->withCount('articles')
            ->where('status', 'ACTIVE')
            ->orderBy('priority')
            ->orderBy('name')
            ->limit(4)
            ->get();

        $latestPipelineRun = AutomationRun::query()
            ->select(['id', 'run_type', 'status', 'created_at', 'finished_at', 'error_message'])
            ->whereIn('run_type', ['COLLECT', 'ANALYZE', 'GENERATE_REVIEW'])
            ->orderByDesc('created_at')
            ->first();

        $failedPipelineRuns = AutomationRun::query()
            ->whereIn('run_type', ['COLLECT', 'ANALYZE', 'GENERATE_REVIEW'])
            ->where('status', 'FAILED')
            ->where('created_at', '>=', $periodStart)
            ->count();

        return view('admin.dashboard', compact(
            'commentStats',
            'engagementRate',
            'failedPipelineRuns',
            'latestPipelineRun',
            'latestWeeklyReview',
            'periodStart',
            'pipelineSources',
            'postStats',
            'recentPosts',
            'topBookmarkedPosts',
            'topViewedPosts',
            'totalBookmarks',
            'userStats',
        ));
    }
}
