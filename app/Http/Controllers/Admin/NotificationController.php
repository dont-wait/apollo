<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\WeeklyReviewReady;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->unreadNotifications()->where('type', WeeklyReviewReady::class);
        $count = (clone $query)->count();
        $notifications = $query->limit(10)->get()->map(fn (DatabaseNotification $notification): array => [
            'id' => $notification->id, 'title' => $notification->data['title'],
            'week_start' => $notification->data['week_start'], 'is_candidate' => $notification->data['is_candidate'],
            'open_url' => route('admin.notifications.open', $notification->id, absolute: false),
        ]);

        return response()->json(['data' => $notifications, 'unread_count' => $count]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->where('type', WeeklyReviewReady::class)->findOrFail($notification);
        $record->markAsRead();

        if ($record->data['post_id'] ?? null) {
            return redirect()->route('admin.articles.edit', $record->data['post_id']);
        }

        return redirect()->route('admin.weekly-reviews.show', $record->data['review_id']);
    }
}
