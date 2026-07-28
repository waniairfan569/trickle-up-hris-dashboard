<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProfileTemplate;
use App\Models\ProfileSection;
use App\Models\ProfileField;
use Illuminate\Support\Str;
use App\Models\User;

class ProfileTemplateController extends Controller
{
    public function index()
    {
        $templates = ProfileTemplate::withCount(['sections', 'sections as fields_count' => function ($query) {
            $query->join('profile_fields', 'profile_sections.id', '=', 'profile_fields.section_id');
        }])->get();

        return view('profile-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('profile-templates.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $slug = Str::slug($request->name);
        
        // Ensure unique slug
        $count = ProfileTemplate::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        ProfileTemplate::create([
            'name' => $request->name,
            'slug' => $slug,
            'type' => 'dynamic',
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('profile-templates.index')->with('success', 'Profile template created successfully.');
    }

    public function show(ProfileTemplate $profile_template)
    {
        $profile_template->load('sections.fields');
        $isDefaultTemplate = $profile_template->type === 'default';

        return view('profile-templates.show', compact('profile_template', 'isDefaultTemplate'));
    }

    public function edit(ProfileTemplate $profile_template)
    {
        if ($profile_template->type === 'default') {
            abort(403, 'Cannot edit default template structure directly.');
        }

        return view('profile-templates.edit', compact('profile_template'));
    }

    public function update(Request $request, ProfileTemplate $profile_template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $profile_template->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('profile-templates.index')->with('success', 'Profile template updated successfully.');
    }

    public function destroy(ProfileTemplate $profile_template)
    {
        if ($profile_template->type === 'default') {
            abort(403, 'Cannot delete the default template.');
        }

        $profile_template->delete();

        return redirect()->route('profile-templates.index')->with('success', 'Profile template deleted successfully.');
    }

    public function assign(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:profile_templates,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $template = ProfileTemplate::findOrFail($request->template_id);

        if ($template->type === 'default') {
            return back()->with('error', 'The default template applies to all employees automatically and cannot be assigned individually.');
        }

        $authId = auth()->id();
        $now = now();

        $pivots = [];
        foreach ($request->user_ids as $userId) {
            $pivots[$userId] = [
                'assigned_by' => $authId,
                'assigned_at' => $now,
            ];
        }

        $template->employees()->syncWithoutDetaching($pivots);

        return back()->with('success', 'Template assigned successfully.');
    }

    public function unassign(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:profile_templates,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $template = ProfileTemplate::findOrFail($request->template_id);
        
        if ($template->type === 'default') {
            abort(403, 'Cannot unassign the default template.');
        }

        $template->employees()->detach($request->user_id);

        return back()->with('success', 'Template unassigned successfully.');
    }

    public function storeSection(Request $request, ProfileTemplate $profile_template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:255',
        ]);

        $slug = Str::slug($request->name);
        $count = ProfileSection::where('template_id', $profile_template->id)
            ->where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        $maxSort = ProfileSection::where('template_id', $profile_template->id)->max('sort_order') ?? 0;

        ProfileSection::create([
            'template_id' => $profile_template->id,
            'name' => $request->name,
            'slug' => $slug,
            'icon' => $request->icon,
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('success', 'Section added successfully.');
    }

    public function updateSection(Request $request, ProfileSection $section)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:255',
        ]);

        $section->update([
            'name' => $request->name,
            'icon' => $request->icon,
        ]);

        return back()->with('success', 'Section updated successfully.');
    }

    public function destroySection(ProfileSection $section)
    {
        if ($section->template->type === 'default') {
            abort(403, 'Cannot delete sections from default template.');
        }

        $section->delete();

        return back()->with('success', 'Section deleted successfully.');
    }

    public function storeField(Request $request, ProfileSection $section)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255|unique:profile_fields,key',
            'type' => 'required|string|in:text,textarea,number,date,date_range,dropdown,multi_select,checkbox,phone,email,url,file,currency,employee_lookup,department_lookup',
            'options' => 'nullable|array',
            'placeholder' => 'nullable|string|max:255',
            'is_required' => 'boolean',
            'is_encrypted' => 'boolean',
            'employee_can_edit' => 'boolean',
            'visibility' => 'required|string|in:public,internal,private,manager',
        ]);

        $isEncrypted = $request->boolean('is_encrypted');
        if (!in_array($request->type, ['text', 'textarea', 'number'])) {
            $isEncrypted = false;
        }

        $maxSort = ProfileField::where('section_id', $section->id)->max('sort_order') ?? 0;

        ProfileField::create([
            'section_id' => $section->id,
            'name' => $request->name,
            'key' => $request->key,
            'type' => $request->type,
            'options' => $request->options,
            'placeholder' => $request->placeholder,
            'is_required' => $request->boolean('is_required'),
            'is_system' => false,
            'is_encrypted' => $isEncrypted,
            'visibility' => $request->visibility,
            'employee_can_edit' => $request->boolean('employee_can_edit'),
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('success', 'Field added successfully.');
    }

    public function updateField(Request $request, ProfileField $field)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'options' => 'nullable|array',
            'placeholder' => 'nullable|string|max:255',
            'is_required' => 'boolean',
            'employee_can_edit' => 'boolean',
            'visibility' => 'required|string|in:public,internal,private,manager',
        ]);

        $field->update([
            'name' => $request->name,
            'options' => $request->options,
            'placeholder' => $request->placeholder,
            'is_required' => $request->boolean('is_required'),
            'employee_can_edit' => $request->boolean('employee_can_edit'),
            'visibility' => $request->visibility,
        ]);

        return back()->with('success', 'Field updated successfully.');
    }

    public function destroyField(ProfileField $field)
    {
        if ($field->is_system) {
            abort(403, 'System fields cannot be deleted.');
        }

        $field->delete();

        return back()->with('success', 'Field deleted successfully.');
    }
}
