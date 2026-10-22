<?php

namespace App\Http\Controllers\Admin;

use App\AiWeekly\DraftPostCreator;
use App\AiWeekly\RunCoordinator;
use App\AiWeekly\SectionEditor;
use App\Http\Controllers\Controller;
use App\Jobs\AiWeekly\GenerateWeeklyReview;
use App\Models\AutomationRun;
use App\Models\WeeklyReview;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeeklyReviewController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $reviews = WeeklyReview::query()->withCount('sourceArticles')->orderByDesc('week_start')->paginate(20);

        return $request->expectsJson() ? response()->json($reviews) : view('admin.ai-weekly.reviews', compact('reviews'));
    }

    public function show(Request $request, WeeklyReview $review): JsonResponse|View
    {
        $review->load(['sourceArticles.source', 'sourceArticles.analyses' => fn ($query) => $query->where('is_current', true), 'generationRun', 'post']);
        $candidateRun = AutomationRun::query()->where('run_type', 'GENERATE_REVIEW')->where('status', 'SUCCESS')->where('payload->review_id', $review->id)->orderByDesc('id')->first();
        if ($request->expectsJson()) {
            return response()->json(['data' => $review, 'handoff' => $this->handoff($review), 'candidate_run' => $candidateRun]);
        }

        $previewHtml = Str::markdown($review->markdown_content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $previewHtml = preg_replace('/<img\b[^>]*>/i', '', $previewHtml);

        return view('admin.ai-weekly.review', compact('review', 'candidateRun', 'previewHtml'));
    }

    public function generate(Request $request, RunCoordinator $coordinator): JsonResponse
    {
        $data = $request->validate(['week_start' => ['required', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($data['week_start'], config('ai_weekly.timezone'));
        if ($start->dayOfWeek !== CarbonImmutable::MONDAY) {
            return response()->json(['message' => 'week_start phải là thứ Hai.'], 422);
        }

        $run = $coordinator->generate($data['week_start'], 'MANUAL', $request->user()->id);

        return response()->json(['run_id' => $run->id], 202);
    }

    public function update(Request $request, WeeklyReview $review, SectionEditor $sections): JsonResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'], 'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'], 'markdown_content' => ['required', 'string', 'max:50000'],
        ]);
        $data['revision'] = (int) $data['revision'];

        return DB::transaction(function () use ($review, $data, $sections): JsonResponse {
            $locked = WeeklyReview::query()->lockForUpdate()->findOrFail($review->id);
            if (! in_array($locked->status, ['DRAFT', 'REVIEW'], true) || $locked->revision !== $data['revision']) {
                return response()->json(['message' => 'Review đã thay đổi hoặc không thể sửa.'], 409);
            }

            try {
                $content = $sections->synchronize($locked, strip_tags($data['markdown_content']));
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['markdown_content' => $exception->getMessage()]);
            }
            $locked->update([
                'title' => $data['title'], 'excerpt' => $data['excerpt'] ?? null,
                'markdown_content' => $content['markdown'], 'revision' => $locked->revision + 1,
                'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
            ]);
            $locked->sourceArticles()->sync($content['citations']);

            return response()->json(['data' => $locked]);
        });
    }

    public function regenerate(Request $request, WeeklyReview $review, SectionEditor $sections): JsonResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'section' => ['nullable', Rule::in(array_merge(['full'], array_keys(SectionEditor::HEADINGS)))],
        ]);
        $data['revision'] = (int) $data['revision'];
        if (! in_array($review->status, ['DRAFT', 'REVIEW'], true) || $review->revision !== $data['revision']) {
            return response()->json(['message' => 'Review đã thay đổi hoặc không thể sửa.'], 409);
        }
        $section = ($data['section'] ?? 'full') === 'full' ? null : $data['section'];
        if ($section && ! $sections->validStructure($review->markdown_content)) {
            return response()->json(['message' => 'Các heading đã thay đổi; hãy regenerate toàn bài.'], 409);
        }

        $run = AutomationRun::create([
            'run_type' => 'GENERATE_REVIEW', 'status' => 'PENDING', 'trigger_type' => 'MANUAL',
            'triggered_by' => $request->user()->id, 'idempotency_key' => (string) Str::uuid(),
            'payload' => ['week_start' => $review->week_start->toDateString(), 'snapshot' => $review->generationRun?->payload['snapshot'] ?? null,
                'mode' => 'candidate', 'section' => $section, 'review_id' => $review->id, 'revision' => $review->revision],
            'attempt' => 1, 'max_attempts' => config('ai_weekly.max_attempts'), 'created_at' => now(),
        ]);
        GenerateWeeklyReview::dispatch($run->id);

        return response()->json(['run_id' => $run->id], 202);
    }

    public function applyCandidate(Request $request, WeeklyReview $review, SectionEditor $sections): JsonResponse
    {
        $data = $request->validate(['run_id' => ['required', 'integer', 'exists:automation_runs,id'], 'revision' => ['required', 'integer', 'min:1']]);
        $data['revision'] = (int) $data['revision'];

        return DB::transaction(function () use ($review, $data, $sections): JsonResponse {
            $locked = WeeklyReview::query()->lockForUpdate()->findOrFail($review->id);
            $run = AutomationRun::findOrFail($data['run_id']);
            $candidate = $run->payload['candidate'] ?? null;
            if (! in_array($locked->status, ['DRAFT', 'REVIEW'], true) || $locked->revision !== $data['revision'] || $run->status !== 'SUCCESS' || ($run->payload['review_id'] ?? null) !== $locked->id || ($candidate['revision'] ?? null) !== $data['revision']) {
                return response()->json(['message' => 'Candidate không còn hợp lệ.'], 409);
            }

            $draft = $candidate['draft'];
            if ($candidate['section'] ?? null) {
                try {
                    $replaced = $sections->replace($locked, $candidate['section'], $draft['markdown_content']);
                } catch (\RuntimeException $exception) {
                    return response()->json(['message' => $exception->getMessage()], 409);
                }
                $draft['title'] = $locked->title;
                $draft['excerpt'] = $locked->excerpt;
                $draft['markdown_content'] = $replaced['markdown'];
                $draft['quality_warning'] = $locked->quality_warning;
                $draft['suggested_tags'] = $locked->suggested_tags;
                $citations = collect($replaced['citation_ids'])->mapWithKeys(fn (int $id, int $index): array => [$id => ['citation_order' => $index + 1, 'is_representative' => true]])->all();
            } else {
                $citations = collect($candidate['citations'])->mapWithKeys(fn (array $citation): array => [$citation['article_id'] => ['citation_order' => $citation['citation_order'], 'is_representative' => true]])->all();
            }
            $locked->update([
                'title' => $draft['title'], 'excerpt' => $draft['excerpt'], 'markdown_content' => $draft['markdown_content'],
                'quality_warning' => $draft['quality_warning'], 'suggested_tags' => $draft['suggested_tags'] ?? [],
                'revision' => $locked->revision + 1, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
                'generation_run_id' => $run->id, 'model_name' => $run->payload['snapshot']['model'] ?? null, 'generated_at' => now(),
            ]);
            $locked->sourceArticles()->sync($citations);

            return response()->json(['data' => $locked->fresh()]);
        });
    }

    public function convert(Request $request, WeeklyReview $review, DraftPostCreator $drafts): JsonResponse
    {
        try {
            $post = $drafts->create($review, $request->user());
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $post], $post->wasRecentlyCreated ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function handoff(WeeklyReview $review): array
    {
        return [
            'weekly_review_id' => $review->id, 'title' => $review->title, 'excerpt' => $review->excerpt,
            'markdown_content' => $review->markdown_content,
            'sources' => $review->sourceArticles->map(fn ($article): array => [
                'article_id' => $article->id, 'title' => $article->title, 'url' => $article->url,
                'source_name' => $article->source->name, 'citation_order' => $article->pivot->citation_order,
            ])->all(),
            'suggested_category_id' => $review->suggested_category_id, 'suggested_tags' => $review->suggested_tags ?? [],
        ];
    }
}
