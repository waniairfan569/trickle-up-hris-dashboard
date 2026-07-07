<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
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

        Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => $request->boolean('is_pinned'),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Announcement posted.');
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
