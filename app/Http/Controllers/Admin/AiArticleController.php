<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiArticleController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $articles = AiArticle::query()->with('source:id,name')->orderByDesc('collected_at')->orderByDesc('id')->paginate(30);

        return $request->expectsJson() ? response()->json($articles) : view('admin.ai-weekly.articles', compact('articles'));
    }

    public function show(Request $request, AiArticle $article): JsonResponse|View
    {
        $article->load(['source:id,name,site_url', 'analyses', 'topics']);

        return $request->expectsJson() ? response()->json(['data' => $article]) : view('admin.ai-weekly.article', compact('article'));
    }
}
