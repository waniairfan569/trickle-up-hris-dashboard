<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LinkedSheet;
use Illuminate\Http\Request;

class LinkedSheetController extends Controller
{
    /** The sheets library — grouped by category, scoped to what the user may see. */
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $q        = trim((string) $request->get('q'));
        $category = $request->get('category');

        $sheets = LinkedSheet::with(['departments', 'creator'])
            ->visibleTo($user)
            ->when($category, fn ($b) => $b->where('category', $category))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('category', 'like', "%{$q}%")))
            ->orderByRaw('category is null, category')
            ->orderBy('name')
            ->get();

        // Group by category for display (uncategorised last, under "Uncategorised").
        $grouped = $sheets->groupBy(fn ($s) => $s->category ?: 'Uncategorised');

        // Distinct categories for the filter + the add-form datalist.
        $categories = LinkedSheet::visibleTo($user)
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        $departments = $isAdmin ? Department::orderBy('name')->get(['id', 'name']) : collect();

        return view('sheets.index', [
            'grouped'     => $grouped,
            'total'       => $sheets->count(),
            'categories'  => $categories,
            'departments' => $departments,
            'isAdmin'     => $isAdmin,
            'q'           => $q,
            'category'    => $category,
        ]);
    }

    /** Register a new linked sheet (admin). */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $sheet = LinkedSheet::create([
            'name'        => $data['name'],
            'url'         => $data['url'],
            'description' => $data['description'] ?? null,
            'category'    => ($data['category'] ?? null) ?: null,
            'provider'    => LinkedSheet::detectProvider($data['url']),
            'visibility'  => $data['visibility'],
            'created_by'  => $request->user()->id,
        ]);

        $this->syncDepartments($sheet, $data);

        return redirect()->route('sheets.index')->with('success', "“{$sheet->name}” added.");
    }

    /** Update a linked sheet (admin). */
    public function update(Request $request, LinkedSheet $sheet)
    {
        $data = $this->validated($request);

        $sheet->update([
            'name'        => $data['name'],
            'url'         => $data['url'],
            'description' => $data['description'] ?? null,
            'category'    => ($data['category'] ?? null) ?: null,
            'provider'    => LinkedSheet::detectProvider($data['url']),
            'visibility'  => $data['visibility'],
        ]);

        $this->syncDepartments($sheet, $data);

        return redirect()->route('sheets.index')->with('success', "“{$sheet->name}” updated.");
    }

    /** Remove a linked sheet (admin). Soft delete — the record is recoverable. */
    public function destroy(LinkedSheet $sheet)
    {
        $name = $sheet->name;
        $sheet->delete();

        return redirect()->route('sheets.index')->with('success', "“{$name}” removed.");
    }

    /** Open the sheet at its source (tracks an open, then redirects). */
    public function open(Request $request, LinkedSheet $sheet)
    {
        abort_unless($sheet->canView($request->user()), 403);

        $sheet->increment('opens_count');
        $sheet->forceFill(['last_opened_at' => now()])->save();

        return redirect()->away($sheet->url);
    }

    /** In-app embedded preview of the sheet. */
    public function preview(Request $request, LinkedSheet $sheet)
    {
        abort_unless($sheet->canView($request->user()), 403);

        return view('sheets.preview', compact('sheet'));
    }

    // ── Internals ───────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:150',
            'url'             => 'required|url|max:2000',
            'description'     => 'nullable|string|max:1000',
            'category'        => 'nullable|string|max:80',
            'visibility'      => 'required|in:everyone,admins,departments',
            'department_ids'  => 'required_if:visibility,departments|array',
            'department_ids.*'=> 'exists:departments,id',
        ], [
            'url.url'                     => 'Enter a valid link (including https://).',
            'department_ids.required_if'  => 'Pick at least one department for department-restricted visibility.',
        ]);
    }

    private function syncDepartments(LinkedSheet $sheet, array $data): void
    {
        if (($data['visibility'] ?? null) === 'departments') {
            $sheet->departments()->sync($data['department_ids'] ?? []);
        } else {
            $sheet->departments()->detach();
        }
    }
}
