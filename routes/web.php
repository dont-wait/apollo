<?php

use App\Http\Controllers\Admin\AiArticleController;
use App\Http\Controllers\Admin\AiSourceController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\AutomationRunController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\WeeklyReviewController;
use App\Http\Controllers\ArticleController as PublicArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/articles/{article:slug}', [PublicArticleController::class, 'show'])
    ->name('articles.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', EnsureUserIsAdmin::class])
    ->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::resource('articles', ArticleController::class)->except(['show']);

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('categories.index');

        Route::post('/categories', [CategoryController::class, 'store'])
            ->name('categories.store');

        Route::get('/categories/{category}', [CategoryController::class, 'show'])
            ->name('categories.show');

        Route::put('/categories/{category}', [CategoryController::class, 'update'])
            ->name('categories.update');

        Route::patch('/categories/{category}/status', [CategoryController::class, 'updateStatus'])
            ->name('categories.update-status');

        Route::get('/tags', [TagController::class, 'index'])
            ->name('tags.index');

        Route::post('/tags', [TagController::class, 'store'])
            ->name('tags.store');

        Route::get('/tags/{tag}', [TagController::class, 'show'])
            ->name('tags.show');

        Route::put('/tags/{tag}', [TagController::class, 'update'])
            ->name('tags.update');

        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])
            ->name('tags.destroy');

        Route::get('/media', [MediaController::class, 'index'])
            ->name('media.index');

        Route::post('/media', [MediaController::class, 'store'])
            ->name('media.store');

        Route::get('/ai-sources', [AiSourceController::class, 'index'])->name('ai-sources.index');
        Route::post('/ai-sources', [AiSourceController::class, 'store'])->name('ai-sources.store');
        Route::put('/ai-sources/{source}', [AiSourceController::class, 'update'])->name('ai-sources.update');
        Route::get('/ai-articles', [AiArticleController::class, 'index'])->name('ai-articles.index');
        Route::get('/ai-articles/{article}', [AiArticleController::class, 'show'])->name('ai-articles.show');
        Route::post('/automation-runs/collect', [AutomationRunController::class, 'collect'])->name('automation-runs.collect');
        Route::post('/automation-runs/analyze', [AutomationRunController::class, 'analyze'])->name('automation-runs.analyze');
        Route::get('/automation-runs', [AutomationRunController::class, 'index'])->name('automation-runs.index');
        Route::get('/automation-runs/{run}', [AutomationRunController::class, 'show'])->name('automation-runs.show');
        Route::post('/automation-runs/{run}/retry', [AutomationRunController::class, 'retry'])->name('automation-runs.retry');
        Route::get('/weekly-reviews', [WeeklyReviewController::class, 'index'])->name('weekly-reviews.index');
        Route::post('/weekly-reviews/generate', [WeeklyReviewController::class, 'generate'])->name('weekly-reviews.generate');
        Route::get('/weekly-reviews/{review}', [WeeklyReviewController::class, 'show'])->name('weekly-reviews.show');
        Route::put('/weekly-reviews/{review}', [WeeklyReviewController::class, 'update'])->name('weekly-reviews.update');
        Route::post('/weekly-reviews/{review}/regenerate', [WeeklyReviewController::class, 'regenerate'])->name('weekly-reviews.regenerate');
        Route::post('/weekly-reviews/{review}/apply-candidate', [WeeklyReviewController::class, 'applyCandidate'])->name('weekly-reviews.apply-candidate');
        Route::post('/weekly-reviews/{review}/convert-to-post', [WeeklyReviewController::class, 'convert'])->name('weekly-reviews.convert');
    });
