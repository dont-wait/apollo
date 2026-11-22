<?php

namespace App\Http\Controllers\Admin;

use App\AiWeekly\RunCoordinator;
use App\AiWeekly\RunDispatchBusyException;
use App\Http\Controllers\Controller;
use App\Models\AiArticle;
use App\Models\AiSource;
use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AutomationRunController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $filter = $request->validate([
            'monitor' => ['sometimes', 'boolean'],
            'run_type' => ['nullable', 'in:COLLECT,ANALYZE,GENERATE_REVIEW'],
            'status' => ['nullable', 'in:PENDING,RUNNING,PAUSED,SUCCESS,PARTIAL,FAILED,CANCELLED'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ]);
        unset($filter['monitor']);
        $compact = ! $request->expectsJson() || $request->boolean('monitor');
        $runsQuery = $this->runQuery($compact)
            ->when(! empty($filter['run_type']), fn ($query) => $query->where('run_type', $filter['run_type']))
            ->when(! empty($filter['status']), fn ($query) => $query->where('status', $filter['status']))
            ->when(! empty($filter['created_from']), fn ($query) => $query->whereDate('created_at', '>=', $filter['created_from']))
            ->when(! empty($filter['created_to']), fn ($query) => $query->whereDate('created_at', '<=', $filter['created_to']))
            ->orderByDesc('created_at')->orderByDesc('id');
        $runs = $runsQuery->paginate(30);
        if ($compact && $runs->currentPage() > $runs->lastPage()) {
            $runs = $runsQuery->paginate(30, ['*'], 'page', $runs->lastPage());
        }
        $runs->appends($request->except(['page', 'monitor']));

        $monitorRuns = $this->runQuery()->whereIn('status', ['PENDING', 'RUNNING', 'PAUSED'])->orderByDesc('id')->get()
            ->concat($this->runQuery()->whereNotIn('status', ['PENDING', 'RUNNING', 'PAUSED'])->orderByDesc('id')->limit(5)->get())
            ->unique('id')->map(fn (AutomationRun $run): array => $this->runState($run))->values()->all();

        $listing = [
            'records' => $runs->getCollection()->map(fn (AutomationRun $run): array => $this->runState($run))->all(),
            'total' => $runs->total(), 'page' => $runs->currentPage(),
            'per_page' => $runs->perPage(), 'last_page' => $runs->lastPage(), 'page_url' => $runs->url(1),
            'filtered' => count(array_filter($filter, fn (mixed $value): bool => $value !== null && $value !== '')) > 0,
        ];

        if ($request->expectsJson() && $request->boolean('monitor')) {
            return response()->json(['current_runs' => $monitorRuns, 'listing' => $listing]);
        }
        $pollUrl = route('admin.automation-runs.index', array_merge($request->query(), ['monitor' => 1]));

        return $request->expectsJson()
            ? response()->json($runs->toArray() + ['current_runs' => $monitorRuns, 'listing' => $listing])
            : view('admin.ai-weekly.runs', [
                'runs' => $runs, 'monitorRuns' => $monitorRuns, 'listing' => $listing, 'pollUrl' => $pollUrl,
                'collectSources' => AiSource::query()->where('status', 'ACTIVE')->whereIn('source_type', ['RSS', 'API'])->orderBy('name')->get(['id', 'name', 'source_type']),
                'defaultMaxItems' => (int) config('ai_weekly.max_items_per_source'),
            ]);
    }

    public function show(Request $request, AutomationRun $run): JsonResponse|View
    {
        $monitorRuns = [$this->runState($run, includePayload: true)];

        return $request->expectsJson()
            ? response()->json(['data' => $run, 'state' => $monitorRuns[0]])
            : view('admin.ai-weekly.run', compact('run', 'monitorRuns'));
    }

    public function collect(Request $request, RunCoordinator $coordinator): JsonResponse
    {
        $data = $request->validate([
            'max_items' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'source_ids' => ['nullable', 'array', 'min:1'],
            'source_ids.*' => ['integer', 'distinct', 'exists:ai_sources,id'],
        ]);

        try {
            $run = $coordinator->collect('MANUAL', $request->user()->id, isset($data['max_items']) ? (int) $data['max_items'] : null, array_map('intval', $data['source_ids'] ?? []));
        } catch (RunDispatchBusyException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['run_id' => $run->id, 'reused' => ! $run->wasRecentlyCreated, 'state' => $this->runState($run)], 202);
    }

    public function analyze(Request $request, RunCoordinator $coordinator): JsonResponse
    {
        $limit = (int) config('ai_weekly.analyze_batch_size');
        $data = $request->validate([
            'article_id' => ['nullable', 'integer', 'exists:ai_articles,id', 'prohibits:article_ids'],
            'article_ids' => ['nullable', 'array', 'min:1', ...($limit > 0 ? ['max:'.$limit] : [])],
            'article_ids.*' => ['integer', 'distinct', 'exists:ai_articles,id'],
        ]);
        if (isset($data['article_id']) && AiArticle::query()->whereKey($data['article_id'])->whereNotNull('duplicate_of')->exists()) {
            return response()->json(['message' => 'Không phân tích lại bài trùng.'], 422);
        }
        $articleIds = array_map('intval', $data['article_ids'] ?? []);
        if ($articleIds !== []) {
            $usable = AiArticle::query()->whereIn('id', $articleIds)->whereNull('duplicate_of')
                ->where(fn ($query) => $query->where(fn ($summary) => $summary->whereNotNull('raw_summary')->where('raw_summary', '!=', ''))
                    ->orWhere(fn ($body) => $body->whereNotNull('raw_content')->where('raw_content', '!=', '')))
                ->count();
            if ($usable !== count($articleIds)) {
                return response()->json(['message' => 'Duplicate articles and articles without content cannot be analyzed. Remove them from the selection.'], 422);
            }
        }
        try {
            $run = $coordinator->analyze('MANUAL', $request->user()->id, $data['article_id'] ?? null, $articleIds);
        } catch (RunDispatchBusyException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['run_id' => $run->id, 'reused' => ! $run->wasRecentlyCreated, 'state' => $this->runState($run)], 202);
    }

    public function cancel(AutomationRun $run, RunCoordinator $coordinator): JsonResponse
    {
        return $this->transition($run, $coordinator->cancel($run), 'Job này đã kết thúc nên không thể hủy.');
    }

    public function pause(AutomationRun $run, RunCoordinator $coordinator): JsonResponse
    {
        return $this->transition($run, $coordinator->pause($run), 'Chỉ có thể hoãn job phân tích đang chờ hoặc đang chạy.');
    }

    public function resume(AutomationRun $run, RunCoordinator $coordinator): JsonResponse
    {
        return $this->transition($run, $coordinator->resume($run), 'Chỉ có thể tiếp tục job phân tích đang bị hoãn.');
    }

    private function transition(AutomationRun $run, bool $changed, string $refusal): JsonResponse
    {
        if (! $changed) {
            return response()->json(['message' => $refusal], 409);
        }

        return response()->json(['run_id' => $run->id, 'reused' => true, 'state' => $this->runState($run->fresh(), includePayload: true)]);
    }

    public function retry(AutomationRun $run, RunCoordinator $coordinator): JsonResponse
    {
        if (! $coordinator->retry($run)) {
            return response()->json(['message' => 'Run không thể retry.'], 409);
        }

        return response()->json(['run_id' => $run->id, 'state' => $this->runState($run->fresh(), includePayload: true)], 202);
    }

    /**
     * Project only progress fields so polling never transfers or decodes analysis results and review snapshots.
     * Analysis jobs record only completed items in article_results and maintain the failed counter incrementally.
     */
    private function runQuery(bool $compact = true): Builder
    {
        $query = AutomationRun::query();
        if (! $compact) {
            return $query;
        }

        return $query->select(['id', 'run_type', 'status', 'attempt', 'max_attempts', 'created_at', 'started_at', 'finished_at', 'error_message'])
            ->selectRaw(<<<'SQL'
                JSON_OBJECT(
                    'sources', CASE WHEN run_type = 'COLLECT' THEN JSON_EXTRACT(payload, '$.sources') ELSE NULL END,
                    'total', JSON_EXTRACT(payload, '$.total'),
                    'current_item', JSON_EXTRACT(payload, '$.current_item'),
                    'stage', JSON_EXTRACT(payload, '$.stage')
                ) AS payload,
                CASE WHEN run_type = 'ANALYZE' THEN COALESCE(JSON_LENGTH(payload, '$.article_results'), 0) END AS progress_completed,
                CASE WHEN run_type = 'ANALYZE' THEN JSON_EXTRACT(payload, '$.failed') END AS progress_failed
                SQL);
    }

    /** @return array<string, mixed> */
    private function runState(AutomationRun $run, bool $includePayload = false): array
    {
        return [
            'id' => $run->id, 'run_type' => $run->run_type, 'status' => $run->status,
            'attempt' => $run->attempt, 'max_attempts' => $run->max_attempts,
            'created_at' => $run->created_at?->format('d/m/Y H:i:s'),
            'started_at' => $run->started_at?->format('d/m/Y H:i:s'),
            'finished_at' => $run->finished_at?->format('d/m/Y H:i:s'),
            'progress' => $run->progress(), 'error_message' => $run->error_message,
            'url' => route('admin.automation-runs.show', $run),
        ] + ($includePayload ? ['payload' => $run->payload] : []);
    }
}
