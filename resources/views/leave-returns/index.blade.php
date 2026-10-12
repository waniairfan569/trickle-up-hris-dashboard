@extends('layouts.hr-app')

@section('title', 'My Returns')
@section('breadcrumb', 'My Returns')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="undo-2" class="h-6 w-6 text-sky-500"></i> My returns
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            A short note each time you come back from leave, written here instead of in a
            meeting. These go onto the HR document for that leave, so you are never asked twice.
        </p>
    </div>

    {{-- Flash messages come from the layout; repeating them here shows each twice. --}}

    {{-- Still to answer --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Needs a note</h2>
            <p class="text-xs text-slate-400 mt-0.5">Leaves left blank show as “No note given” on your document.</p>
        </div>

        @if($outstanding->isEmpty())
            {{-- "Every return is written up" reads as a contradiction to someone
                 who has never written one, so say which of the two it is. --}}
            <div class="px-6 py-8 text-center text-sm text-slate-400">
                {{ $answered->isEmpty()
                    ? 'Nothing to answer — you have no leave waiting on a note.'
                    : 'Nothing outstanding — every return is written up.' }}
            </div>
        @else
            <form method="POST" action="{{ route('leave-returns.store') }}" class="px-6 py-5 space-y-4">
                @csrf
                @foreach($outstanding as $lv)
                    <div>
                        <label for="rn-{{ $lv->id }}" class="mb-1.5 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ optional($lv->policy)->name ?? 'Leave' }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $lv->date_label }} · back {{ \Illuminate\Support\Carbon::parse($lv->end_date)->addDay()->format('d M Y') }}
                            </span>
                        </label>
                        @if(trim((string) $lv->reason) !== '')
                            <p class="mb-1.5 text-xs text-slate-400">You applied saying: <span class="italic">“{{ $lv->reason }}”</span></p>
                        @endif
                        <textarea id="rn-{{ $lv->id }}" name="notes[{{ $lv->id }}]" rows="2" maxlength="1000"
                                  placeholder="{{ optional($lv->policy)->isWorkFromHome() ? 'How did working from home go, and is there anything we should know?' : 'How are you now, and is there anything we should know?' }}"
                                  class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                @endforeach
                <div class="flex justify-end">
                    <button type="submit" class="btn-brand"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
                </div>
            </form>
        @endif
    </section>

    {{-- Already answered --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Written up</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                    <tr>
                        <th class="px-6 py-2 whitespace-nowrap">Leave</th>
                        <th class="px-6 py-2 whitespace-nowrap">Dates</th>
                        <th class="px-6 py-2">Why you applied</th>
                        <th class="px-6 py-2">Your note on returning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($answered as $lv)
                        <tr class="align-top">
                            <td class="px-6 py-3 whitespace-nowrap font-semibold text-slate-800 dark:text-slate-100">{{ optional($lv->policy)->name ?? 'Leave' }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-xs text-slate-400">{{ $lv->date_label }}</td>
                            <td class="px-6 py-3 text-xs {{ trim((string) $lv->reason) !== '' ? 'italic text-slate-600 dark:text-slate-300' : 'text-slate-400' }}">
                                {{ trim((string) $lv->reason) !== '' ? '“' . $lv->reason . '”' : '—' }}
                            </td>
                            <td class="px-6 py-3 text-xs italic text-slate-600 dark:text-slate-300">
                                “{{ $lv->return_note }}”
                                <span class="mt-0.5 block not-italic text-[11px] text-slate-400">answered {{ optional($lv->return_note_at)->format('d M, g:i A') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-slate-400">You haven’t written up any returns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection
