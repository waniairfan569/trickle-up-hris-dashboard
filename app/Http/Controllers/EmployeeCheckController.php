<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCheck;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Tenancy\TenantStorage;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pre-employment and ongoing checks on an employee's record.
 *
 * No provider is integrated and none is implied. What this holds is the thing
 * a client is actually asked for later: that the check was done, what it said,
 * who says so, the evidence, and the day it stops counting.
 */
class EmployeeCheckController extends Controller
{
    use LogsActivity;

    /** Files that can stand as evidence — same set the employee file accepts. */
    private const ACCEPTED = 'pdf,jpg,jpeg,png,webp,doc,docx';

    public function index(Request $request)
    {
        $this->authorizeManage();

        $state = in_array($request->query('state'), ['attention', 'all'], true)
            ? $request->query('state')
            : 'attention';

        $checks = EmployeeCheck::with(['employee', 'recorder'])
            ->when($state === 'attention', fn ($q) => $q->needsAttention())
            ->orderByRaw('expires_on IS NULL, expires_on')
            ->paginate(40)->withQueryString();

        return view('employee-checks.index', [
            'checks' => $checks,
            'state' => $state,
            'counts' => [
                'attention' => EmployeeCheck::needsAttention()->count(),
                'all' => EmployeeCheck::count(),
            ],
        ]);
    }

    public function forEmployee(User $employee)
    {
        $this->authorizeManage();

        return view('employee-checks.employee', [
            'employee' => $employee,
            'checks' => EmployeeCheck::with('recorder', 'document')
                ->where('user_id', $employee->id)
                ->orderByDesc('checked_on')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, User $employee)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(EmployeeCheck::TYPES))],
            'status' => ['required', Rule::in(array_keys(EmployeeCheck::STATUSES))],
            'reference' => 'nullable|string|max:120',
            'verified_by_name' => 'nullable|string|max:120',
            'checked_on' => 'nullable|date|before_or_equal:today',
            'expires_on' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'evidence' => 'nullable|file|max:20480|mimes:' . self::ACCEPTED,
        ]);

        // Evidence is kept as an employee document, so it sits with the rest of
        // their file and inherits the same access rules rather than living in a
        // second place with its own.
        $documentId = null;

        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $document = EmployeeDocument::create([
                'user_id' => $employee->id,
                'name' => EmployeeCheck::TYPES[$validated['type']] . ' evidence',
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store(TenantStorage::path('employee-documents')),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
            $documentId = $document->id;
        }

        $check = EmployeeCheck::create([
            'user_id' => $employee->id,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'reference' => $validated['reference'] ?? null,
            'verified_by_name' => $validated['verified_by_name'] ?? null,
            'checked_on' => $validated['checked_on'] ?? null,
            'expires_on' => $validated['expires_on'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'document_id' => $documentId,
            'recorded_by' => auth()->id(),
        ]);

        // A check is evidence about a person, so who recorded it and when is
        // part of the record rather than a detail.
        try {
            $this->logActivity(
                'created', 'User', $employee->id,
                "{$check->typeLabel()} check recorded for {$employee->full_name}: {$check->statusLabel()}"
                . ($check->expires_on ? ', expires ' . $check->expires_on->format('j M Y') : ''),
                ['type' => $check->type, 'status' => $check->status, 'check_id' => $check->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', $check->typeLabel() . ' check recorded.');
    }

    public function update(Request $request, EmployeeCheck $check)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(EmployeeCheck::STATUSES))],
            'expires_on' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $check->update($validated);

        return back()->with('success', 'Check updated.');
    }

    public function destroy(EmployeeCheck $check)
    {
        $this->authorizeManage();

        $label = $check->typeLabel();
        $check->delete();

        return back()->with('success', $label . ' check removed. Any evidence stays on their file.');
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Only HR administrators can manage checks.');
    }
}
