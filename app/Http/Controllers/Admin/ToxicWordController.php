<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreToxicWordRequest;
use App\Http\Requests\Admin\UpdateToxicWordRequest;
use App\Http\Requests\Admin\UpdateToxicWordStatusRequest;
use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ToxicWordController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(config('toxic_words.scopes'))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $words = ToxicWord::query()
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('term', 'like', '%'.$q.'%'))
            ->when($filters['scope'] ?? null, fn ($query, string $scope) => $query->whereJsonContains('scopes', $scope))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('term')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $words]);
        }

        return view('admin.toxic-words.index', [
            'words' => $words,
            'filters' => $filters,
            'scopes' => config('toxic_words.scopes'),
            'matchModes' => ToxicWord::MATCH_MODES,
        ]);
    }

    public function store(
        StoreToxicWordRequest $request,
        ToxicWordNormalizer $normalizer,
    ): JsonResponse|RedirectResponse {
        $data = $request->validated();
        $data['normalized_term'] = $normalizer->normalize($data['term']);
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['fold_diacritics'] = $request->boolean('fold_diacritics');

        $word = ToxicWord::query()->create($data);

        return $this->respond($request, $word, 'Đã thêm từ vào từ điển.', 201);
    }

    public function update(
        UpdateToxicWordRequest $request,
        ToxicWord $toxicWord,
        ToxicWordNormalizer $normalizer,
    ): JsonResponse|RedirectResponse {
        $data = $request->validated();
        $data['normalized_term'] = $normalizer->normalize($data['term']);
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active');
        $data['fold_diacritics'] = $request->boolean('fold_diacritics');

        $toxicWord->update($data);

        return $this->respond($request, $toxicWord->fresh(), 'Đã cập nhật từ trong từ điển.');
    }

    public function updateStatus(
        UpdateToxicWordStatusRequest $request,
        ToxicWord $toxicWord,
    ): JsonResponse|RedirectResponse {
        $toxicWord->update([
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);

        return $this->respond(
            $request,
            $toxicWord->fresh(),
            $toxicWord->is_active ? 'Đã bật từ trong từ điển.' : 'Đã tắt từ trong từ điển.',
        );
    }

    public function destroy(Request $request, ToxicWord $toxicWord): JsonResponse|RedirectResponse
    {
        $toxicWord->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã xóa từ khỏi từ điển.']);
        }

        return back()->with('status', 'Đã xóa từ khỏi từ điển.');
    }

    private function respond(
        Request $request,
        ToxicWord $word,
        string $message,
        int $status = 200,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $word], $status);
        }

        return back()->with('status', $message);
    }
}
