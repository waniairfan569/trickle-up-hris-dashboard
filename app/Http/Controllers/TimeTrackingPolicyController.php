<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use App\Models\Department;
use App\Models\OfficeLocation;
use App\Models\TimeTrackingPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TimeTrackingPolicyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $policies = TimeTrackingPolicy::with(['entities', 'departments'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->latest('updated_at')
            ->get();

        return view('time-tracking-policies.index', compact('policies', 'search'));
    }

    public function create()
    {
        return view('time-tracking-policies.wizard', $this->wizardData(null));
    }

    public function edit(TimeTrackingPolicy $timeTrackingPolicy)
    {
        $timeTrackingPolicy->load(['entities', 'departments', 'sites']);

        return view('time-tracking-policies.wizard', $this->wizardData($timeTrackingPolicy));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePolicy($request);

        $policy = TimeTrackingPolicy::create([
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'submission_method' => $validated['submission_method'],
            'allow_timesheet_editing' => $request->boolean('allow_timesheet_editing'),
            'geofencing_enabled' => $request->boolean('geofencing_enabled'),
            'reminders_frequency' => $validated['reminders_frequency'],
            'custom_reminder_times' => $this->cleanReminders($request->input('reminders', [])),
        ]);

        $this->syncRelations($policy, $request);

        return redirect()->route('time-tracking-policies.index')
            ->with('success', "Time tracking policy “{$policy->name}” created.");
    }

    public function update(Request $request, TimeTrackingPolicy $timeTrackingPolicy)
    {
        $validated = $this->validatePolicy($request);

        $timeTrackingPolicy->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'submission_method' => $validated['submission_method'],
            'allow_timesheet_editing' => $request->boolean('allow_timesheet_editing'),
            'geofencing_enabled' => $request->boolean('geofencing_enabled'),
            'reminders_frequency' => $validated['reminders_frequency'],
            'custom_reminder_times' => $this->cleanReminders($request->input('reminders', [])),
        ]);

        $this->syncRelations($timeTrackingPolicy, $request);

        return redirect()->route('time-tracking-policies.index')
            ->with('success', "Time tracking policy “{$timeTrackingPolicy->name}” updated.");
    }

    public function destroy(TimeTrackingPolicy $timeTrackingPolicy)
    {
        $name = $timeTrackingPolicy->name;
        $timeTrackingPolicy->delete();

        return back()->with('success', "“{$name}” deleted.");
    }

    // ---------------------------------------------------------------------

    private function validatePolicy(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:70',
            'description' => 'nullable|string|max:140',
            'submission_method' => ['required', Rule::in(['clock_in_out', 'manual'])],
            'reminders_frequency' => ['required', Rule::in(['work_schedule', 'custom'])],
            'entities' => 'nullable|array',
            'entities.*' => 'integer|exists:company_entities,id',
            'departments' => 'nullable|array',
            'departments.*' => 'integer|exists:departments,id',
            'sites' => 'nullable|array',
            'sites.*' => 'integer|exists:office_locations,id',
            'reminders' => 'nullable|array',
            'reminders.*.day' => 'nullable|string|max:20',
            'reminders.*.time' => 'nullable|string|max:10',
        ]);
    }

    private function syncRelations(TimeTrackingPolicy $policy, Request $request): void
    {
        DB::transaction(function () use ($policy, $request) {
            $policy->entities()->sync($request->input('entities', []));
            $policy->departments()->sync($request->input('departments', []));
            // Sites only matter when geofencing is enabled.
            $policy->sites()->sync($request->boolean('geofencing_enabled') ? $request->input('sites', []) : []);
        });
    }

    private function cleanReminders($reminders): array
    {
        return collect(is_array($reminders) ? $reminders : [])
            ->filter(fn ($r) => !empty($r['day']) && !empty($r['time']))
            ->map(fn ($r) => ['day' => $r['day'], 'time' => $r['time']])
            ->values()
            ->all();
    }

    private function wizardData(?TimeTrackingPolicy $policy): array
    {
        $entities = CompanyEntity::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $sites = OfficeLocation::with('entity:id,name')->orderBy('name')->get();

        $existing = null;
        if ($policy) {
            $existing = [
                'name' => $policy->name,
                'description' => $policy->description,
                'submission_method' => $policy->submission_method,
                'allow_timesheet_editing' => $policy->allow_timesheet_editing,
                'geofencing_enabled' => $policy->geofencing_enabled,
                'reminders_frequency' => $policy->reminders_frequency,
                'entities' => $policy->entities->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'departments' => $policy->departments->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'sites' => $policy->sites->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'reminders' => $policy->custom_reminder_times ?? [],
            ];
        }

        return compact('policy', 'entities', 'departments', 'sites', 'existing');
    }
}
