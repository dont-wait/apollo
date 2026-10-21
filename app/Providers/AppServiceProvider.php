<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Tag;
use App\Observers\RelatedPostSearchObserver;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RelatedPostSearchObserver::class);
        $this->app->singleton(Client::class, fn (): Client => new Client(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key'),
            new HttpClient(['connect_timeout' => 2, 'timeout' => 5]),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('comments', function (Request $request): array {
            return [
                Limit::perMinute(10)->by('user:'.$request->user()->id),
                Limit::perMinute(30)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('bookmarks', function (Request $request): array {
            return [
                Limit::perMinute(60)->by('user:'.$request->user()->id),
                Limit::perMinute(120)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset-request', function (Request $request): array {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key)];
        });

        RateLimiter::for('password-reset-verify', function (Request $request): array {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return [Limit::perMinute(10)->by($key)];
        });

        RateLimiter::for('password-change', function (Request $request): array {
            return [Limit::perMinute(5)->by('user:'.$request->user()->id)];
        });

        Category::observe(RelatedPostSearchObserver::class);
        Tag::observe(RelatedPostSearchObserver::class);
    }
}
