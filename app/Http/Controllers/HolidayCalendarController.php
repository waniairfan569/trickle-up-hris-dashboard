<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HolidayCalendarController extends Controller
{
    public function index()
    {
        $calendars = HolidayCalendar::withCount(['holidays', 'users'])->orderBy('name')->get();
        return view('holiday-calendars.index', compact('calendars'));
    }

    public function create()
    {
        // For inline creation if needed, or redirect back if handled by modal
        return view('holiday-calendars.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_code' => 'nullable|string|max:2',
            'year' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $calendar = HolidayCalendar::create($validated);

        return redirect()->route('holiday-calendars.show', $calendar)->with('success', 'Holiday calendar created.');
    }

    public function show(HolidayCalendar $holidayCalendar)
    {
        $holidayCalendar->load('holidays');
        // Users assigned to this calendar
        $assignedUsers = $holidayCalendar->users()->orderBy('first_name')->get();
        // Unassigned users for the assignment dropdown
        $assignedUserIds = $assignedUsers->pluck('id')->toArray();
        $availableUsers = User::whereNotIn('id', $assignedUserIds)->orderBy('first_name')->get();

        return view('holiday-calendars.show', compact('holidayCalendar', 'assignedUsers', 'availableUsers'));
    }

    public function update(Request $request, HolidayCalendar $holidayCalendar)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_code' => 'nullable|string|max:2',
            'year' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $holidayCalendar->update($validated);

        return redirect()->route('holiday-calendars.show', $holidayCalendar)->with('success', 'Calendar updated.');
    }

    public function destroy(HolidayCalendar $holidayCalendar)
    {
        $holidayCalendar->delete();
        return redirect()->route('holiday-calendars.index')->with('success', 'Calendar deleted.');
    }

    // --- Custom Actions ---

    public function addHoliday(Request $request, HolidayCalendar $holidayCalendar)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'is_recurring' => 'boolean',
        ]);

        $validated['is_recurring'] = $request->has('is_recurring');

        $holidayCalendar->holidays()->create($validated);

        return back()->with('success', 'Holiday added successfully.');
    }

    public function removeHoliday(HolidayCalendar $holidayCalendar, Holiday $holiday)
    {
        if ($holiday->calendar_id === $holidayCalendar->id) {
            $holiday->delete();
        }
        return back()->with('success', 'Holiday removed.');
    }

    public function assign(Request $request, HolidayCalendar $holidayCalendar)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $syncData = [];
        foreach ($request->user_ids as $userId) {
            $syncData[$userId] = ['assigned_by' => auth()->id() ?? 1];
        }

        // Use syncWithoutDetaching so we don't drop existing ones if they weren't selected
        $holidayCalendar->users()->syncWithoutDetaching($syncData);

        return back()->with('success', 'Employees assigned successfully.');
    }

    public function unassign(HolidayCalendar $holidayCalendar, User $user)
    {
        $holidayCalendar->users()->detach($user->id);
        return back()->with('success', 'Employee unassigned from this calendar.');
    }
}
