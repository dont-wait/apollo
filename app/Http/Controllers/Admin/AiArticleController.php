<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiArticleController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        if ($request->expectsJson() && $request->boolean('picker')) {
            return $this->picker($request);
        }

        $articles = AiArticle::query()->with('source:id,name')->orderByDesc('collected_at')->orderByDesc('id')->paginate(30);

        $limit = (int) config('ai_weekly.analyze_batch_size');
        $maxSelection = $limit > 0 ? $limit : null;

        return $request->expectsJson() ? response()->json($articles) : view('admin.ai-weekly.articles', compact('articles', 'maxSelection'));
    }

    public function show(Request $request, AiArticle $article): JsonResponse|View
    {
        $article->load(['source:id,name,site_url', 'analyses', 'topics']);

        return $request->expectsJson() ? response()->json(['data' => $article]) : view('admin.ai-weekly.article', compact('article'));
    }

    /** Lightweight, searchable feed for the "choose articles to analyze" dialog (never loads article bodies). */
    private function picker(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'in:pending,all'],
        ]);

        $page = AiArticle::query()
            ->with('source:id,name')
            ->select(['id', 'source_id', 'title', 'status', 'duplicate_of', 'published_at', 'collected_at'])
            ->selectRaw("(COALESCE(raw_summary, '') <> '' OR COALESCE(raw_content, '') <> '') AS has_content")
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when(($filters['filter'] ?? 'pending') === 'pending', function (Builder $query): void {
                $query->whereNull('duplicate_of')
                    ->where(fn ($content) => $content->where(fn ($summary) => $summary->whereNotNull('raw_summary')->where('raw_summary', '!=', ''))
                        ->orWhere(fn ($body) => $body->whereNotNull('raw_content')->where('raw_content', '!=', '')))
                    ->where(fn ($pending) => $pending->whereNotIn('status', ['ANALYZED'])
                        ->orWhereDoesntHave('analyses', fn ($analysis) => $analysis->where('is_current', true)));
            })
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AiArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'source' => $article->source?->name,
                'status' => $article->duplicate_of ? 'DUPLICATE' : $article->status,
                'date' => ($article->published_at ?? $article->collected_at)?->format('d/m/Y'),
                'selectable' => $article->duplicate_of === null && (bool) $article->has_content,
                'reason' => $article->duplicate_of !== null ? 'Bài trùng' : ((bool) $article->has_content ? null : 'Không có nội dung'),
            ])->all(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }
}
