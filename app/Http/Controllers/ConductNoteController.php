<?php

namespace App\Http\Controllers;

use App\Models\ConductNote;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin-only conduct / behaviour log for an employee. Entries surface on the
 * admin side (profile → Time tracking) and in the employee's time-tracking report.
 */
class ConductNoteController extends Controller
{
    public function store(Request $request, User $employee)
    {
        $data = $request->validate([
            'occurred_on' => 'required|date|before_or_equal:today',
            'category'    => 'nullable|string|max:40',
            'note'        => 'required|string|max:2000',
        ]);

        ConductNote::create([
            'user_id'     => $employee->id,
            'author_id'   => $request->user()->id,
            'occurred_on' => $data['occurred_on'],
            'category'    => $data['category'] ?: null,
            'note'        => trim($data['note']),
        ]);

        return redirect()
            ->route('employees.profile', ['employee' => $employee->id, 'section' => 'timetracking'])
            ->with('success', 'Conduct note added.');
    }

    public function destroy(ConductNote $conductNote)
    {
        $employeeId = $conductNote->user_id;
        $conductNote->delete();

        return redirect()
            ->route('employees.profile', ['employee' => $employeeId, 'section' => 'timetracking'])
            ->with('success', 'Conduct note removed.');
    }
}
