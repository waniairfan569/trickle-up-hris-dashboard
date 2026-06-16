<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /** Admin management page — active + archived events. */
    public function index(Request $request)
    {
        $this->authorizeManage();

        $events = Event::where('status', 'active')->orderBy('date')->get();
        $archived = Event::where('status', 'archived')->orderByDesc('date')->get();

        return view('events.index', compact('events', 'archived'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validateEvent($request);

        Event::create($data + [
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'status' => 'active',
        ]);

        return back()->with('success', 'Event added.');
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeManage();
        $event->update($this->validateEvent($request));

        return back()->with('success', 'Event updated.');
    }

    public function archive(Event $event)
    {
        $this->authorizeManage();
        $event->update(['status' => 'archived']);

        return back()->with('success', 'Event archived.');
    }

    public function restore(Event $event)
    {
        $this->authorizeManage();
        $event->update(['status' => 'active']);

        return back()->with('success', 'Event restored.');
    }

    public function destroy(Event $event)
    {
        $this->authorizeManage();
        $title = $event->title;
        $event->delete();

        return back()->with('success', "“{$title}” deleted.");
    }

    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:date',
            'location' => 'nullable|string|max:120',
            'color' => 'nullable|string|max:20',
        ]);
    }

    private function authorizeManage(): void
    {
        $auth = auth()->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Only HR administrators can manage events.');
    }
}
