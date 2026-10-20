<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiSourceRequest;
use App\Http\Requests\Admin\UpdateAiSourceRequest;
use App\Models\AiSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSourceController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $sources = AiSource::query()->withCount('articles')->orderByDesc('priority')->orderBy('id')->paginate(20);

        return $request->expectsJson() ? response()->json($sources) : view('admin.ai-weekly.sources', compact('sources'));
    }

    public function store(StoreAiSourceRequest $request): JsonResponse
    {
        $source = AiSource::create($request->validated());

        return response()->json(['data' => $source], 201);
    }

    public function update(UpdateAiSourceRequest $request, AiSource $source): JsonResponse
    {
        $source->update($request->validated());

        return response()->json(['data' => $source->fresh()]);
    }
}
