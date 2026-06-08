@extends('layouts.hr-app')

@section('title', 'Manage Calendar')
@section('breadcrumb', 'Manage Calendar')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{ tab: 'holidays' }">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('holiday-calendars.index') }}" class="text-brand-600 hover:underline">Calendars</a> / {{ $holidayCalendar->name }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage the specific dates and employees assigned to this calendar.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3"><p class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('success') }}</p></div>
            </div>
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <div class="px-8 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
            <nav class="flex gap-6">
                <button @click="tab = 'holidays'" :class="{'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400': tab === 'holidays', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': tab !== 'holidays'}" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-bold transition">
                    Holiday Dates ({{ $holidayCalendar->holidays->count() }})
                </button>
                <button @click="tab = 'employees'" :class="{'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400': tab === 'employees', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': tab !== 'employees'}" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-bold transition">
                    Assigned Employees ({{ $assignedUsers->count() }})
                </button>
                <button @click="tab = 'settings'" :class="{'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400': tab === 'settings', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': tab !== 'settings'}" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-bold transition">
                    Calendar Settings
                </button>
            </nav>
        </div>

        <!-- Tab 1: Holidays -->
        <div x-show="tab === 'holidays'">
            <div class="p-6 overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Holiday Name</th>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Recurring</th>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($holidayCalendar->holidays as $holiday)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-4 text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $holiday->date->format('M j, Y') }}
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-700 dark:text-slate-300">{{ $holiday->name }}</td>
                                <td class="px-4 py-4">
                                    @if($holiday->is_recurring)
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">Yes</span>
                                    @else
                                        <span class="text-slate-400 text-sm">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <form action="{{ route('holiday-calendars.remove-holiday', [$holidayCalendar, $holiday]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-bold transition">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No holidays added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Add Holiday Form inline -->
            <div class="bg-slate-50 border-t border-slate-200/80 p-6 dark:bg-slate-900 dark:border-slate-700/80">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Add New Holiday</h4>
                <form action="{{ route('holiday-calendars.add-holiday', $holidayCalendar) }}" method="POST" class="flex flex-wrap gap-4 items-end">
                    @csrf
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1 dark:text-slate-300">Name</label>
                        <input type="text" name="name" required placeholder="e.g. Christmas Day" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    </div>
                    <div class="w-48">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1 dark:text-slate-300">Date</label>
                        <input type="date" name="date" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    </div>
                    <div class="flex items-center h-10 mb-1">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="is_recurring" value="1" class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:bg-slate-800">
                            <span class="ml-2 text-sm text-slate-600 font-bold dark:text-slate-300">Repeats Yearly</span>
                        </label>
                    </div>
                    <button type="submit" class="h-10 rounded-xl bg-brand-600 px-4 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 mb-1">Add</button>
                </form>
            </div>
        </div>

        <!-- Tab 2: Employees -->
        <div x-show="tab === 'employees'" style="display: none;">
            <div class="p-6">
                <!-- Assign form -->
                <form action="{{ route('holiday-calendars.assign', $holidayCalendar) }}" method="POST" class="mb-8 p-4 border border-brand-200 bg-brand-50 rounded-xl dark:bg-brand-500/10 dark:border-brand-500/20">
                    @csrf
                    <h4 class="text-sm font-bold text-brand-900 dark:text-brand-100 mb-3">Assign Employees</h4>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <select name="user_ids[]" multiple required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white" style="min-height: 100px;">
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-500 mt-1">Hold Ctrl/Cmd to select multiple.</p>
                        </div>
                        <div>
                            <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700">Assign Selected</button>
                        </div>
                    </div>
                </form>

                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700">
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                            <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse($assignedUsers as $user)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-4 text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $user->first_name }} {{ $user->last_name }} <span class="text-slate-500 font-normal">({{ $user->email }})</span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <form action="{{ route('holiday-calendars.unassign', [$holidayCalendar, $user]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-bold transition">Unassign</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No employees assigned.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 3: Settings -->
        <div x-show="tab === 'settings'" style="display: none;" class="p-8">
            <form action="{{ route('holiday-calendars.update', $holidayCalendar) }}" method="POST" class="max-w-xl space-y-6">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ $holidayCalendar->name }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Country Code</label>
                        <input type="text" name="country_code" value="{{ $holidayCalendar->country_code }}" maxlength="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Year</label>
                        <input type="number" name="year" value="{{ $holidayCalendar->year }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>

                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500" {{ $holidayCalendar->is_active ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Calendar is Active</span>
                    </label>
                </div>

                <div class="pt-4 flex justify-between items-center border-t border-slate-100 dark:border-slate-700/60">
                    <button type="submit" class="rounded-xl px-5 py-2.5 bg-brand-600 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700">Save Changes</button>
                </div>
            </form>
            
            <form action="{{ route('holiday-calendars.destroy', $holidayCalendar) }}" method="POST" class="mt-8 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-bold text-red-600 hover:text-red-800 transition">Delete Calendar</button>
            </form>
        </div>
    </div>
</div>
@endsection
