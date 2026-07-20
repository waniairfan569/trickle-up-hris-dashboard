<?php

namespace App\Http\Controllers;

use App\Models\CompanyFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyFileController extends Controller
{
    /** Shared company file library — visible to everyone. */
    public function index(Request $request)
    {
        $files = CompanyFile::with('uploader:id,first_name,last_name')
            ->latest()
            ->get();

        return view('files.index', compact('files'));
    }

    /** Upload a company file (Super Admin / HR Admin only). */
    public function store(Request $request)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to upload company files.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'file' => 'required|file|max:20480', // 20 MB
        ]);

        $file = $request->file('file');
        $path = $file->store(\App\Tenancy\TenantStorage::path('company-files')); // private 'local' disk

        CompanyFile::create([
            'title' => $validated['title'],
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'description' => $validated['description'] ?? null,
            'uploaded_by' => $auth->id,
        ]);

        return back()->with('success', 'File uploaded to the company library.');
    }

    /** Download a file (any authenticated user). */
    public function download(CompanyFile $file)
    {
        if (!Storage::exists($file->path)) {
            abort(404, 'File not found.');
        }

        return Storage::download($file->path, $file->original_name);
    }

    /** Delete a file (Super Admin / HR Admin only). */
    public function destroy(Request $request, CompanyFile $file)
    {
        $auth = $request->user();
        if (!$auth || !$auth->isAdmin()) {
            abort(403, 'Forbidden: you do not have permission to delete company files.');
        }

        Storage::delete($file->path);
        $file->delete();

        return back()->with('success', 'File deleted.');
    }
}
