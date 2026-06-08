<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    protected ShiftService $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    public function assignSingle(Request $request, User $employee)
    {
        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'date' => 'required|date'
        ]);

        $shift = Shift::findOrFail($validated['shift_id']);

        $this->shiftService->assignShift($employee, $shift, [
            'assignment_type' => 'single',
            'date' => $validated['date'],
            'notes' => $request->notes
        ]);

        return back()->with('success', 'Single shift assigned successfully.');
    }

    public function assignRecurring(Request $request, User $employee)
    {
        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'recurring_start_date' => 'required|date',
            'recurring_end_date' => 'nullable|date|after_or_equal:recurring_start_date',
            'recurring_days' => 'required|array'
        ]);

        $shift = Shift::findOrFail($validated['shift_id']);

        $this->shiftService->assignShift($employee, $shift, [
            'assignment_type' => 'recurring',
            'recurring_start_date' => $validated['recurring_start_date'],
            'recurring_end_date' => $validated['recurring_end_date'] ?? null,
            'recurring_days' => $validated['recurring_days'],
            'notes' => $request->notes
        ]);

        return back()->with('success', 'Recurring shift assigned successfully. Previous recurring shifts have been safely closed.');
    }
}
