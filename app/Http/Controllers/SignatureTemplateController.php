<?php

namespace App\Http\Controllers;

use App\Models\SignatureTemplate;
use Illuminate\Http\Request;

class SignatureTemplateController extends Controller
{
    /** Library page: saved signatures + a create pad. */
    public function index()
    {
        $templates = SignatureTemplate::with('creator')->latest()->get();

        return view('signature-templates.index', compact('templates'));
    }

    /** Save a new signature (drawn / typed / uploaded → PNG data URL). */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'image_data' => ['required', 'string', 'starts_with:data:image/', 'max:6000000'],
        ], [
            'image_data.required' => 'Please draw, type or upload a signature.',
        ]);

        SignatureTemplate::create([
            'name' => trim($validated['name']),
            'image_data' => $validated['image_data'],
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Signature saved.');
    }

    public function destroy(SignatureTemplate $signatureTemplate)
    {
        $signatureTemplate->delete();

        return back()->with('success', 'Signature deleted.');
    }
}
