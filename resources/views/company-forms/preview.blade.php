@extends('layouts.hr-app')

@section('title', 'Preview · ' . $form->title)
@section('breadcrumb', 'Company Forms')

@section('content')
@php
    $statusBadge = ['draft' => 'bg-slate-100 text-slate-600', 'active' => 'bg-emerald-50 text-emerald-700', 'closed' => 'bg-rose-50 text-rose-700'];
@endphp

<div class="max-w-2xl mx-auto space-y-5">
    <div class="flex items-center justify-between">
        <a href="{{ route('company-forms.builder', $form) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to builder</a>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-700 px-3 py-1 text-xs font-bold dark:bg-amber-500/10"><i data-lucide="eye" class="h-3.5 w-3.5"></i> Preview — read only</span>
    </div>

    <p class="text-xs text-slate-400">This is exactly how employees see the form. Inputs are disabled here; nothing is saved.</p>

    <fieldset disabled class="contents">
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $form->title }}</h1>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusBadge[$form->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($form->status) }}</span>
            </div>
            @if($form->description)<p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $form->description }}</p>@endif
            @if($form->deadline)<p class="text-xs mt-2 text-slate-400"><i data-lucide="clock" class="h-3.5 w-3.5 inline -mt-0.5"></i> Due {{ $form->deadline->format('d M Y, H:i') }}</p>@endif
        </div>

        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
            @forelse($form->fields as $field)
                @php
                    $col = $field->width === 'half' ? 'sm:col-span-1' : 'sm:col-span-2';
                    $isReq = $field->is_required;
                    $base = 'w-full rounded-xl border-slate-300 text-sm bg-slate-50 dark:bg-slate-900 dark:border-slate-600 dark:text-white';
                @endphp

                @if($field->type === 'heading')
                    <div class="sm:col-span-2"><h3 class="text-base font-bold text-slate-800 dark:text-white">{{ $field->label }}</h3></div>
                @elseif($field->type === 'paragraph')
                    <div class="sm:col-span-2"><p class="text-sm text-slate-500 dark:text-slate-400">{{ $field->label }}</p></div>
                @elseif($field->type === 'divider')
                    <div class="sm:col-span-2"><hr class="border-slate-100 dark:border-slate-700"></div>
                @else
                    <div class="{{ $col }}">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">{{ $field->label }} @if($isReq)<span class="text-rose-500">*</span>@endif</label>

                        @switch($field->type)
                            @case('textarea')
                                <textarea rows="3" placeholder="{{ $field->placeholder ?: 'Your answer' }}" class="{{ $base }} resize-none"></textarea>
                                @break
                            @case('dropdown')
                                <select class="{{ $base }}"><option>— Select —</option>@foreach($field->options ?? [] as $opt)<option>{{ $opt }}</option>@endforeach</select>
                                @break
                            @case('multi_select')
                                <div class="space-y-1.5">@foreach($field->options ?? [] as $opt)<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" class="rounded border-slate-300 text-brand-600"> {{ $opt }}</label>@endforeach</div>
                                @break
                            @case('radio')
                                <div class="space-y-1.5">@foreach($field->options ?? [] as $opt)<label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="radio" class="border-slate-300 text-brand-600"> {{ $opt }}</label>@endforeach</div>
                                @break
                            @case('checkbox')
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" class="rounded border-slate-300 text-brand-600"> Yes</label>
                                @break
                            @case('file_upload')
                                <input type="file" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-500">
                                @break
                            @case('signature')
                                <div class="rounded-xl border border-slate-300 dark:border-slate-600 bg-white h-[140px] flex items-center justify-center text-xs text-slate-300">Signature pad</div>
                                @break
                            @case('date')
                                <input type="date" class="{{ $base }}">
                                @break
                            @default
                                <input type="{{ in_array($field->type, ['number','email']) ? $field->type : ($field->type === 'phone' ? 'tel' : 'text') }}" placeholder="{{ $field->placeholder ?: 'Your answer' }}" class="{{ $base }}">
                        @endswitch

                        @if($field->help_text)<p class="text-[11px] text-slate-400 mt-1">{{ $field->help_text }}</p>@endif
                    </div>
                @endif
            @empty
                <div class="sm:col-span-2 text-center py-10 text-sm text-slate-400">No fields yet. Add fields in the builder.</div>
            @endforelse

            @if($form->requires_signature)
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Signature <span class="text-rose-500">*</span></label>
                    <div class="rounded-xl border border-slate-300 dark:border-slate-600 bg-white h-[160px] flex items-center justify-center text-xs text-slate-300">Signature pad</div>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
            <span class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-400 dark:bg-slate-700">Save progress</span>
            <span class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600/60 px-6 py-2.5 text-sm font-bold text-slate-900"><i data-lucide="check" class="h-4 w-4"></i> Submit form</span>
        </div>
    </div>
    </fieldset>
</div>
@endsection
