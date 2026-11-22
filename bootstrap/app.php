<?php

use App\Http\Middleware\AuthenticateSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('search:process-outbox')
            ->everyMinute()
            ->withoutOverlapping(10);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.session' => AuthenticateSession::class]);
        $middleware->trustHosts(
            at: function (): array {
                $hosts = config('seo.trusted_hosts', []);
                if ($hosts === []) {
                    $hosts = [(string) parse_url((string) config('app.url'), PHP_URL_HOST)];
                }

                return array_map(
                    static fn (string $host): string => '^'.preg_quote($host, '/').'$',
                    $hosts,
                );
            },
            subdomains: false,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {})->create();
