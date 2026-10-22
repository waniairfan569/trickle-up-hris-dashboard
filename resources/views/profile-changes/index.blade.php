@extends('layouts.hr-app')

@section('title', 'Record changes')
@section('breadcrumb', 'Record changes')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Record changes</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Changes employees have asked to make to their own records. Only fields set to
            <span class="font-semibold">“Hold it until HR approves”</span> appear here — the rest save straight away.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach(['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Turned down'] as $key => $label)
            <a href="{{ route('profile-changes.index', ['status' => $key]) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-bold transition
                      {{ $status === $key
                         ? 'border-brand-400 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
                         : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700/40' }}">
                {{ $label }}
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700/60">
                <thead class="bg-slate-50 dark:bg-slate-700/30">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="px-5 py-3">Who</th>
                        <th class="px-5 py-3">What</th>
                        <th class="px-5 py-3">Now</th>
                        <th class="px-5 py-3">They want</th>
                        <th class="px-5 py-3">Asked</th>
                        <th class="px-5 py-3 text-right">{{ $status === 'pending' ? 'Decide' : 'Answered' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($requests as $r)
                        @php
                            // A field the workspace keeps private is reported as changed,
                            // not quoted, so reviewing it does not put it on a screen.
                            $private = optional($r->field)->visibility === 'private' || optional($r->field)->is_encrypted;
                        @endphp
                        <tr>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <a href="{{ route('employees.profile', $r->user_id) }}" class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                    {{ optional($r->employee)->full_name ?? '—' }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-200">
                                {{ $r->label() }}
                                @if(optional($r->field)->section)
                                    <span class="block text-[11px] text-slate-400">{{ $r->field->section->name }}</span>
                                @endif
                            </td>
                            @if($private)
                                <td colspan="2" class="px-5 py-3 text-xs italic text-slate-400">
                                    Private field — open their profile to see it
                                </td>
                            @else
                                <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $r->old_value ?: '—' }}</td>
                                <td class="px-5 py-3 text-sm font-semibold text-slate-800 dark:text-white">{{ $r->new_value ?: '—' }}</td>
                            @endif
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                {{ $r->created_at?->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-right">
                                @if($r->isPending())
                                    <form method="POST" action="{{ route('profile-changes.decide', $r) }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="note" maxlength="500" placeholder="Reason (optional)"
                                               class="w-40 rounded-lg border-slate-300 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                        <button name="decision" value="approve" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">Approve</button>
                                        <button name="decision" value="reject" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">Turn down</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $r->status === 'approved' ? 'Approved' : 'Turned down' }}
                                        @if($r->reviewer) by {{ $r->reviewer->full_name }} @endif
                                        @if($r->reviewed_at) · {{ $r->reviewed_at->format('d M Y') }} @endif
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">
                                @if($status === 'pending')
                                    Nothing waiting. Changes appear here only for fields set to “Hold it until HR approves”
                                    in the profile template.
                                @else
                                    Nothing here yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $requests->links() }}
</div>
@endsection
