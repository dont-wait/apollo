<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportToxicWordsRequest;
use App\Http\Requests\Admin\StoreToxicWordRequest;
use App\Http\Requests\Admin\UpdateToxicWordRequest;
use App\Http\Requests\Admin\UpdateToxicWordStatusRequest;
use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordImporter;
use App\Support\Moderation\ToxicWordNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        try {
            $word = ToxicWord::query()->create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['term' => 'Từ này đã có trong từ điển.']);
        }

        return $this->respond($request, $word, 'Đã thêm từ vào từ điển.', 201);
    }

    public function import(ImportToxicWordsRequest $request, ToxicWordImporter $importer): RedirectResponse
    {
        try {
            $result = $importer->import($request->file('file'), $request->user()->id);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['file' => 'Không đọc được file. Hãy dùng file mẫu.']);
        }

        $message = "Đã import {$result['created']} từ mới, bỏ qua {$result['skipped']} từ đã có";

        if ($result['errors'] !== []) {
            $shown = array_slice($result['errors'], 0, 10);
            $more = count($result['errors']) - count($shown);

            return back()
                ->with('status', $message.', '.count($result['errors']).' dòng lỗi.')
                ->with('import_errors', [...$shown, ...($more > 0 ? ["... và {$more} lỗi khác."] : [])]);
        }

        return back()->with('status', $message.'.');
    }

    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(ToxicWordImporter::COLUMNS));
            $writer->addRow(Row::fromValues(['# ví dụ: từ cần chặn', 'post,comment', 'WORD', 0, 1, 'Dòng bắt đầu bằng # là ghi chú, sẽ không được import']));
            $writer->addRow(Row::fromValues(['# ví dụ: cụm từ cần chặn', '', 'LOOSE', 1, 1, 'Để trống scopes = áp dụng tất cả']));
            $writer->close();
        }, 'toxic-words-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function update(
        UpdateToxicWordRequest $request,
        ToxicWord $toxicWord,
        ToxicWordNormalizer $normalizer,
    ): JsonResponse|RedirectResponse {
        $data = $request->validated();
        $data['normalized_term'] = $normalizer->normalize($data['term']);
        $data['updated_by'] = $request->user()->id;
        $data['fold_diacritics'] = $request->boolean('fold_diacritics');

        try {
            $toxicWord->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['term' => 'Từ này đã có trong từ điển.']);
        }

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
