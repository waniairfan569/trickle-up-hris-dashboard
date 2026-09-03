@extends('layouts.hr-app')

@section('title', 'Form Builder · ' . $form->title)
@section('breadcrumb', 'Company Forms')

@php
    $typeLabels = [
        'text' => 'Text', 'textarea' => 'Paragraph text', 'number' => 'Number', 'email' => 'Email',
        'phone' => 'Phone', 'date' => 'Date', 'dropdown' => 'Dropdown', 'multi_select' => 'Multi-select',
        'checkbox' => 'Checkbox', 'radio' => 'Radio', 'file_upload' => 'File upload',
        'heading' => 'Heading', 'paragraph' => 'Paragraph (text block)', 'divider' => 'Divider', 'signature' => 'Signature',
    ];
    $optionTypes = ['dropdown', 'multi_select', 'radio'];
    $layoutTypes = ['heading', 'paragraph', 'divider'];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <a href="{{ route('company-forms.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All forms</a>
        <div class="flex items-center gap-2">
            <a href="{{ route('company-forms.preview', $form) }}" target="_blank" class="rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="eye" class="h-4 w-4 inline -mt-0.5"></i> Preview</a>
            <a href="{{ route('company-forms.show', $form) }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Assign</a>
            <a href="{{ route('company-forms.responses', $form) }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Responses</a>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <!-- Form settings -->
    <form action="{{ route('company-forms.update', $form) }}" method="POST" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
        @csrf @method('PUT')
        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Form details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title</label><input type="text" name="title" value="{{ $form->title }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                <select name="status" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach(['draft' => 'Draft', 'active' => 'Active (employees can fill)', 'closed' => 'Closed'] as $v => $l)<option value="{{ $v }}" @selected($form->status === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2"><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label><textarea name="description" rows="2" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none">{{ $form->description }}</textarea></div>
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Deadline</label><input type="datetime-local" name="deadline" value="{{ optional($form->deadline)->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <div class="flex flex-wrap items-center gap-4 pt-6">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="requires_signature" value="1" @checked($form->requires_signature) class="rounded border-slate-300 text-brand-600"> Require signature</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="allow_multiple_submissions" value="1" @checked($form->allow_multiple_submissions) class="rounded border-slate-300 text-brand-600"> Allow multiple</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200" title="Employees re-submit this form every month; each month is kept as separate history and they're notified when a new month opens."><input type="checkbox" name="is_monthly" value="1" @checked($form->is_monthly) class="rounded border-slate-300 text-brand-600"> Monthly (re-submit each month)</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="show_progress_bar" value="1" @checked($form->show_progress_bar) class="rounded border-slate-300 text-brand-600"> Progress bar</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="is_anonymous" value="1" @checked($form->is_anonymous) class="rounded border-slate-300 text-brand-600"> Anonymous</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200" title="Shows a quick 'Overtime Approval' button + modal on the Time-Off page so employees can log one or more overtime entries. Only one form can be the overtime form."><input type="checkbox" name="is_overtime_form" value="1" @checked($form->system_key === 'overtime') class="rounded border-slate-300 text-brand-600"> <i data-lucide="clock" class="h-3.5 w-3.5 text-amber-500"></i> Overtime form (show on Time-Off)</label>
            </div>
        </div>
        <div class="flex justify-end"><button type="submit" class="rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-900 dark:bg-slate-700">Save details</button></div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        <!-- Fields list -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Fields ({{ $form->fields->count() }})</h2></div>
            @forelse($form->fields as $field)
                <div class="flex items-center gap-3 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60" x-data="{ edit: false }">
                    <div class="flex flex-col">
                        <form action="{{ route('company-forms.fields.move', $field) }}" method="POST">@csrf<input type="hidden" name="direction" value="up"><button class="text-slate-300 hover:text-slate-600" @if($loop->first) disabled @endif><i data-lucide="chevron-up" class="h-3.5 w-3.5"></i></button></form>
                        <form action="{{ route('company-forms.fields.move', $field) }}" method="POST">@csrf<input type="hidden" name="direction" value="down"><button class="text-slate-300 hover:text-slate-600" @if($loop->last) disabled @endif><i data-lucide="chevron-down" class="h-3.5 w-3.5"></i></button></form>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $field->label }}</p>
                        <p class="text-xs text-slate-400">
                            <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ $typeLabels[$field->type] ?? $field->type }}</span>
                            @if($field->is_required)<span class="text-rose-500 font-bold ml-1">required</span>@endif
                            @if($field->width === 'half')<span class="text-slate-400 ml-1">· half width</span>@endif
                        </p>
                    </div>
                    <button @click="edit = !edit" class="text-slate-400 hover:text-slate-700" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                    <form action="{{ route('company-forms.fields.delete', $field) }}" method="POST" onsubmit="return confirm('Delete this field?');">@csrf @method('DELETE')<button class="text-slate-400 hover:text-rose-500" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>

                    <!-- Edit modal -->
                    <div x-show="edit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-slate-900/50" @click="edit = false"></div>
                        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-slate-800 text-left">
                            <form action="{{ route('company-forms.fields.update', $field) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h3 class="text-base font-extrabold text-slate-900 dark:text-white">Edit field</h3><button type="button" @click="edit = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button></div>
                                <div class="p-6 space-y-3">
                                    <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Label</label><input type="text" name="label" value="{{ $field->label }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                    @if(!in_array($field->type, $layoutTypes))
                                        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Placeholder</label><input type="text" name="placeholder" value="{{ $field->placeholder }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Help text</label><input type="text" name="help_text" value="{{ $field->help_text }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                        @if(in_array($field->type, $optionTypes))
                                            <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Options (one per line)</label><textarea name="options_raw" rows="3" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ implode("\n", $field->options ?? []) }}</textarea></div>
                                        @endif
                                        <div class="flex items-center justify-between">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="is_required" value="1" @checked($field->is_required) class="rounded border-slate-300 text-brand-600"> Required</label>
                                            <select name="width" class="rounded-lg border-slate-300 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white"><option value="full" @selected($field->width==='full')>Full width</option><option value="half" @selected($field->width==='half')>Half width</option></select>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60"><button type="button" @click="edit = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button><button type="submit" class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700">Save</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-6 py-10 text-center text-sm text-slate-400">No fields yet. Add your first field on the right.</p>
            @endforelse
        </div>

        <!-- Add field -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 self-start" x-data="{ type: 'text', optionTypes: @js($optionTypes), layoutTypes: @js($layoutTypes) }">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-3">Add field</h2>
            <form action="{{ route('company-forms.fields.add', $form) }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type</label>
                    <select name="type" x-model="type" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach($typeLabels as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Label</label><input type="text" name="label" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <template x-if="!layoutTypes.includes(type)">
                    <div class="space-y-3">
                        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Placeholder</label><input type="text" name="placeholder" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Help text</label><input type="text" name="help_text" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                        <template x-if="optionTypes.includes(type)">
                            <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Options (one per line)</label><textarea name="options_raw" rows="3" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea></div>
                        </template>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="is_required" value="1" class="rounded border-slate-300 text-brand-600"> Required</label>
                    </div>
                </template>
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="plus" class="h-4 w-4 inline -mt-0.5"></i> Add field</button>
            </form>
        </div>
    </div>
</div>
@endsection
