<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use Illuminate\Http\Request;

class DocumentCategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            DocumentCategory::orderBy('sort_order')->get(['id', 'name', 'slug', 'icon', 'color'])
        );
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
}
