<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Tag;
use App\Observers\RelatedPostSearchObserver;
use GuzzleHttp\Client as HttpClient;
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
        Category::observe(RelatedPostSearchObserver::class);
        Tag::observe(RelatedPostSearchObserver::class);
    }
}
