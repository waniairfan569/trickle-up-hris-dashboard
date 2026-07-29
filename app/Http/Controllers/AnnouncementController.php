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
        ]);

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned'),
            'is_active' => true,
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
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        return back()->with('success', 'Announcement updated.');
    }

    public function toggle(Request $request, Announcement $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $announcement->update(['is_active' => !$announcement->is_active]);

        return back()->with('success', $announcement->is_active ? 'Announcement shown.' : 'Announcement hidden.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
