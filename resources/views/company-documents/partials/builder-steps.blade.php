{{--
    Guided 4-step signature-builder progress bar for a company document.
    Params:
      $template : DocumentTemplate backing the company document
      $current  : int 1..4  (1 Upload · 2 Place fields · 3 Preview · 4 Send)
    Renders nothing unless the template is backed by a company document.
--}}
@php
    $__companyDoc = isset($template) && $template
        ? \App\Models\CompanyDocument::where('template_id', $template->id)->first()
        : null;
@endphp

@if($__companyDoc)
    @php
        $current = $current ?? 1;
        $isPdf = method_exists($template, 'isPdf') ? $template->isPdf() : true;
        $steps = [
            1 => ['label' => 'Upload',       'icon' => 'upload',       'url' => route('company-documents.edit', $__companyDoc)],
            2 => ['label' => 'Place fields', 'icon' => 'layout-grid',  'url' => route('document-templates.edit', $template)],
            3 => ['label' => 'Preview',      'icon' => 'eye',          'url' => route('document-templates.preview-view', $template)],
            4 => ['label' => 'Send',         'icon' => 'send',         'url' => route('document-templates.send-form', $template)],
        ];
    @endphp
    <nav class="mb-6 rounded-2xl border border-slate-200/80 bg-white px-3 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <ol class="flex items-center gap-1 sm:gap-2">
            @foreach($steps as $i => $s)
                @php
                    $done = $i < $current;
                    $active = $i === $current;
                @endphp
                <li class="flex-1 min-w-0">
                    <a href="{{ $s['url'] }}"
                       class="group flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition {{ $active ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50' }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-extrabold
                            {{ $active ? 'bg-brand-500 text-slate-900' : ($done ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-400') }}">
                            @if($done)
                                <i data-lucide="check" class="h-4 w-4"></i>
                            @else
                                {{ $i }}
                            @endif
                        </span>
                        <span class="min-w-0 hidden sm:block">
                            <span class="block text-[10px] font-bold uppercase tracking-wider {{ $active ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400' }}">Step {{ $i }}</span>
                            <span class="block text-sm font-bold truncate {{ $active ? 'text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300' }}">{{ $s['label'] }}</span>
                        </span>
                        <span class="sm:hidden text-xs font-bold {{ $active ? 'text-slate-900 dark:text-white' : 'text-slate-500' }}">{{ $s['label'] }}</span>
                    </a>
                </li>
                @if(!$loop->last)
                    <li class="shrink-0 text-slate-300 dark:text-slate-600"><i data-lucide="chevron-right" class="h-4 w-4"></i></li>
                @endif
            @endforeach
        </ol>
        @unless($isPdf)
            <p class="mt-2 px-2.5 text-[11px] text-amber-600 dark:text-amber-400"><i data-lucide="alert-triangle" class="h-3.5 w-3.5 inline -mt-0.5"></i> Field placement &amp; signing need a PDF — re-upload a PDF version to use every step.</p>
        @endunless
    </nav>
@endif
