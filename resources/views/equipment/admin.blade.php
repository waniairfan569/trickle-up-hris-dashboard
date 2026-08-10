@extends('layouts.hr-app')

@section('title', 'Equipment Requests')
@section('breadcrumb', 'Equipment Requests')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-4xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="package" class="h-6 w-6 text-brand-500"></i> Equipment Requests
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Employees asking to take company equipment home. Approve or decline — they’re notified by email.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Pending --}}
    <div class="space-y-3">
        @forelse($pending as $req)
            <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 dark:bg-slate-800 dark:border-amber-500/30" x-data="{ showReject: false, reason: '' }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-900 dark:text-white">
                            {{ optional($req->employee)->full_name ?? 'Employee' }} wants to take <span class="text-brand-600 dark:text-brand-400">{{ $req->equipment_name }}</span> home
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $req->request_number }} · requested {{ $req->created_at->diffForHumans() }}@if($req->expected_return_date) · return by {{ $req->expected_return_date->format('d M Y') }}@endif</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2"><span class="font-bold text-slate-600 dark:text-slate-300">Reason:</span> {{ $req->reason }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-500/10 shrink-0">⏳ Pending</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('equipment.approve', $req->id) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                            <i data-lucide="check" class="h-4 w-4"></i> Approve
                        </button>
                    </form>
                    <button type="button" @click="showReject = !showReject"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400">
                        <i data-lucide="x" class="h-4 w-4"></i> Decline
                    </button>
                    <form method="POST" action="{{ route('equipment.destroy', $req->id) }}" class="ml-auto"
                          onsubmit="return confirm('Delete this request permanently? The employee is not notified. Use this only for test or junk entries.')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Delete (test/junk only)"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </form>
                </div>

                {{-- Decline reason (revealed) --}}
                <form method="POST" action="{{ route('equipment.reject', $req->id) }}" x-show="showReject" x-cloak x-transition class="mt-2 flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text" name="review_note" x-model="reason" required maxlength="255" placeholder="Reason (required — shown to the employee)"
                           class="flex-1 rounded-xl border border-rose-200 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                    <button type="submit" :disabled="!reason.trim()"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="ban" class="h-4 w-4"></i> Confirm decline
                    </button>
                </form>

                {{-- Approve with optional note --}}
                <details class="mt-2 group">
                    <summary class="text-[11px] font-bold text-slate-400 cursor-pointer hover:text-slate-600 dark:hover:text-slate-300">Add a note when approving?</summary>
                    <form method="POST" action="{{ route('equipment.approve', $req->id) }}" class="mt-2 flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input type="text" name="review_note" maxlength="255" placeholder="Note (optional — shown to the employee)"
                               class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                            <i data-lucide="check" class="h-4 w-4"></i> Approve with note
                        </button>
                    </form>
                </details>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="check-check" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">All caught up</p>
                <p class="text-xs text-slate-400 mt-1">No equipment requests waiting for review.</p>
            </div>
        @endforelse
    </div>

    {{-- Decision history — searchable, sortable, paginated --}}
    @php $dateToggle = $sort === 'newest' ? 'oldest' : 'newest'; @endphp
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Decision history</h2>
            <form method="GET" action="{{ route('equipment.admin') }}" class="flex items-center gap-2">
                @if($sort !== 'newest')<input type="hidden" name="sort" value="{{ $sort }}">@endif
                <div class="relative">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search employee or equipment…"
                           class="w-full sm:w-64 rounded-xl border border-slate-300 pl-9 pr-3 py-2 text-xs shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                @if($search !== '')
                    <a href="{{ route('equipment.admin', $sort !== 'newest' ? ['sort' => $sort] : []) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Clear</a>
                @endif
            </form>
        </div>

        @if($decided->total())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-400 dark:bg-slate-900/40">
                        <tr>
                            <th class="px-5 py-2.5 font-bold">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'employee', 'decided' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-600 dark:hover:text-slate-200 {{ $sort === 'employee' ? 'text-slate-600 dark:text-slate-200' : '' }}">Employee @if($sort === 'employee')<i data-lucide="chevron-down" class="h-3 w-3"></i>@endif</a>
                            </th>
                            <th class="px-5 py-2.5 font-bold">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'equipment', 'decided' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-600 dark:hover:text-slate-200 {{ $sort === 'equipment' ? 'text-slate-600 dark:text-slate-200' : '' }}">Equipment @if($sort === 'equipment')<i data-lucide="chevron-down" class="h-3 w-3"></i>@endif</a>
                            </th>
                            <th class="px-5 py-2.5 font-bold">Status</th>
                            <th class="px-5 py-2.5 font-bold">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $dateToggle, 'decided' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-600 dark:hover:text-slate-200 {{ in_array($sort, ['newest', 'oldest'], true) ? 'text-slate-600 dark:text-slate-200' : '' }}">Decided <i data-lucide="chevron-{{ $sort === 'oldest' ? 'up' : 'down' }}" class="h-3 w-3"></i></a>
                            </th>
                            <th class="px-5 py-2.5 font-bold">By</th>
                            <th class="px-5 py-2.5 font-bold">Note</th>
                            <th class="px-5 py-2.5 font-bold text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($decided as $req)
                            @php $by = trim((string) optional($req->reviewer)->full_name); @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30">
                                <td class="px-5 py-3 font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ optional($req->employee)->full_name ?? 'Employee' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300 max-w-[16rem] truncate" title="{{ $req->equipment_name }}">{{ $req->equipment_name }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 font-bold {{ $req->status === 'approved' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' }}">{{ ucfirst($req->status) }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    @if($req->reviewed_at)
                                        <span title="{{ $req->reviewed_at->format('l, d M Y · H:i') }}">{{ $req->reviewed_at->format('d M Y') }} <span class="text-slate-400">· {{ $req->reviewed_at->format('g:i A') }}</span></span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $by !== '' ? $by : '—' }}</td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 max-w-[18rem] truncate" title="{{ $req->review_note }}">{{ $req->review_note ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('equipment.destroy', $req->id) }}"
                                          onsubmit="return confirm('Delete this record permanently? Use this only for test or duplicate entries.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete (test/duplicate only)"
                                                class="inline-flex items-center rounded-lg px-2 py-1.5 text-slate-300 hover:text-rose-600 hover:bg-rose-50 dark:text-slate-500 dark:hover:bg-rose-500/10">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700/60">{{ $decided->links() }}</div>
        @else
            <div class="py-12 text-center">
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">{{ $search !== '' ? 'No matches' : 'No decisions yet' }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ $search !== '' ? 'Try a different name or equipment.' : 'Approved and declined requests will appear here.' }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
