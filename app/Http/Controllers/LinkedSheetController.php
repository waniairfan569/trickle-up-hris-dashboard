<?php

namespace App\Http\Controllers;

use App\Models\LinkedSheet;
use Illuminate\Http\Request;

class LinkedSheetController extends Controller
{
    /** The sheets library — grouped by category. Admin-only (gated at the route). */
    public function index(Request $request)
    {
        $q        = trim((string) $request->get('q'));
        $category = $request->get('category');

        $sheets = LinkedSheet::with('creator')
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
        $categories = LinkedSheet::whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        return view('sheets.index', [
            'grouped'    => $grouped,
            'total'      => $sheets->count(),
            'categories' => $categories,
            'q'          => $q,
            'category'   => $category,
        ]);
    }

    /** Register a new linked sheet. */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $sheet = LinkedSheet::create([
            'name'        => $data['name'],
            'url'         => $data['url'],
            'description' => $data['description'] ?? null,
            'category'    => ($data['category'] ?? null) ?: null,
            'provider'    => LinkedSheet::detectProvider($data['url']),
            'created_by'  => $request->user()->id,
        ]);

        return redirect()->route('sheets.index')->with('success', "“{$sheet->name}” added.");
    }

    /** Update a linked sheet. */
    public function update(Request $request, LinkedSheet $sheet)
    {
        $data = $this->validated($request);

        $sheet->update([
            'name'        => $data['name'],
            'url'         => $data['url'],
            'description' => $data['description'] ?? null,
            'category'    => ($data['category'] ?? null) ?: null,
            'provider'    => LinkedSheet::detectProvider($data['url']),
        ]);

        return redirect()->route('sheets.index')->with('success', "“{$sheet->name}” updated.");
    }

    /** Remove a linked sheet. Soft delete — the record is recoverable. */
    public function destroy(LinkedSheet $sheet)
    {
        $name = $sheet->name;
        $sheet->delete();

        return redirect()->route('sheets.index')->with('success', "“{$name}” removed.");
    }

    /** Open the sheet at its source (tracks an open, then redirects). */
    public function open(LinkedSheet $sheet)
    {
        $sheet->increment('opens_count');
        $sheet->forceFill(['last_opened_at' => now()])->save();

        return redirect()->away($sheet->url);
    }

    /** In-app embedded preview of the sheet. */
    public function preview(LinkedSheet $sheet)
    {
        return view('sheets.preview', compact('sheet'));
    }

    // ── Internals ───────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:150',
            'url'         => 'required|url|max:2000',
            'description' => 'nullable|string|max:1000',
            'category'    => 'nullable|string|max:80',
        ], [
            'url.url' => 'Enter a valid link (including https://).',
        ]);
    }
}
