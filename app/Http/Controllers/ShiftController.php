<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::withCount('assignments')->get();
        $defaultShift = Shift::getDefault();

        return view('shifts.index', compact('shifts', 'defaultShift'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
            'break_minutes' => 'required|integer|min:0',
            'working_days' => 'nullable|array',
            'color' => 'required|string',
            'is_default' => 'nullable',
            'auto_assign_to_new_employees' => 'nullable',
            'crosses_midnight' => 'nullable'
        ]);

        $validated['is_default'] = $request->has('is_default');
        $validated['auto_assign_to_new_employees'] = $request->has('auto_assign_to_new_employees');
        $validated['crosses_midnight'] = $request->has('crosses_midnight');

        $shift = Shift::create($validated);

        return redirect()->route('shifts.index')->with('success', 'Shift created successfully.');
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
            'break_minutes' => 'required|integer|min:0',
            'working_days' => 'nullable|array',
            'color' => 'required|string',
            'is_default' => 'nullable',
            'auto_assign_to_new_employees' => 'nullable',
            'crosses_midnight' => 'nullable'
        ]);

        $validated['is_default'] = $request->has('is_default');
        $validated['auto_assign_to_new_employees'] = $request->has('auto_assign_to_new_employees');
        $validated['crosses_midnight'] = $request->has('crosses_midnight');

        $shift->update($validated);

        return redirect()->route('shifts.index')->with('success', 'Shift updated successfully.');
    }

    public function destroy(Shift $shift)
    {
        if ($shift->is_default) {
            return back()->with('error', 'Cannot delete the default shift.');
        }

        if ($shift->assignments()->exists()) {
            return back()->with('error', 'Cannot delete a shift that has employees assigned to it.');
        }

        $shift->delete();
        return back()->with('success', 'Shift deleted successfully.');
    }

    public function setDefault(Shift $shift)
    {
        // Model boot hook handles unsetting others
        $shift->update([
            'is_default' => true,
            'auto_assign_to_new_employees' => true
        ]);

        return back()->with('success', $shift->name.' is now the default shift for new employees.');
    }

    public function assignToAll(Shift $shift, ShiftService $shiftService)
    {
        // Find all users who do not have an active recurring shift assignment
        $usersWithoutShift = User::whereNotIn('id', function($query) {
            $query->select('user_id')
                  ->from('shift_assignments')
                  ->where('assignment_type', 'recurring')
                  ->whereNull('recurring_end_date'); // active recurring
        })->where('status', 'active')->get();

        $count = 0;
        foreach ($usersWithoutShift as $user) {
            $shiftService->assignShift($user, $shift, [
                'assignment_type' => 'recurring',
                'recurring_start_date' => now()->toDateString(),
                'recurring_days' => $shift->working_days ?? ["Mon","Tue","Wed","Thu","Fri"],
                'notes' => 'Bulk assigned to all empty employees via Shift Management'
            ]);
            $count++;
        }

        return back()->with('success', "Shift assigned to {$count} employees without an existing shift.");
    }
}
