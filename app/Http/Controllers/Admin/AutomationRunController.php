<?php

namespace App\Http\Controllers\Admin;

use App\AiWeekly\RunCoordinator;
use App\Http\Controllers\Controller;
use App\Models\AiArticle;
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
            'status' => ['nullable', 'in:PENDING,RUNNING,SUCCESS,PARTIAL,FAILED'],
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

        $monitorRuns = $this->runQuery()->whereIn('status', ['PENDING', 'RUNNING'])->orderByDesc('id')->get()
            ->concat($this->runQuery()->whereNotIn('status', ['PENDING', 'RUNNING'])->orderByDesc('id')->limit(5)->get())
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
            : view('admin.ai-weekly.runs', compact('runs', 'monitorRuns', 'listing', 'pollUrl'));
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
        try {
            $run = $coordinator->collect('MANUAL', $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['run_id' => $run->id, 'reused' => ! $run->wasRecentlyCreated, 'state' => $this->runState($run)], 202);
    }

    public function analyze(Request $request, RunCoordinator $coordinator): JsonResponse
    {
        $data = $request->validate(['article_id' => ['nullable', 'integer', 'exists:ai_articles,id']]);
        if (isset($data['article_id']) && AiArticle::query()->whereKey($data['article_id'])->whereNotNull('duplicate_of')->exists()) {
            return response()->json(['message' => 'Không phân tích lại bài trùng.'], 422);
        }
        $run = $coordinator->analyze('MANUAL', $request->user()->id, $data['article_id'] ?? null);

        return response()->json(['run_id' => $run->id, 'reused' => ! $run->wasRecentlyCreated, 'state' => $this->runState($run)], 202);
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
