<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class AnnouncementController extends Controller
{
    /** Read-only list of all active announcements for every employee. */
    public function all()
    {
        $announcements = Announcement::active()->with('creator')
            ->orderByDesc('is_pinned')->latest()->get();

        return view('announcements.all', compact('announcements'));
    }

    public function index(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $announcements = Announcement::with('creator')
            ->orderByDesc('is_pinned')
            ->latest()
            ->get();

        return view('announcements.index', compact('announcements'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'is_pinned' => 'nullable|boolean',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned'),
            'is_active' => true,
            'expires_at' => $request->filled('expires_at') ? $validated['expires_at'] : null,
            'created_by' => $request->user()->id,
        ]);
        $announcement->setRelation('creator', $request->user());

        $this->broadcastAnnouncement($announcement, $request->user()->id);

        return back()->with('success', 'Announcement posted — everyone has been notified.');
    }

    /** Bell notification to every active user + one BCC email to all (fast). */
    private function broadcastAnnouncement(Announcement $announcement, int $exceptUserId): void
    {
        $users = User::where('account_status', '!=', 'deactivated')
            ->where('id', '!=', $exceptUserId)
            ->get();

        // Bell (database) — bulk, fast.
        try {
            Notification::send($users, new AnnouncementPosted($announcement));
        } catch (\Throwable $e) {
            report($e);
        }

        // Email — ONE message BCC'd to everyone, so posting doesn't hang on N sends.
        $emails = $users->pluck('email')->filter()->unique()->values()->all();
        if (!empty($emails)) {
            try {
                $from = config('mail.from.address') ?: 'no-reply@' . request()->getHost();
                Mail::send('emails.announcement', ['announcement' => $announcement], function ($m) use ($from, $emails, $announcement) {
                    $m->to($from)->bcc($emails)->subject('📢 ' . $announcement->title);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function update(Request $request, Announcement $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'is_pinned' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned'),
            'expires_at' => $request->filled('expires_at') ? $validated['expires_at'] : null,
        ]);

        return back()->with('success', 'Announcement updated.');
    }

    public function toggle(Request $request, Announcement $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $announcement->update(['is_active' => !$announcement->is_active]);

        return back()->with('success', $announcement->is_active ? 'Announcement shown.' : 'Announcement hidden.');
    }

    /** Archive an announcement (soft delete) — recoverable from the Archive. */
    public function destroy(Request $request, Announcement $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement archived — you can restore it anytime from the Archive.');
    }

    /** Admin: list archived (soft-deleted) announcements. */
    public function archived(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $announcements = Announcement::onlyTrashed()->with('creator')
            ->latest('deleted_at')
            ->get();

        return view('announcements.archived', compact('announcements'));
    }

    /** Admin: restore an archived announcement back to the live list. */
    public function restore(Request $request, int $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $record = Announcement::onlyTrashed()->findOrFail($announcement);
        $record->restore();

        return back()->with('success', 'Announcement restored.');
    }

    /** Admin: permanently delete an archived announcement (cannot be undone). */
    public function forceDelete(Request $request, int $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $record = Announcement::onlyTrashed()->findOrFail($announcement);
        $record->forceDelete();

        return back()->with('success', 'Announcement permanently deleted.');
    }

    /** AJAX: the current user acknowledges one announcement (won't auto-pop again). */
    public function markRead(Request $request, Announcement $announcement)
    {
        \App\Models\AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => auth()->id()],
            ['read_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    /** AJAX: mark every currently-unread announcement as read for this user. */
    public function markAllRead(Request $request)
    {
        $userId = auth()->id();
        $unread = Announcement::active()
            ->whereDoesntHave('reads', fn ($r) => $r->where('user_id', $userId))
            ->pluck('id');

        foreach ($unread as $id) {
            \App\Models\AnnouncementRead::updateOrCreate(
                ['announcement_id' => $id, 'user_id' => $userId],
                ['read_at' => now()]
            );
        }

        return response()->json(['success' => true, 'count' => $unread->count()]);
    }
}
