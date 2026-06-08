<?php

namespace App\Http\Controllers;

use App\Models\WorkSchedule;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    public function index()
    {
        $schedules = WorkSchedule::withCount('employees')->orderByDesc('is_default')->orderBy('name')->get();
        return view('work-schedules.index', compact('schedules'));
    }

    public function create()
    {
        return view('work-schedules.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hours_per_day' => 'required|numeric|min:1|max:24',
            'days_per_week' => 'required|integer|min:1|max:7',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['is_default'] = $request->has('is_default');
        $validated['is_active'] = $request->has('is_active');

        if ($validated['is_default']) {
            WorkSchedule::query()->update(['is_default' => false]);
        }

        WorkSchedule::create($validated);

        return redirect()->route('work-schedules.index')->with('success', 'Work schedule created successfully.');
    }

    public function edit(WorkSchedule $workSchedule)
    {
        return view('work-schedules.edit', compact('workSchedule'));
    }

    public function update(Request $request, WorkSchedule $workSchedule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hours_per_day' => 'required|numeric|min:1|max:24',
            'days_per_week' => 'required|integer|min:1|max:7',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'start_time' => 'required|date_format:H:i:s,H:i',
            'end_time' => 'required|date_format:H:i:s,H:i',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['is_default'] = $request->has('is_default');
        $validated['is_active'] = $request->has('is_active');

        // Extract HH:MM if it has seconds
        $validated['start_time'] = substr($validated['start_time'], 0, 5);
        $validated['end_time'] = substr($validated['end_time'], 0, 5);

        if ($validated['is_default']) {
            WorkSchedule::query()->where('id', '!=', $workSchedule->id)->update(['is_default' => false]);
        } else if ($workSchedule->is_default) {
            // Cannot unset default if it's the only one
            $otherCount = WorkSchedule::where('id', '!=', $workSchedule->id)->count();
            if ($otherCount > 0) {
                // Allowed, but maybe another should be set. Let the user manage it.
            } else {
                $validated['is_default'] = true; // Force true if it's the only one
            }
        }

        $workSchedule->update($validated);

        return redirect()->route('work-schedules.index')->with('success', 'Work schedule updated successfully.');
    }

    public function destroy(WorkSchedule $workSchedule)
    {
        if ($workSchedule->is_default) {
            return back()->with('error', 'Cannot delete the default work schedule. Set another schedule as default first.');
        }

        $workSchedule->employees()->update(['work_schedule_id' => null]);
        $workSchedule->delete();

        return redirect()->route('work-schedules.index')->with('success', 'Work schedule deleted successfully.');
    }

    public function setDefault(WorkSchedule $workSchedule)
    {
        WorkSchedule::query()->update(['is_default' => false]);
        $workSchedule->update(['is_default' => true]);

        return back()->with('success', "{$workSchedule->name} is now the default work schedule.");
    }
}
