@extends('layouts.hr-app')

@section('title', 'Checks')
@section('breadcrumb', 'Checks')

@php
    $badge = [
        'valid'    => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'expiring' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'expired'  => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'pending'  => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'failed'   => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
    ];
    $reads = [
        'valid' => 'Valid', 'expiring' => 'Expiring soon', 'expired' => 'Expired',
        'pending' => 'Waiting', 'failed' => 'Not passed',
    ];
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Checks</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Right to work, DBS and anything else carried out on
                <span class="font-semibold">{{ $employee->full_name }}</span> — what it said, who says so,
                and when it stops counting.
            </p>
        </div>
        <a href="{{ route('employee-checks.index') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">
            All checks across everyone
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700/60">
            <thead class="bg-slate-50 dark:bg-slate-700/30">
                <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    <th class="px-5 py-3">Check</th>
                    <th class="px-5 py-3">Outcome</th>
                    <th class="px-5 py-3">Checked</th>
                    <th class="px-5 py-3">Valid until</th>
                    <th class="px-5 py-3">By</th>
                    <th class="px-5 py-3 text-right">Evidence</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($checks as $check)
                    @php $state = $check->state(); @endphp
                    <tr>
                        <td class="px-5 py-3">
                            <div class="text-sm font-semibold text-slate-800 dark:text-white">{{ $check->typeLabel() }}</div>
                            @if($check->reference)
                                <div class="text-[11px] text-slate-400">Ref {{ $check->reference }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge[$state] }}">{{ $reads[$state] }}</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ $check->checked_on?->format('j M Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ $check->expires_on?->format('j M Y') ?? 'Does not expire' }}
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ $check->verified_by_name ?: ($check->recorder?->full_name ?? '—') }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if($check->document)
                                <a href="{{ route('employees.documents.download', [$employee->id, $check->document->id]) }}"
                                   class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">Open</a>
                            @else
                                <span class="text-xs text-slate-400">none</span>
                            @endif
                            <form method="POST" action="{{ route('employee-checks.destroy', $check) }}" class="inline"
                                  onsubmit="return confirm('Remove this check? The evidence stays on their file.')">
                                @csrf @method('DELETE')
                                <button class="ml-2 text-xs font-semibold text-slate-400 hover:text-rose-600">Remove</button>
                            </form>
                        </td>
                    </tr>
                    @if($check->notes)
                        <tr><td colspan="6" class="px-5 pb-3 -mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $check->notes }}</td></tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">
                            No checks recorded for them yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recording one. No provider is called: this is what a person saw. --}}
    <form method="POST" action="{{ route('employee-checks.store', $employee) }}" enctype="multipart/form-data"
          class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm space-y-4 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <h2 class="text-sm font-extrabold text-slate-800 dark:text-white">Record a check</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Check</label>
                <select name="type" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach(\App\Models\EmployeeCheck::TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Outcome</label>
                <select name="status" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach(\App\Models\EmployeeCheck::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'passed')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Checked on</label>
                <input type="date" name="checked_on" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Valid until</label>
                <input type="date" name="expires_on"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <p class="mt-1 text-[10px] text-slate-400">Blank if it does not expire. A visa date goes here.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Reference</label>
                <input type="text" name="reference" maxlength="120" placeholder="Share code, certificate number"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Verified by</label>
                <input type="text" name="verified_by_name" maxlength="120" placeholder="A person, or the service used"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Evidence</label>
                <input type="file" name="evidence"
                       class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-bold dark:text-slate-300 dark:file:bg-slate-700">
                <p class="mt-1 text-[10px] text-slate-400">Goes onto their file with everything else</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Notes</label>
            <textarea name="notes" rows="2" maxlength="2000"
                      class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea>
        </div>

        <div class="flex justify-end">
            <button class="btn-brand">Record check</button>
        </div>
    </form>
</div>
@endsection
