<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Infrastructure\Persistence\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InAppNotificationController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $query = InAppNotification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20)->through(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'action' => $n->action,
            'target_type' => $n->target_type,
            'target_id' => $n->target_id,
            'actor_name' => $n->actor_name,
            'link' => $n->link,
            'data' => $n->data,
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at->toISOString(),
        ]);

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'filter' => $filter,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = InAppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function recent(Request $request): JsonResponse
    {
        $notifications = InAppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'action' => $n->action,
                'target_type' => $n->target_type,
                'target_id' => $n->target_id,
                'actor_name' => $n->actor_name,
                'link' => $n->link,
                'data' => $n->data,
                'read_at' => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ]);

        return response()->json(['data' => $notifications]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $updated = InAppNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => $updated > 0]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        InAppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
