<?php

use App\Http\Controllers\Admin\AiArticleController;
use App\Http\Controllers\Admin\AiSourceController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\AutomationRunController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\ToxicWordController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WeeklyReviewController;
use App\Http\Controllers\ArticleController as PublicArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/articles/{article:slug}', [PublicArticleController::class, 'show'])
    ->name('articles.show');

Route::prefix('articles/{article:slug}/comments')
    ->name('articles.comments.')
    ->group(function (): void {
        Route::get('/', [CommentController::class, 'index'])->name('index');
        Route::post('/', [CommentController::class, 'store'])
            ->middleware(['auth', 'auth.session', 'throttle:comments'])
            ->name('store');
        Route::patch('/{comment}', [CommentController::class, 'update'])
            ->middleware(['auth', 'auth.session', 'throttle:comments'])
            ->scopeBindings()
            ->name('update');
        Route::delete('/{comment}', [CommentController::class, 'destroy'])
            ->middleware(['auth', 'auth.session'])
            ->scopeBindings()
            ->name('destroy');
        Route::get('/{comment}/replies', [CommentController::class, 'replies'])
            ->scopeBindings()
            ->name('replies');
    });

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])
        ->middleware('throttle:password-reset-request')
        ->name('password.email');
    Route::get('/reset-password/otp', [ResetPasswordController::class, 'showOtp'])->name('password.otp');
    Route::post('/reset-password/otp', [ResetPasswordController::class, 'verifyOtp'])
        ->middleware('throttle:password-reset-verify')
        ->name('password.otp.verify');
    Route::post('/reset-password/otp/resend', [ForgotPasswordController::class, 'resend'])
        ->middleware('throttle:password-reset-request')
        ->name('password.otp.resend');
    Route::get('/reset-password', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
        ->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'auth.session'])
    ->name('logout');

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/change-password', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::put('/change-password', [ChangePasswordController::class, 'update'])
        ->middleware('throttle:password-change')
        ->name('password.change.update');
    Route::get('/change-password/otp', [ChangePasswordController::class, 'showOtp'])->name('password.change.otp');
    Route::post('/change-password/otp', [ChangePasswordController::class, 'verifyOtp'])
        ->middleware('throttle:password-change-verify')
        ->name('password.change.otp.verify');
    Route::post('/change-password/otp/resend', [ChangePasswordController::class, 'resendOtp'])
        ->middleware('throttle:password-change-resend')
        ->name('password.change.otp.resend');

    Route::get('/bookmarks', [BookmarkController::class, 'index'])
        ->name('bookmarks.index');

    Route::post('/bookmarks/{post}', [BookmarkController::class, 'store'])
        ->middleware('throttle:bookmarks')
        ->name('bookmarks.store');

    Route::delete('/bookmarks/{post}', [BookmarkController::class, 'destroy'])
        ->middleware('throttle:bookmarks')
        ->name('bookmarks.destroy');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'auth.session', EnsureUserIsAdmin::class])
    ->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::resource('articles', ArticleController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);

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

        Route::get('/toxic-words', [ToxicWordController::class, 'index'])
            ->name('toxic-words.index');

        Route::post('/toxic-words', [ToxicWordController::class, 'store'])
            ->name('toxic-words.store');

        Route::put('/toxic-words/{toxicWord}', [ToxicWordController::class, 'update'])
            ->name('toxic-words.update');

        Route::patch('/toxic-words/{toxicWord}/status', [ToxicWordController::class, 'updateStatus'])
            ->name('toxic-words.update-status');

        Route::delete('/toxic-words/{toxicWord}', [ToxicWordController::class, 'destroy'])
            ->name('toxic-words.destroy');

        Route::get('/media', [MediaController::class, 'index'])
            ->name('media.index');

        Route::post('/media', [MediaController::class, 'store'])
            ->name('media.store');

        Route::delete('/media/{media}', [MediaController::class, 'destroy'])
            ->name('media.destroy');

        Route::patch('/media/{media}/rename', [MediaController::class, 'rename'])
            ->name('media.rename');

        Route::post('/media/{media}/replace', [MediaController::class, 'replace'])
            ->name('media.replace');

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
        Route::get('/comments', [AdminCommentController::class, 'index'])
            ->name('comments.index');

        Route::patch('/comments/{comment}/status', [AdminCommentController::class, 'updateStatus'])
            ->name('comments.update-status');

        Route::patch('/comments/bulk-status', [AdminCommentController::class, 'bulkUpdateStatus'])
            ->name('comments.bulk-status');
    });
