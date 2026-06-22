@extends('layouts.hr-app')

@section('title', 'Submission · ' . optional($submission->form)->title)
@section('breadcrumb', 'Company Forms')

@php
    $employeeView = $employeeView ?? false;
    $byKey = $submission->responses->keyBy('field_key');
    $back = $employeeView ? route('my-forms.index') : route('company-forms.responses', $submission->form);
@endphp

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back</a>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
            <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ optional($submission->form)->title }}</h1>
            <p class="text-xs text-slate-400 mt-1">
                @unless(optional($submission->form)->is_anonymous) By {{ optional($submission->employee)->full_name ?? '—' }} · @endunless
                {{ $submission->status === 'submitted' ? 'Submitted ' . optional($submission->submitted_at)->format('d M Y, H:i') : 'Not submitted' }}
            </p>
        </div>
        <div class="p-6 space-y-5">
            @forelse($submission->form->fields as $field)
                @if(!$field->isInputField())
                    @if($field->type === 'heading')<h3 class="text-base font-bold text-slate-800 dark:text-white pt-2">{{ $field->label }}</h3>
                    @elseif($field->type === 'paragraph')<p class="text-sm text-slate-500 dark:text-slate-400">{{ $field->label }}</p>
                    @else<hr class="border-slate-100 dark:border-slate-700">@endif
                @else
                    @php $resp = $byKey->get($field->field_key); @endphp
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $field->label }}</p>
                        @if($field->type === 'signature' && $resp && str_starts_with((string) $resp->value, 'data:image'))
                            <img src="{{ $resp->value }}" alt="signature" class="mt-1 h-16 border border-slate-200 rounded bg-white dark:border-slate-600">
                        @elseif($resp && $resp->isFile())
                            <a href="{{ route('forms.response.download', $resp) }}" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:underline"><i data-lucide="paperclip" class="h-3.5 w-3.5"></i> {{ $resp->getDisplayValue() }}</a>
                        @else
                            <p class="text-sm font-semibold text-slate-800 dark:text-white mt-0.5">{{ $resp ? $resp->getDisplayValue() : '—' }}</p>
                        @endif
                    </div>
                @endif
            @empty
                <p class="text-sm text-slate-400">This form has no fields.</p>
            @endforelse

            @if($submission->signature_data && str_starts_with($submission->signature_data, 'data:image'))
                <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Signature</p>
                    <img src="{{ $submission->signature_data }}" alt="signature" class="mt-1 h-16 border border-slate-200 rounded bg-white dark:border-slate-600">
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
