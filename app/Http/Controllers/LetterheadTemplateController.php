<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use App\Models\LetterheadTemplate;
use App\Tenancy\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Manage branded letterheads used to wrap generated HR documents. */
class LetterheadTemplateController extends Controller
{
    public function index()
    {
        // Give a brand-new workspace a starter to edit, so the page is never empty.
        if (LetterheadTemplate::count() === 0) {
            LetterheadTemplate::starterFromWorkspace(auth()->id());
        }

        $letterheads = LetterheadTemplate::orderByDesc('is_default')->orderBy('name')->get();

        return view('letterheads.index', compact('letterheads'));
    }

    public function create()
    {
        return view('letterheads.form', [
            'letterhead' => new LetterheadTemplate(LetterheadTemplate::starterDefaults()),
            'entities'   => $this->entities(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->applyUploads($request, $this->validated($request));
        $data['created_by'] = auth()->id();

        $letterhead = LetterheadTemplate::create($data);

        if ($request->boolean('is_default') || LetterheadTemplate::count() === 1) {
            $letterhead->makeDefault();
        }

        return redirect()->route('letterheads.index')->with('success', "Letterhead “{$letterhead->name}” created.");
    }

    public function edit(LetterheadTemplate $letterhead)
    {
        return view('letterheads.form', ['letterhead' => $letterhead, 'entities' => $this->entities()]);
    }

    public function update(Request $request, LetterheadTemplate $letterhead)
    {
        $data = $this->applyUploads($request, $this->validated($request));

        $letterhead->update($data);

        if ($request->boolean('is_default')) {
            $letterhead->makeDefault();
        }

        return redirect()->route('letterheads.index')->with('success', "Letterhead “{$letterhead->name}” updated.");
    }

    public function destroy(LetterheadTemplate $letterhead)
    {
        $wasDefault = $letterhead->is_default;
        $letterhead->delete();

        // Keep one default around if we removed it.
        if ($wasDefault && ($next = LetterheadTemplate::orderBy('id')->first())) {
            $next->makeDefault();
        }

        return redirect()->route('letterheads.index')->with('success', 'Letterhead deleted.');
    }

    public function setDefault(LetterheadTemplate $letterhead)
    {
        $letterhead->makeDefault();

        return back()->with('success', "“{$letterhead->name}” is now the default letterhead.");
    }

    /** Inline PDF preview of the letterhead on a sample page. */
    public function preview(LetterheadTemplate $letterhead)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(60);

        $pdf = Pdf::loadView('letterheads.preview', ['letterhead' => $letterhead])->setPaper('a4', 'portrait');
        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="letterhead-preview.pdf"',
        ]);
    }

    private function entities()
    {
        return CompanyEntity::orderByDesc('is_primary')->orderBy('name')->get(['id', 'name', 'legal_name']);
    }

    /** Apply the logo / header-band / footer-band image uploads (and their remove flags). */
    private function applyUploads(Request $request, array $data): array
    {
        foreach (['logo' => 'logo_path', 'header_image' => 'header_image_path', 'footer_image' => 'footer_image_path', 'watermark_image' => 'watermark_image_path'] as $input => $column) {
            if ($request->boolean('remove_' . $input)) {
                $data[$column] = null;
            } elseif ($request->hasFile($input)) {
                $data[$column] = $request->file($input)->store(TenantStorage::path('letterheads'), 'public');
            }
        }

        return $data;
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'name'              => 'required|string|max:100',
            'company_name'      => 'required|string|max:120',
            'company_suffix'    => 'nullable|string|max:120',
            'company_entity_id' => 'nullable|exists:company_entities,id',
            'address'           => 'nullable|string|max:400',
            'email'             => 'nullable|string|max:150',
            'website'           => 'nullable|string|max:150',
            'phone'             => 'nullable|string|max:60',
            'header_bg'         => 'nullable|regex:/^#([0-9a-fA-F]{6})$/',
            'header_accent'     => 'nullable|regex:/^#([0-9a-fA-F]{6})$/',
            'footer_bg'         => 'nullable|regex:/^#([0-9a-fA-F]{6})$/',
            'footer_text'       => 'nullable|regex:/^#([0-9a-fA-F]{6})$/',
            'logo'              => 'nullable|image|max:2048',
            'header_image'      => 'nullable|image|max:4096',
            'footer_image'      => 'nullable|image|max:4096',
            'watermark_image'   => 'nullable|image|max:4096',
            'watermark_opacity' => 'nullable|numeric|min:0|max:1',
        ]);

        return [
            'name'              => $request->input('name'),
            'company_name'      => $request->input('company_name'),
            'company_suffix'    => $request->input('company_suffix'),
            'company_entity_id' => $request->input('company_entity_id') ?: null,
            'address'           => $request->input('address'),
            'email'             => $request->input('email'),
            'website'           => $request->input('website'),
            'phone'             => $request->input('phone'),
            'header_bg'         => $request->input('header_bg') ?: '#FFFDF5',
            'header_accent'     => $request->input('header_accent') ?: '#fcd82f',
            'footer_bg'         => $request->input('footer_bg') ?: '#1F5FD6',
            'footer_text'       => $request->input('footer_text') ?: '#ffffff',
            'watermark_opacity' => $request->filled('watermark_opacity') ? (float) $request->input('watermark_opacity') : 0.06,
        ];
    }
}
