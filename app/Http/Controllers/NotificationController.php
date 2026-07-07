<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Notification inbox — Inbox / Important / To-dos folders with history.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->get('tab', 'inbox');

        $all = $user->notifications()->latest()->get();

        $isImportant = fn ($n) => (bool) ($n->data['important'] ?? false) || (($n->data['priority'] ?? null) === 'high');
        $isTodo = fn ($n) => !empty($n->data['action_url'] ?? null) || !empty($n->data['action'] ?? null) || (($n->data['category'] ?? null) === 'todo');

        $important = $all->filter($isImportant)->values();
        $todos = $all->filter($isTodo)->values();

        $notifications = match ($tab) {
            'important' => $important,
            'todos' => $todos,
            default => $all,
        };

        return view('notifications.index', [
            'notifications' => $notifications,
            'tab' => $tab,
            'counts' => [
                'inbox' => $all->count(),
                'important' => $important->count(),
                'todos' => $todos->count(),
                'unread' => $all->whereNull('read_at')->count(),
            ],
        ]);
    }

    /** JSON of unread notifications — used to raise browser/desktop pop-ups. */
    public function unreadJson(Request $request)
    {
        $items = $request->user()->unreadNotifications()->latest()->take(10)->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? route('notifications.index'),
                'icon' => $n->data['icon'] ?? 'bell',
            ]);

        return response()->json(['count' => $items->count(), 'items' => $items]);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->unreadNotifications->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
        return back();
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }
}
