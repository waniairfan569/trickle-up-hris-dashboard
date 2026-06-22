<?php

namespace App\Http\Controllers;

use App\Exports\PolicyAcknowledgmentsExport;
use App\Models\CompanyPolicy;
use App\Models\Department;
use App\Models\PolicyAcknowledgment;
use App\Models\PolicyAssignment;
use App\Models\User;
use App\Notifications\PolicyAssigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class CompanyPolicyController extends Controller
{
    private const CATEGORIES = ['hr', 'it', 'finance', 'legal', 'health_safety', 'general'];

    public function index()
    {
        $policies = CompanyPolicy::withCount('acknowledgments')->latest()->get();

        return view('company-policies.index', compact('policies'));
    }

    public function create()
    {
        return view('company-policies.create', ['policy' => new CompanyPolicy(['version' => '1.0', 'category' => 'general', 'status' => 'draft', 'requires_acknowledgment' => true]), 'categories' => self::CATEGORIES] + $this->targets());
    }

    public function store(Request $request)
    {
        $data = $this->validatePolicy($request);
        $policy = new CompanyPolicy($data);
        $policy->created_by = auth()->id();
        $policy->save();

        if ($request->hasFile('document')) {
            $this->storeDocument($policy, $request);
        }

        return redirect()->route('company-policies.show', $policy)->with('success', 'Policy created.');
    }

    public function edit(CompanyPolicy $companyPolicy)
    {
        return view('company-policies.create', ['policy' => $companyPolicy, 'categories' => self::CATEGORIES] + $this->targets());
    }

    public function update(Request $request, CompanyPolicy $companyPolicy)
    {
        $data = $this->validatePolicy($request);
        $companyPolicy->update($data);

        if ($request->hasFile('document')) {
            if ($companyPolicy->document_file && Storage::exists($companyPolicy->document_file)) {
                Storage::delete($companyPolicy->document_file);
            }
            $this->storeDocument($companyPolicy, $request);
        }

        return redirect()->route('company-policies.show', $companyPolicy)->with('success', 'Policy updated.');
    }

    public function show(CompanyPolicy $companyPolicy)
    {
        $companyPolicy->load(['assignments', 'creator']);

        return view('company-policies.show', ['policy' => $companyPolicy] + $this->targets());
    }

    public function assign(Request $request, CompanyPolicy $companyPolicy)
    {
        $validated = $request->validate([
            'assigned_to_type' => ['required', Rule::in(['user', 'department', 'all'])],
            'assigned_to_id' => 'nullable|integer',
            'deadline' => 'nullable|date',
        ]);

        if (in_array($validated['assigned_to_type'], ['user', 'department'], true) && !$validated['assigned_to_id']) {
            return back()->withErrors(['assigned_to_id' => 'Select who to assign the policy to.']);
        }

        $assignment = PolicyAssignment::firstOrCreate(
            [
                'policy_id' => $companyPolicy->id,
                'assigned_to_type' => $validated['assigned_to_type'],
                'assigned_to_id' => $validated['assigned_to_type'] === 'all' ? null : $validated['assigned_to_id'],
            ],
            ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'deadline' => $validated['deadline'] ?? null]
        );

        $count = 0;
        foreach ($companyPolicy->getAssignedUsers() as $user) {
            $ack = PolicyAcknowledgment::firstOrCreate(
                ['policy_id' => $companyPolicy->id, 'user_id' => $user->id],
                ['assignment_id' => $assignment->id, 'status' => 'pending']
            );
            if ($ack->wasRecentlyCreated) {
                $count++;
                try {
                    $user->notify(new PolicyAssigned($companyPolicy));
                } catch (\Throwable $e) {
                }
            }
        }

        return back()->with('success', "Policy assigned. {$count} new employee(s) added.");
    }

    public function acknowledgments(Request $request, CompanyPolicy $companyPolicy)
    {
        $assigned = $companyPolicy->getAssignedUsers();
        $acks = $companyPolicy->acknowledgments()->with('employee.department')->latest('id')->get();

        $stats = [
            'assigned' => $assigned->count(),
            'acknowledged' => $acks->where('status', 'acknowledged')->count(),
            'pending' => $acks->where('status', '!=', 'acknowledged')->count(),
            'overdue' => $companyPolicy->effective_date && $companyPolicy->effective_date->isPast()
                ? $acks->where('status', '!=', 'acknowledged')->count() : 0,
        ];

        return view('company-policies.acknowledgments', ['policy' => $companyPolicy, 'acks' => $acks, 'stats' => $stats] + $this->targets());
    }

    public function sendReminder(PolicyAcknowledgment $acknowledgment)
    {
        try {
            $acknowledgment->employee?->notify(new PolicyAssigned($acknowledgment->policy, true));
            $acknowledgment->update(['updated_at' => now()]);
            if ($acknowledgment->assignment) {
                $acknowledgment->assignment->update(['reminder_sent_at' => now()]);
            }

            return response()->json(['sent' => true]);
        } catch (\Throwable $e) {
            return response()->json(['sent' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function export(CompanyPolicy $companyPolicy)
    {
        return Excel::download(new PolicyAcknowledgmentsExport($companyPolicy), Str::slug($companyPolicy->title) . '-acknowledgments.xlsx');
    }

    public function download(CompanyPolicy $companyPolicy)
    {
        $user = auth()->user();
        $allowed = $user->isAdmin() || $companyPolicy->getAssignedUsers()->pluck('id')->contains($user->id);
        abort_unless($allowed, 403, 'This policy is not assigned to you.');
        abort_unless($companyPolicy->document_file && Storage::exists($companyPolicy->document_file), 404);

        return Storage::download($companyPolicy->document_file, $companyPolicy->document_filename ?? 'policy-document');
    }

    public function destroy(CompanyPolicy $companyPolicy)
    {
        $companyPolicy->delete();

        return redirect()->route('company-policies.index')->with('success', 'Policy deleted.');
    }

    // ---------------------------------------------------------------------

    private function validatePolicy(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'version' => 'nullable|string|max:20',
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'effective_date' => 'nullable|date',
            'review_date' => 'nullable|date',
            'acknowledgment_deadline' => 'nullable|integer|min:0|max:365',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
        ]);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'version' => $validated['version'] ?: '1.0',
            'category' => $validated['category'],
            'status' => $validated['status'] ?? 'draft',
            'effective_date' => $validated['effective_date'] ?? null,
            'review_date' => $validated['review_date'] ?? null,
            'acknowledgment_deadline' => $validated['acknowledgment_deadline'] ?? null,
            'requires_acknowledgment' => $request->boolean('requires_acknowledgment'),
            'requires_signature' => $request->boolean('requires_acknowledgment') && $request->boolean('requires_signature'),
        ];
    }

    private function storeDocument(CompanyPolicy $policy, Request $request): void
    {
        $file = $request->file('document');
        $path = $file->store('policies/' . $policy->id);
        $policy->update([
            'document_file' => $path,
            'document_filename' => $file->getClientOriginalName(),
            'document_size' => $file->getSize(),
        ]);
    }

    private function targets(): array
    {
        return [
            'users' => User::where('account_status', '!=', 'deactivated')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }
}
