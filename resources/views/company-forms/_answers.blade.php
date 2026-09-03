{{-- Read-only answers for one submission. Expects $submission (with form.fields + responses loaded). --}}
@php $byKey = $submission->responses->keyBy('field_key'); @endphp
<div class="space-y-4">
    @forelse($submission->form->fields as $field)
        @if(!$field->isInputField())
            @if($field->type === 'heading')
                <h4 class="text-sm font-bold text-slate-800 dark:text-white pt-1">{{ $field->label }}</h4>
            @elseif($field->type === 'paragraph')
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $field->label }}</p>
            @else
                <hr class="border-slate-100 dark:border-slate-700">
            @endif
        @else
            @php $resp = $byKey->get($field->field_key); @endphp
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $field->label }}</p>
                @if($field->type === 'signature' && $resp && str_starts_with((string) $resp->value, 'data:image'))
                    <img src="{{ $resp->value }}" alt="signature" class="mt-1 h-14 border border-slate-200 rounded bg-white dark:border-slate-600">
                @elseif($resp && $resp->isFile())
                    <a href="{{ route('forms.response.download', $resp) }}" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:underline"><i data-lucide="paperclip" class="h-3.5 w-3.5"></i> {{ $resp->getDisplayValue() }}</a>
                @else
                    <p class="text-sm font-semibold text-slate-800 dark:text-white mt-0.5 whitespace-pre-line">{{ $resp ? $resp->getDisplayValue() : '—' }}</p>
                @endif
            </div>
        @endif
    @empty
        <p class="text-sm text-slate-400">This form has no fields.</p>
    @endforelse

    @if($submission->signature_data && str_starts_with($submission->signature_data, 'data:image'))
        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Signature</p>
            <img src="{{ $submission->signature_data }}" alt="signature" class="mt-1 h-14 border border-slate-200 rounded bg-white dark:border-slate-600">
        </div>
    @endif
</div>
