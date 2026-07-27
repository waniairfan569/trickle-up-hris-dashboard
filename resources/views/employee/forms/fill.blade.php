@extends('layouts.hr-app')

@section('title', 'Fill · ' . $form->title)
@section('breadcrumb', 'My Forms')

@section('content')
<style>[x-cloak]{display:none!important} .sigpad{touch-action:none}</style>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

@php
    $inputFields = $form->fields->filter(fn ($f) => $f->isInputField());
    $requiredKeys = $inputFields->where('is_required', true)->pluck('field_key')->values();
@endphp

<div class="max-w-2xl mx-auto space-y-5" x-data="formFiller(@js($requiredKeys->all()))" x-init="init()">
    <a href="{{ route('my-forms.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> My Forms</a>

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $form->title }}</h1>
            @if($submission->period)<span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 mt-1"><i data-lucide="calendar" class="h-3 w-3 mr-1"></i>{{ $submission->periodLabel() }} submission</span>@endif
            @if($form->description)<p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $form->description }}</p>@endif
            @if($form->deadline)
                <p class="text-xs mt-2 {{ $form->isOverdue() ? 'text-amber-600 font-semibold' : 'text-slate-400' }}">
                    <i data-lucide="clock" class="h-3.5 w-3.5 inline -mt-0.5"></i> Due {{ $form->deadline->format('d M Y, H:i') }}{{ $form->isOverdue() ? ' · deadline passed (you can still submit)' : '' }}
                </p>
            @endif
            @if($form->show_progress_bar && $requiredKeys->count())
                <div class="mt-3">
                    <div class="flex justify-between text-[11px] font-semibold text-slate-400 mb-1"><span x-text="filled + ' of ' + total + ' required fields completed'"></span><span x-text="pct + '%'"></span></div>
                    <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden"><div class="h-full bg-brand-500 transition-all" :style="`width:${pct}%`"></div></div>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('forms.submit', $form) }}" enctype="multipart/form-data" @submit="return prepare($event)" x-ref="theForm">
            @csrf
            <input type="hidden" name="signature_data" x-ref="formSig">
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5" @input="recount()" @change="recount()">
                @foreach($form->fields as $field)
                    @php
                        $col = $field->width === 'half' ? 'sm:col-span-1' : 'sm:col-span-2';
                        $name = 'fields[' . $field->field_key . ']';
                        $val = $existing[$field->field_key] ?? null;
                        $isReq = $field->is_required;
                        $base = 'w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white';
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
                                    <textarea name="{{ $name }}" rows="3" placeholder="{{ $field->placeholder }}" data-key="{{ $field->field_key }}" {{ $isReq ? 'data-req' : '' }} class="{{ $base }} resize-none">{{ $val }}</textarea>
                                    @break
                                @case('dropdown')
                                    <select name="{{ $name }}" data-key="{{ $field->field_key }}" {{ $isReq ? 'data-req' : '' }} class="{{ $base }}">
                                        <option value="">— Select —</option>
                                        @foreach($field->options ?? [] as $opt)<option value="{{ $opt }}" @selected($val === $opt)>{{ $opt }}</option>@endforeach
                                    </select>
                                    @break
                                @case('multi_select')
                                    @php $selected = is_array(json_decode((string) $val, true)) ? json_decode($val, true) : []; @endphp
                                    <div class="space-y-1.5" data-key="{{ $field->field_key }}" {{ $isReq ? 'data-req-group' : '' }}>
                                        @foreach($field->options ?? [] as $opt)
                                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="{{ $name }}[]" value="{{ $opt }}" @checked(in_array($opt, $selected)) class="rounded border-slate-300 text-brand-600"> {{ $opt }}</label>
                                        @endforeach
                                    </div>
                                    @break
                                @case('radio')
                                    <div class="space-y-1.5" data-key="{{ $field->field_key }}" {{ $isReq ? 'data-req-group' : '' }}>
                                        @foreach($field->options ?? [] as $opt)
                                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="radio" name="{{ $name }}" value="{{ $opt }}" @checked($val === $opt) class="border-slate-300 text-brand-600"> {{ $opt }}</label>
                                        @endforeach
                                    </div>
                                    @break
                                @case('checkbox')
                                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($val === '1') data-key="{{ $field->field_key }}" class="rounded border-slate-300 text-brand-600"> Yes</label>
                                    @break
                                @case('file_upload')
                                    <input type="file" name="{{ $name }}" data-key="{{ $field->field_key }}" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                                    @if($val)<p class="text-[11px] text-slate-400 mt-1">Current: {{ basename($val) }}</p>@endif
                                    @break
                                @case('signature')
                                    <div class="rounded-xl border border-slate-300 dark:border-slate-600 relative bg-white">
                                        <canvas class="sig-canvas w-full" data-key="{{ $field->field_key }}" height="140"></canvas>
                                        <button type="button" class="sig-clear absolute bottom-2 left-2 text-xs font-semibold text-slate-400 hover:text-rose-500">Clear</button>
                                    </div>
                                    <input type="hidden" name="{{ $name }}" class="sig-input" data-key="{{ $field->field_key }}" value="{{ $val }}">
                                    @break
                                @case('date')
                                    <input type="date" name="{{ $name }}" value="{{ $val }}" data-key="{{ $field->field_key }}" {{ $isReq ? 'data-req' : '' }} class="{{ $base }}">
                                    @break
                                @default
                                    <input type="{{ in_array($field->type, ['number','email']) ? $field->type : ($field->type === 'phone' ? 'tel' : 'text') }}" name="{{ $name }}" value="{{ $val }}" placeholder="{{ $field->placeholder }}" data-key="{{ $field->field_key }}" {{ $isReq ? 'data-req' : '' }} class="{{ $base }}">
                            @endswitch

                            @if($field->help_text)<p class="text-[11px] text-slate-400 mt-1">{{ $field->help_text }}</p>@endif
                        </div>
                    @endif
                @endforeach

                @if($form->requires_signature)
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Signature <span class="text-rose-500">*</span></label>
                        <div class="rounded-xl border border-slate-300 dark:border-slate-600 relative bg-white">
                            <canvas id="form-signature" class="w-full sigpad" height="160"></canvas>
                            <button type="button" id="form-sig-clear" class="absolute bottom-2 left-2 text-xs font-semibold text-slate-400 hover:text-rose-500">Clear</button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Sign above. By signing you confirm this is your electronic signature.</p>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                <button type="button" @click="saveProgress()" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><span x-text="savedLabel">Save progress</span></button>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Submit form</button>
            </div>
        </form>
    </div>
