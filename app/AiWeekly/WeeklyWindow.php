<?php

namespace App\AiWeekly;

use App\Models\AiArticle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class WeeklyWindow
{
    public function start(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('ai_weekly.timezone'))->startOfWeek();
    }

    /** @return Collection<int, AiArticle> */
    public function articles(CarbonImmutable $start): Collection
    {
        $utcStart = $start->utc();
        $utcEnd = $start->addWeek()->utc();

        return AiArticle::query()->with(['source', 'topics' => fn ($query) => $query->where('status', 'ACTIVE'), 'analyses' => fn ($query) => $query->where('is_current', true)->where('status', 'SUCCESS')])
            ->whereNull('duplicate_of')
            ->whereHas('analyses', fn ($query) => $query->where('is_current', true)->where('status', 'SUCCESS'))
            ->where(function ($query) use ($utcStart, $utcEnd): void {
                $query->whereBetween('published_at', [$utcStart, $utcEnd->subMicrosecond()])
                    ->orWhere(function ($fallback) use ($utcStart, $utcEnd): void {
                        $fallback->whereNull('published_at')->whereBetween('collected_at', [$utcStart, $utcEnd->subMicrosecond()]);
                    });
            })->get();
    }
}
