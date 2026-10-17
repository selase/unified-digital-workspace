<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NotificationCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $notifications = $user->notifications()
            ->latest()
            ->limit(25)
            ->get()
            ->map(static fn ($notification): array => [
                'id' => (string) $notification->id,
                'type' => (string) ($notification->data['type'] ?? class_basename((string) $notification->type)),
                'title' => (string) ($notification->data['title'] ?? $notification->data['subject'] ?? 'Notification'),
                'sender_name' => (string) ($notification->data['sender_name'] ?? 'System'),
                'action_url' => (string) ($notification->data['action_url'] ?? '#'),
                'read_at' => $notification->read_at?->format(config('app.datetime_format', 'd/m/Y H:i')),
                'created_at' => $notification->created_at?->format(config('app.datetime_format', 'd/m/Y H:i')),
            ]);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $record = $user->notifications()
            ->where('id', $notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return back()->with([
            'status' => 'success',
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $user->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'unread_count' => 0,
            ]);
        }

        return back()->with([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
        ]);
    }
}