</div>

<script>
    function formFiller(requiredKeys) {
        return {
            requiredKeys: requiredKeys || [],
            total: (requiredKeys || []).length,
            filled: 0,
            pct: 0,
            savedLabel: 'Save progress',
            pads: [],
            formPad: null,

            init() {
                // Init per-field signature canvases.
                document.querySelectorAll('.sig-canvas').forEach((c) => {
                    c.width = c.offsetWidth;
                    const pad = new SignaturePad(c, { penColor: '#0f172a' });
                    const input = c.parentElement.parentElement.querySelector('.sig-input');
                    if (input && input.value && input.value.startsWith('data:image')) { try { pad.fromDataURL(input.value); } catch (e) {} }
                    c.parentElement.querySelector('.sig-clear')?.addEventListener('click', () => { pad.clear(); if (input) input.value = ''; });
                    this.pads.push({ pad, input });
                });
                // Form-level signature.
                const fc = document.getElementById('form-signature');
                if (fc) {
                    fc.width = fc.offsetWidth;
                    this.formPad = new SignaturePad(fc, { penColor: '#0f172a' });
                    document.getElementById('form-sig-clear')?.addEventListener('click', () => this.formPad.clear());
                }
                this.recount();
            },

            syncSignatures() {
                this.pads.forEach(({ pad, input }) => { if (input) input.value = pad && !pad.isEmpty() ? pad.toDataURL('image/png') : (input.value || ''); });
                if (this.formPad && this.$refs.formSig) { this.$refs.formSig.value = this.formPad.isEmpty() ? '' : this.formPad.toDataURL('image/png'); }
            },

            recount() {
                const form = this.$refs.theForm;
                let count = 0;
                this.requiredKeys.forEach((key) => {
                    const single = form.querySelector(`[data-key="${key}"][data-req], [data-key="${key}"][data-req-group]`) || form.querySelector(`[data-key="${key}"]`);
                    // groups (radio/multiselect)
                    const group = form.querySelector(`[data-req-group][data-key="${key}"]`);
                    if (group) { if (group.querySelector('input:checked')) count++; return; }
                    if (single) {
                        if (single.type === 'checkbox') { if (single.checked) count++; }
                        else if (single.value && single.value.trim() !== '') count++;
                    }
                });
                this.filled = count;
                this.pct = this.total ? Math.round(count / this.total * 100) : 100;
            },

            saveProgress() {
                this.syncSignatures();
                const fd = new FormData(this.$refs.theForm);
                this.savedLabel = 'Saving…';
                fetch('{{ route('forms.save', $form) }}', { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                    .then((r) => r.json()).then((d) => { this.savedLabel = 'Saved ✓'; setTimeout(() => this.savedLabel = 'Save progress', 1500); })
                    .catch(() => { this.savedLabel = 'Save failed'; setTimeout(() => this.savedLabel = 'Save progress', 1500); });
            },

            prepare(e) {
                this.syncSignatures();
                @if($form->requires_signature)
                if (this.formPad && this.formPad.isEmpty()) { e.preventDefault(); alert('Please add your signature before submitting.'); return false; }
                @endif
                return true;
            },
        };
    }
</script>
@endsection
