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

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

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

    {{-- Review outcome (shown to both admin & employee once reviewed) --}}
    @if(in_array($submission->review_status, ['approved', 'rejected'], true))
        @php $approved = $submission->review_status === 'approved'; @endphp
        <div class="rounded-2xl border p-5 {{ $approved ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/5' : 'border-rose-200 bg-rose-50/50 dark:border-rose-500/20 dark:bg-rose-500/5' }}">
            <div class="flex items-center gap-2">
                <i data-lucide="{{ $approved ? 'check-circle' : 'x-circle' }}" class="h-5 w-5 {{ $approved ? 'text-emerald-600' : 'text-rose-600' }}"></i>
                <span class="text-sm font-extrabold {{ $approved ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300' }}">{{ $submission->reviewLabel() }}</span>
                <span class="text-[11px] text-slate-400 ml-auto">{{ optional($submission->reviewer)->full_name ?? 'HR' }} · {{ optional($submission->reviewed_at)->format('d M Y, H:i') }}</span>
            </div>
            @if($submission->review_note)
                <p class="text-sm text-slate-700 dark:text-slate-300 mt-2"><span class="font-bold">Suggestion:</span> {{ $submission->review_note }}</p>
            @endif
        </div>
    @endif

    {{-- Admin review panel --}}
    @if(!$employeeView && $submission->status === 'submitted')
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Review this response</h2>
            <p class="text-xs text-slate-400 mb-3">Add a suggestion and approve or reject. The employee is notified.</p>
            <form method="POST" action="{{ route('company-forms.submission.review', $submission) }}" class="space-y-3">
                @csrf
                <textarea name="review_note" rows="3" maxlength="2000" placeholder="Suggestion / feedback for the employee (optional)…" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ $submission->review_note }}</textarea>
                <div class="flex justify-end gap-2">
                    <button type="submit" name="action" value="reject" onclick="return confirm('Reject this response and notify the employee?')" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700"><i data-lucide="x" class="h-4 w-4"></i> Reject</button>
                    <button type="submit" name="action" value="approve" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"><i data-lucide="check" class="h-4 w-4"></i> Approve</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
