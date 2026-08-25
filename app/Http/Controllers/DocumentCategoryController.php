<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use Illuminate\Http\Request;

class DocumentCategoryController extends Controller
{
    /** JSON API — used by the document-upload page to list/refresh categories. */
    public function index()
    {
        return response()->json(
            DocumentCategory::orderBy('sort_order')->get(['id', 'name', 'slug', 'icon', 'color'])
        );
    }

    /** Admin management page: active categories + a link to the archive. */
    public function manage()
    {
        $categories = DocumentCategory::withCount('documents')->orderBy('sort_order')->orderBy('name')->get();
        $deletedCount = DocumentCategory::onlyTrashed()->count();

        return view('document-categories.manage', compact('categories', 'deletedCount'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'icon' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        $category = DocumentCategory::create($data + [
            'sort_order' => (int) DocumentCategory::max('sort_order') + 1,
        ]);

        if ($request->wantsJson()) {
            return response()->json($category, 201);
        }

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, DocumentCategory $documentCategory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'icon' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        // Regenerate the slug when the name changes (kept unique).
        if ($data['name'] !== $documentCategory->name) {
            $data['slug'] = DocumentCategory::uniqueSlug($data['name'], $documentCategory->id);
        }

        $documentCategory->update($data);

        return back()->with('success', 'Category updated.');
    }

    /** Archive a category; its documents fall back to uncategorized. */
    public function destroy(DocumentCategory $documentCategory)
    {
        $documentCategory->documents()->update(['category_id' => null]);
        $documentCategory->delete();

        return back()->with('success', 'Category archived. Its documents are now uncategorized — restore the category anytime.');
    }

    /** Archived (soft-deleted) categories. */
    public function deleted()
    {
        $categories = DocumentCategory::onlyTrashed()->orderBy('name')->get();

        return view('document-categories.deleted', compact('categories'));
    }

    public function restore(int $documentCategory)
    {
        $cat = DocumentCategory::onlyTrashed()->findOrFail($documentCategory);
        $cat->restore();

        return back()->with('success', 'Category restored.');
    }

    public function forceDelete(int $documentCategory)
    {
        $cat = DocumentCategory::onlyTrashed()->findOrFail($documentCategory);
        $cat->forceDelete();

        return redirect()->route('document-categories.deleted')->with('success', 'Category permanently deleted.');
    }
}
