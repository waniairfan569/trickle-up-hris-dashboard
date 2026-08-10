@extends('layouts.hr-app')

@section('title', 'Equipment Requests')
@section('breadcrumb', 'Equipment')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="package" class="h-6 w-6 text-brand-500"></i> Take Equipment Home
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Request approval to take a company item home. Admin will review and you’ll be notified by email.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Two equal-height cards: request form (left) · my requests (right) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">

        {{-- LEFT: request form --}}
        <form method="POST" action="{{ route('equipment.store') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 flex flex-col h-full">
            @csrf
            <div class="space-y-4 flex-1">
                <div x-data="{ type: @js(old('equipment_type', '')) }">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Equipment <span class="text-rose-500">*</span></label>
                    <select name="equipment_type" x-model="type" required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="" disabled {{ old('equipment_type') ? '' : 'selected' }}>Select equipment…</option>
                        @foreach(config('equipment.items', []) as $item)
                            <option value="{{ $item }}" {{ old('equipment_type') === $item ? 'selected' : '' }}>{{ $item }}</option>
                        @endforeach
                        <option value="Other" {{ old('equipment_type') === 'Other' ? 'selected' : '' }}>Other…</option>
                    </select>
                    <input type="text" name="equipment_details" value="{{ old('equipment_details') }}" maxlength="150"
                           x-bind:required="type === 'Other'"
                           x-bind:placeholder="type === 'Other' ? 'Name the equipment' : 'Model / details (optional) — e.g. Dell Latitude 7420'"
                           placeholder="Model / details (optional) — e.g. Dell Latitude 7420"
                           class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason for taking it home <span class="text-rose-500">*</span></label>
                    <textarea name="reason" rows="4" required maxlength="1000"
                              placeholder="Why do you need this equipment at home?"
                              class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('reason') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Expected return date <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                    <input type="date" name="expected_return_date" value="{{ old('expected_return_date') }}" min="{{ now()->toDateString() }}"
                           class="w-full sm:w-56 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>
            <div class="flex justify-end pt-5 mt-auto">
                <button type="submit" class="btn-brand">
                    <i data-lucide="send" class="h-4 w-4"></i> Send request
                </button>
            </div>
        </form>

        {{-- RIGHT: my requests (single card, matches the form's height) --}}
        @php
            $badge = [
                'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                'approved' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                'rejected' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
            ];
            $label = ['pending' => '⏳ Pending', 'approved' => '✅ Approved', 'rejected' => '🚫 Declined'];
        @endphp
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700 flex flex-col h-full overflow-hidden">
            <div class="flex items-center justify-between gap-2 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">My requests</h2>
                @if($requests->count())<span class="text-[11px] font-semibold text-slate-400">{{ $requests->count() }} total</span>@endif
            </div>

            <div class="flex-1 flex flex-col{{ $requests->count() ? ' divide-y divide-slate-100 dark:divide-slate-700/60' : '' }}">
                @forelse($requests as $req)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-extrabold text-slate-900 dark:text-white truncate">{{ $req->equipment_name }}</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $req->request_number }} · requested {{ $req->created_at->diffForHumans() }}@if($req->expected_return_date) · return by {{ $req->expected_return_date->format('d M Y') }}@endif</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 italic">“{{ $req->reason }}”</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold shrink-0 {{ $badge[$req->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $label[$req->status] ?? ucfirst($req->status) }}</span>
                        </div>
                        @if($req->status !== 'pending')
                            <div class="mt-3 rounded-xl border p-3 text-xs {{ $req->status === 'approved' ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10' : 'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' }}">
                                <span class="font-bold {{ $req->status === 'approved' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                    {{ $req->status === 'approved' ? 'Approved' : 'Declined' }} by {{ optional($req->reviewer)->full_name ?? 'Admin' }}{{ $req->reviewed_at ? ' · ' . $req->reviewed_at->format('d M Y') : '' }}
                                </span>
                                @if($req->review_note)<p class="text-slate-600 dark:text-slate-300 mt-1">Note: {{ $req->review_note }}</p>@endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex-1 flex flex-col items-center justify-center text-center px-6 py-14">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="package" class="h-7 w-7"></i></div>
                        <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No requests yet</p>
                        <p class="text-xs text-slate-400 mt-1">Use the form to request equipment to take home.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
