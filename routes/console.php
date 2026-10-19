<?php

use App\AiWeekly\RunCoordinator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (RunCoordinator $coordinator): void {
    $coordinator->collect('SCHEDULED');
})->name('ai-weekly:collect')->cron(config('ai_weekly.collect_schedule'))->timezone(config('ai_weekly.timezone'))->onOneServer();

Schedule::call(function (RunCoordinator $coordinator): void {
    $coordinator->analyze('SCHEDULED');
})->name('ai-weekly:analyze')->cron(config('ai_weekly.analyze_schedule'))->timezone(config('ai_weekly.timezone'))->onOneServer();

Schedule::call(function (RunCoordinator $coordinator): void {
    $weekStart = now()->timezone(config('ai_weekly.timezone'))->startOfWeek()->subWeek()->toDateString();
    $coordinator->generate($weekStart, 'SCHEDULED');
})->name('ai-weekly:generate')->cron(config('ai_weekly.review_schedule'))->timezone(config('ai_weekly.timezone'))->onOneServer();

Schedule::call(function (RunCoordinator $coordinator): void {
    $coordinator->reconcile();
})->name('ai-weekly:reconcile')->everyTenMinutes()->onOneServer();
