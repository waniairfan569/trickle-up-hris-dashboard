@extends('layouts.hr-app')

@section('title', 'Job Locations')
@section('breadcrumb', 'Job Locations')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Job Locations</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Manage where employees are based. These appear in the employee creation form.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-300">
            <ul class="list-disc pl-5 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <!-- Add location form -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Add a location</h3>
        </div>
        <form action="{{ route('job-locations.store') }}" method="POST" class="p-6 grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            @csrf
            <div class="md:col-span-4">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Location name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Lahore Office"
                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">City</label>
                <input type="text" name="city" value="{{ old('city') }}" placeholder="e.g. Lahore"
                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Country</label>
                <select name="country" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach(\App\Models\JobLocation::COUNTRIES as $code => $cname)
                        <option value="{{ $code }}" {{ old('country') === $code ? 'selected' : '' }}>{{ $cname }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full inline-flex justify-center items-center gap-1 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 transition">Save</button>
            </div>
            <div class="md:col-span-12">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_remote" value="1" {{ old('is_remote') ? 'checked' : '' }}
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                    This is a remote/WFH location
                </label>
            </div>
        </form>
    </div>

    <!-- Locations table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 text-slate-500 font-medium uppercase text-xs border-b border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                    <tr>
                        <th class="px-6 py-3">Location</th>
                        <th class="px-6 py-3">City</th>
                        <th class="px-6 py-3">Country</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Employees</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($locations as $loc)
                        <tr x-data="{ editing: false }">
                            <td class="px-6 py-4">
                                <span x-show="!editing" class="font-bold text-slate-800 dark:text-white">{{ $loc->name }}</span>
                                <form x-show="editing" x-cloak id="edit-{{ $loc->id }}" action="{{ route('job-locations.update', $loc) }}" method="POST">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $loc->name }}" required
                                           class="w-full rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                </form>
                            </td>

                            <td class="px-6 py-4">
                                <span x-show="!editing">{{ $loc->city ?? '—' }}</span>
                                <input x-show="editing" x-cloak type="text" name="city" value="{{ $loc->city }}" form="edit-{{ $loc->id }}"
                                       class="w-full rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            </td>

                            <td class="px-6 py-4">
                                <span x-show="!editing">{{ $loc->flag }} {{ $loc->country_name ?? $loc->country ?? '—' }}</span>
                                <select x-show="editing" x-cloak name="country" form="edit-{{ $loc->id }}"
                                        class="rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    @foreach(\App\Models\JobLocation::COUNTRIES as $code => $cname)
                                        <option value="{{ $code }}" {{ $loc->country === $code ? 'selected' : '' }}>{{ $cname }}</option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="px-6 py-4">
                                @if($loc->is_remote)
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">🏠 Remote</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">🏢 Office</span>
                                @endif
                                <label x-show="editing" x-cloak class="ml-2 inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" name="is_remote" value="1" form="edit-{{ $loc->id }}" {{ $loc->is_remote ? 'checked' : '' }}
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Remote
                                </label>
                            </td>

                            <td class="px-6 py-4">
                                <a href="{{ route('employees.index', ['job_location_id' => $loc->id]) }}"
                                   class="inline-flex items-center justify-center min-w-[2rem] rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">
                                    {{ $loc->employees_count ?? $loc->employee_count }}
                                </a>
                            </td>

                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button type="button" x-show="!editing" @click="editing = true"
                                        class="text-sm font-bold text-brand-600 hover:text-brand-700">Edit</button>
                                <button type="submit" form="edit-{{ $loc->id }}" x-show="editing" x-cloak
                                        class="text-sm font-bold text-emerald-600 hover:text-emerald-700">Save</button>
                                <button type="button" x-show="editing" x-cloak @click="editing = false"
                                        class="ml-2 text-sm font-bold text-slate-500 hover:text-slate-700">Cancel</button>

                                @php $assigned = $loc->employees_count ?? $loc->employee_count; @endphp
                                @if($assigned > 0)
                                    <span class="ml-3 text-sm font-bold text-slate-300 cursor-not-allowed" title="Reassign employees first">Delete</span>
                                @else
                                    <form action="{{ route('job-locations.destroy', $loc) }}" method="POST" class="inline ml-3"
                                          onsubmit="return confirm('Delete this location?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm font-bold text-red-600 hover:text-red-700">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No job locations yet. Add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
