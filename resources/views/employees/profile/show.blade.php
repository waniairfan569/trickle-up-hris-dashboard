@extends('layouts.hr-app')

@section('title', $employee->full_name)
@section('breadcrumb', 'Employee Profile')

@section('content')
@php
    $auth = auth()->user();
    $isSelf = $auth->id === $employee->id;
    $editing = request('edit') == 1 && $canEdit;
    
    // Lucide Icons Map
    $lucideIcons = [
        'ti-user'          => 'user',
        'ti-briefcase'     => 'briefcase',
        'ti-coin'          => 'coins',
        'ti-id-badge'      => 'id-card',
        'ti-urgent'        => 'alert-triangle',
        'ti-building-bank' => 'landmark',
        'ti-code'          => 'code',
        'ti-passport'      => 'file-text'
    ];
@endphp

<style>[x-cloak]{display:none!important}</style>

@php $initialSection = in_array(request('section'), ['information', 'files', 'timeoff', 'timetracking'], true) ? request('section') : 'information'; @endphp
<div class="space-y-8" id="profile-page-root" x-data="{ tab: 'personal', section: '{{ $initialSection }}', showSensitive: false, fileTab: 'upload', uploadOpen: false, fileSearch: '', payReviewOpen: false }">
    <!-- Back Button -->
    <div>
        <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 transition dark:text-slate-400 dark:hover:text-white">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Back
        </a>
    </div>

    <!-- Header Hero Banner -->
    @php
        $lastFirst = $employee->last_name
            ? trim($employee->last_name . ', ' . $employee->first_name)
            : ($employee->full_name ?? 'Employee');
        $jt = trim((string) $employee->job_title);
        $showJobTitle = $jt !== '' && strtolower($jt) !== 'employee';
        $contract = trim((string) $employee->contract_type);
        $primaryLocation = $employee->primaryOfficeLocation();
        $cityCountry = trim(implode(', ', array_filter([$employee->city, $employee->country])));
        $locationBits = array_values(array_filter([
            optional($employee->companyEntity)->name,
            $cityCountry ?: null,
            optional($primaryLocation)->name,
        ]));
    @endphp
    <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <!-- Soft banner -->
        <div class="h-32 bg-gradient-to-r from-teal-100/70 via-rose-50 to-sky-100/70 dark:from-slate-700 dark:via-slate-700/60 dark:to-slate-700"></div>

        <!-- Identity row -->
        <div class="relative px-6 pb-6 sm:px-8">
            <!-- Avatar straddles the banner edge; everything else sits in the white area below -->
            <div class="absolute -top-14 left-6 sm:left-8 shrink-0">
                @if($employee->avatar_url)
                    <img src="{{ $employee->avatar_url }}" alt="{{ $employee->full_name }}" class="h-28 w-28 rounded-full object-cover border-4 border-white bg-slate-100 ring-1 ring-slate-200/60 shadow-md dark:border-slate-800">
                @else
                    <div class="flex h-28 w-28 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-indigo-500 text-3xl font-bold text-white border-4 border-white ring-1 ring-slate-200/60 shadow-md dark:border-slate-800">
                        {{ $employee->initials }}
                    </div>
                @endif
            </div>

            <!-- Content (name, meta, actions) — all inside the white section -->
            <div class="pt-16 sm:pt-5 sm:pl-36 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <!-- Name + meta -->
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $lastFirst }}</h1>
                        @if($employee->account_status === 'invited')
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Pending
                            </span>
                        @elseif($employee->account_status === 'deactivated')
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">Archived</span>
                        @endif
                    </div>

                    @if($showJobTitle || $employee->department)
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            @if($showJobTitle)<span class="font-bold text-slate-700 dark:text-slate-200">{{ $jt }}</span>@if($contract) <span class="text-slate-400">({{ $contract }})</span>@endif @endif
                            @if($employee->department)@if($showJobTitle)<span class="text-slate-400">in</span> @endif<span class="font-bold text-slate-700 dark:text-slate-200">{{ $employee->department->name }}</span>@endif
                        </p>
                    @endif

                    @if(count($locationBits))
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {!! implode(' <span class="text-slate-300 dark:text-slate-600">|</span> ', array_map('e', $locationBits)) !!}
                        </p>
                    @endif

                    @if($employee->email)
                        <div class="pt-1">
                            <a href="mailto:{{ $employee->email }}" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition dark:bg-slate-700 dark:text-slate-300">
                                <i data-lucide="mail" class="h-3.5 w-3.5"></i>{{ $employee->email }}
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Profile Action Buttons -->
                @if($canEdit && !$editing)
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('hr_admin'))
                    {{-- Bug #16: Add confirm dialog + tooltip to Assign Default Policies --}}
                    <form action="{{ route('employees.assign-default-policies', $employee->id) }}" method="POST">
                        @csrf
                        <button type="submit" 
                                title="Assigns all currently active default HR policies to this employee"
                                onclick="return confirm('This will assign all active default HR policies to {{ $employee->full_name }}. Continue?')"
                                class="inline-flex items-center gap-x-1.5 rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 border border-slate-200 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            <span>Assign Default Policies</span>
                        </button>
                    </form>
                    
                    @if($employee->account_status === 'invited')
                        <form action="{{ route('invitation.resend', $employee->id) }}" method="POST" class="inline-block">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-x-1.5 rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 border border-slate-200 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
                                <i data-lucide="mail" class="h-4 w-4"></i>
                                <span>Resend Invite</span>
                            </button>
                        </form>
                        <form action="{{ route('invitation.cancel', $employee->id) }}" method="POST" class="inline-block">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-x-1.5 rounded-xl bg-red-50 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-100 border border-red-200 transition duration-150 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/40">
                                <i data-lucide="x-circle" class="h-4 w-4"></i>
                                <span>Cancel Invite</span>
                            </button>
                        </form>
                    @endif
                    @endif
                    <a href="{{ route('employees.profile', ['employee' => $employee->id, 'edit' => 1]) }}" class="inline-flex items-center gap-x-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                        <i data-lucide="edit-3" class="h-4 w-4"></i>
                        <span>Edit Profile</span>
                    </a>
                </div>
                @endif
            </div>
        </div>

        <!-- Tab List -->
        @php
            $profileTabs = [
                'personal'     => 'Personal',
                'job'          => 'Job',
                'compensation' => 'Compensation & benefits',
                'legal'        => 'Legal documents',
                'experience'   => 'Experience',
                'emergency'    => 'Emergency',
            ];
            // These tabs hold HR-managed / sensitive data — hidden on the employee side,
            // visible only to Super Admin / HR Admin.
            // Admins see every tab on anyone's profile; employees see every tab on
            // their OWN profile, but only Personal + Job on a colleague's profile.
            $canSeeHrTabs = $auth->isAdmin() || $isSelf;
            $hrOnlyTabs = ['compensation', 'legal', 'experience', 'emergency'];
        @endphp
        <!-- Top-level tabs -->
        <div class="flex items-center border-t border-slate-100 px-6 dark:border-slate-700/60">
            <div class="flex overflow-x-auto select-none">
                <button @click="section = 'information'" :class="section === 'information' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                    class="whitespace-nowrap border-b-2 py-4 px-4 text-sm font-bold transition">Information</button>
                @if($isSelf || $auth->isAdmin() || $auth->hasRole('hr_admin'))
                    <button @click="section = 'files'" :class="section === 'files' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                        class="whitespace-nowrap border-b-2 py-4 px-4 text-sm font-bold transition">Files</button>
                    <button @click="section = 'timeoff'" :class="section === 'timeoff' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                        class="whitespace-nowrap border-b-2 py-4 px-4 text-sm font-bold transition">Time off</button>
                    <button @click="section = 'timetracking'" :class="section === 'timetracking' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                        class="whitespace-nowrap border-b-2 py-4 px-4 text-sm font-bold transition">Time tracking</button>
                @endif
            </div>
        </div>

        <!-- Sub-tabs (within Information) -->
        <div x-show="section === 'information'" x-cloak class="flex items-center justify-between border-t border-slate-100 px-6 dark:border-slate-700/60 gap-4">
            <div class="flex overflow-x-auto select-none">
                @foreach($profileTabs as $key => $label)
                    @if(in_array($key, $hrOnlyTabs) && !$canSeeHrTabs) @continue @endif
                    <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                        class="whitespace-nowrap border-b-2 py-4 px-4 text-xs font-bold transition">{{ $label }}</button>
                @endforeach
            </div>
            <!-- Show sensitive data toggle (masks private/internal fields) -->
            <button type="button" @click="showSensitive = !showSensitive"
                    class="flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 whitespace-nowrap shrink-0">
                <i data-lucide="eye" class="h-4 w-4"></i>
                <span class="hidden sm:inline">Show sensitive data</span>
                <span class="relative inline-flex h-4 w-7 items-center rounded-full transition" :class="showSensitive ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-600'">
                    <span class="inline-block h-3 w-3 transform rounded-full bg-white transition" :class="showSensitive ? 'translate-x-3.5' : 'translate-x-0.5'"></span>
                </span>
            </button>
        </div>
    </div>

    <!-- Error/Success notifications -->
    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm dark:bg-emerald-950/20 dark:border-emerald-800 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    
    @if(isset($errors) && $errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-800 text-sm dark:bg-rose-950/20 dark:border-rose-800 dark:text-rose-450 flex flex-col gap-1">
            <div class="flex items-center gap-2 font-bold">
                <i data-lucide="alert-circle" class="h-5 w-5 text-rose-500"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc pl-5 mt-1 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ======================================================= --}}
    {{-- SELF / ADMIN ONLY TABS --}}
    {{-- ======================================================= --}}
    @if($isSelf || $auth->isAdmin() || $auth->hasRole('hr_admin'))

    {{-- TIME OFF TAB --}}
    <div x-show="section === 'timeoff'" x-cloak class="space-y-6">

        @if($auth->isAdmin() && !$isSelf)
            @php
                $emPolicyIds = $employee->timeOffPolicies()->pluck('time_off_policies.id')
                    ->merge(\App\Models\TimeOffBalance::where('user_id', $employee->id)->pluck('policy_id'))->unique();
                $emPolicies = \App\Models\TimeOffPolicy::whereIn('id', $emPolicyIds)->get();
                if ($emPolicies->isEmpty()) { $emPolicies = \App\Models\TimeOffPolicy::where('is_active', true)->get(); }
            @endphp
            <div class="bg-white border border-brand-200 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-brand-500/30 overflow-hidden"
                 x-data="{ dt: 'full_day', s: '', e: '' }" x-effect="if (dt !== 'full_day') e = s">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Add leave for {{ $employee->first_name }}</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Filed on their behalf and sent to the approvals queue.</p>
                </div>
                <form method="POST" action="{{ route('time-off.on-behalf') }}" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Policy</label>
                            <select name="policy_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                @foreach($emPolicies as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Duration</label>
                            <select name="duration_type" x-model="dt" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <option value="full_day">Full day(s)</option>
                                <option value="half_day">Half day</option>
                                <option value="hourly">Hourly</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Start date</label>
                            <input type="date" name="start_date" x-model="s" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        <div x-show="dt === 'full_day'">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">End date</label>
                            <input type="date" name="end_date" x-model="e" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                    </div>
                    <div x-show="dt === 'half_day'" class="flex gap-4 text-sm text-slate-600 dark:text-slate-300">
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="half_day_period" value="morning" checked> Morning</label>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="half_day_period" value="afternoon"> Afternoon</label>
                    </div>
                    <div x-show="dt === 'hourly'" class="grid grid-cols-2 gap-4 max-w-sm">
                        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Start time</label><input type="time" name="start_time" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                        <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">End time</label><input type="time" name="end_time" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Reason (optional)</label>
                        <textarea name="reason" rows="2" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                    </div>
                    <p class="text-[11px] text-slate-400">Maternity / Paternity require the employee to be married with 1+ year of service. The request is filed on their behalf and sent for approval.</p>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="calendar-plus" class="h-4 w-4"></i> Submit for approval</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Time-Off Balances</h2>
                @if($isSelf)<a href="{{ route('time-off.create') }}" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-slate-900 text-xs font-bold py-2 px-4 rounded-lg transition"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Request Time Off</a>@endif
            </div>
            <div class="p-6">
                @php $empBalances = \App\Models\TimeOffBalance::where('user_id',$employee->id)->with('policy')->whereHas('policy')->get(); @endphp
                @if($empBalances->isEmpty())
                    <p class="text-sm text-slate-400 italic">No time-off policies assigned.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @foreach($empBalances as $b)
                            @php
                                $total=$b->opening_balance+$b->accrued+$b->adjusted+$b->carried_over;
                                $rem=max(0,$total-$b->used-$b->pending);
                                $pN=$b->policy->name;
                                $lbl=stripos($pN,'Annual')!==false?'Planned Leaves':(stripos($pN,'Casual')!==false?'Unplanned':$pN);
                                $cc=['border-cyan-400','border-amber-400','border-rose-400','border-emerald-400','border-indigo-400'][$loop->index%5];
                            @endphp
                            <div class="border-l-4 {{ $cc }} bg-slate-50 dark:bg-slate-700/50 rounded-xl p-4">
                                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">{{ $lbl }}</p>
                                <p class="text-2xl font-extrabold text-slate-800 dark:text-white">{{ floatval($rem) }}</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">of {{ floatval($total) }} days total</p>
                                <p class="text-[10px] text-slate-400 mt-1">Used: {{ floatval($b->used) }} · Pending: {{ floatval($b->pending) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Recent Requests</h2></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @php $recentReqs=\App\Models\TimeOffRequest::where('user_id',$employee->id)->with('policy')->orderByDesc('created_at')->limit(5)->get(); @endphp
                @forelse($recentReqs as $req)
                    @php $rsc=match($req->status){'approved'=>'bg-emerald-50 text-emerald-700','pending'=>'bg-amber-50 text-amber-700','rejected'=>'bg-red-50 text-red-700',default=>'bg-slate-50 text-slate-600'}; @endphp
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div><p class="text-sm font-semibold text-slate-800 dark:text-white">{{ optional($req->policy)->name??'Leave' }}</p><p class="text-xs text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($req->start_date)->format('d M Y') }} → {{ \Carbon\Carbon::parse($req->end_date)->format('d M Y') }}</p></div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold capitalize {{ $rsc }}">{{ $req->status }}</span>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-slate-400 italic">No time-off requests yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ATTENDANCE TAB --}}
    <div x-show="section === 'timetracking'" x-cloak class="space-y-6">

        @if($auth->isAdmin())
            <div class="bg-white border border-brand-200 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-brand-500/30 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Add / edit attendance</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Set clock-in / clock-out for any date (in {{ $employee->first_name }}'s timezone). Late is auto-flagged.</p>
                </div>
                <form method="POST" action="{{ route('attendance.employee-entry', $employee->id) }}" class="p-6 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date</label>
                        <input type="date" name="date" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Clock in</label>
                        <input type="time" name="clock_in" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Clock out</label>
                        <input type="time" name="clock_out" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
                </form>
            </div>

            {{-- Hide from attendance sheets & reports --}}
            @php $isHidden = (bool) ($employee->exclude_from_attendance ?? false); @endphp
            <form method="POST" action="{{ route('employees.update-attendance-visibility', $employee->id) }}"
                  class="bg-white border {{ $isHidden ? 'border-amber-300 dark:border-amber-500/40' : 'border-slate-200/80 dark:border-slate-700' }} rounded-2xl shadow-sm dark:bg-slate-800 overflow-hidden">
                @csrf
                @method('PUT')
                <input type="hidden" name="exclude_from_attendance" value="{{ $isHidden ? '0' : '1' }}">
                <div class="flex items-start gap-4 px-6 py-5">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $isHidden ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-700' }}">
                        <i data-lucide="{{ $isHidden ? 'eye-off' : 'eye' }}" class="h-5 w-5"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Hide from attendance sheets</h2>
                        <p class="text-xs text-slate-400 mt-0.5 max-w-xl">
                            When hidden, {{ $employee->first_name }} won't appear on the live board, team history, reports or the daily email — not as present, not as absent, nothing. No daily attendance records are generated for them.
                        </p>
                        @if($isHidden)
                            <span class="inline-flex items-center gap-1 mt-2 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                <i data-lucide="eye-off" class="h-3 w-3"></i> Currently hidden from attendance
                            </span>
                        @endif
                    </div>
                    <button type="submit" class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-bold {{ $isHidden ? 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200' : 'bg-amber-500 text-slate-900 hover:bg-amber-600' }}">
                        <i data-lucide="{{ $isHidden ? 'eye' : 'eye-off' }}" class="h-4 w-4"></i>
                        {{ $isHidden ? 'Show on sheets' : 'Hide from sheets' }}
                    </button>
                </div>
            </form>
        @endif

        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
            @php
                $attRange = in_array(request('att'), ['week', 'month'], true) ? request('att') : 'recent';
                $attQ = \App\Models\AttendanceRecord::where('user_id', $employee->id)->orderByDesc('date');
                if ($attRange === 'week') {
                    $attQ->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]);
                } elseif ($attRange === 'month') {
                    $attQ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
                } else {
                    $attQ->limit(14);
                }
                $atts = $attQ->get();
                $tzSvc = app(\App\Services\TimezoneService::class);
                $canEditAtt = $auth->isAdmin();
                $cols = $canEditAtt ? 6 : 5;
                $attTitle = ['recent' => 'Recent Attendance', 'week' => 'Attendance — This Week', 'month' => 'Attendance — ' . now()->format('F Y')][$attRange];
                $attLink = fn ($r) => request()->fullUrlWithQuery(['att' => $r, 'section' => 'timetracking']) . '#attendance-card';
                $attPill = fn ($active) => $active
                    ? 'rounded-full bg-brand-500 px-3 py-1 text-[11px] font-bold text-slate-900'
                    : 'rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300';
            @endphp
            <div id="attendance-card" class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">{{ $attTitle }}</h2>
                <div class="flex items-center gap-1.5">
                    <a href="{{ $attLink('recent') }}" class="{{ $attPill($attRange === 'recent') }}">Recent</a>
                    <a href="{{ $attLink('week') }}" class="{{ $attPill($attRange === 'week') }}">This week</a>
                    <a href="{{ $attLink('month') }}" class="{{ $attPill($attRange === 'month') }}">This month</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700">
                        <tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Clock In</th><th class="px-6 py-3">Clock Out</th><th class="px-6 py-3">Hours Worked</th><th class="px-6 py-3">Status</th>@if($canEditAtt)<th class="px-6 py-3 text-right">Edit</th>@endif</tr>
                    </thead>
                    @forelse($atts as $att)
                        @php
                            $as=$att->status??'present';
                            $asc=match($as){'present'=>'bg-emerald-50 text-emerald-700','absent'=>'bg-red-50 text-red-700','late'=>'bg-amber-50 text-amber-700','half_day'=>'bg-indigo-50 text-indigo-700','on_leave'=>'bg-blue-50 text-blue-700',default=>'bg-slate-50 text-slate-600'};
                            $ciVal=$att->clock_in?$tzSvc->formatForUser($att->clock_in,$employee,'H:i'):'';
                            $coVal=$att->clock_out?$tzSvc->formatForUser($att->clock_out,$employee,'H:i'):'';
                        @endphp
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-xs" @if($canEditAtt)x-data="{ edit: false }"@endif>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <td class="px-6 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ \Carbon\Carbon::parse($att->date)->format('D, d M Y') }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $att->clock_in?$tzSvc->formatForUser($att->clock_in,$employee,'h:i A'):'-' }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $att->clock_out?$tzSvc->formatForUser($att->clock_out,$employee,'h:i A'):'-' }}</td>
                                <td class="px-6 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $att->clock_in && $att->clock_out ? floor($att->total_minutes_worked / 60) . 'h ' . ($att->total_minutes_worked % 60) . 'm' : '-' }}</td>
                                <td class="px-6 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold capitalize {{ $asc }}">{{ str_replace('_',' ',$as) }}</span></td>
                                @if($canEditAtt)
                                    <td class="px-6 py-3 text-right">
                                        <button type="button" @click="edit=!edit" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-slate-700">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> <span x-text="edit ? 'Close' : 'Edit'">Edit</span>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                            @if($canEditAtt)
                                <tr x-show="edit" x-cloak class="bg-slate-50/60 dark:bg-slate-900/30">
                                    <td colspan="{{ $cols }}" class="px-6 py-4">
                                        <form method="POST" action="{{ route('attendance.records.update-times', $att->id) }}" class="flex flex-wrap items-end gap-3">
                                            @csrf @method('PUT')
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Clock in</label>
                                                <input type="time" name="clock_in" value="{{ $ciVal }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Clock out</label>
                                                <input type="time" name="clock_out" value="{{ $coVal }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            </div>
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
                                            <button type="button" @click="edit=false" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">Cancel</button>
                                            <span class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($att->date)->format('D, d M Y') }} · times in {{ $employee->first_name }}'s timezone · leave Clock in blank to mark Absent</span>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="{{ $cols }}" class="px-6 py-8 text-center text-slate-400 italic">No attendance records found.</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>
    </div>

    {{-- FILES TAB — E-signature + Upload sub-tabs --}}
    <script>window.__empDocNames = @json($employee->documents->pluck('name')->map(fn ($n) => strtolower($n))->values());</script>
    <div x-show="section === 'files'" x-cloak class="space-y-4">
        <!-- Sub-tabs -->
        <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
            <button @click="fileTab = 'esign'" :class="fileTab === 'esign' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                    class="border-b-2 px-4 py-2.5 text-sm font-bold transition">E-signature</button>
            <button @click="fileTab = 'upload'" :class="fileTab === 'upload' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'"
                    class="border-b-2 px-4 py-2.5 text-sm font-bold transition">Uploads</button>
        </div>

        {{-- E-SIGNATURE SUB-TAB --}}
        @php
            $auth = auth()->user();
            $esBadge = [
                'in_progress' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                'declined' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
                'cancelled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
            ];
            $esLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];
        @endphp
        <div x-show="fileTab === 'esign'" x-cloak class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700">
            @if(($signatureRequests ?? collect())->count())
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Signature documents ({{ $signatureRequests->count() }})</span>
                    @if($auth->isAdmin())
                        <a href="{{ route('document-templates.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i> Send new
                        </a>
                    @endif
                </div>
                <div>
                    @foreach($signatureRequests as $req)
                        @php $awaitingMe = $req->isAwaiting($auth); @endphp
                        <div class="flex items-center gap-4 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="file-signature" class="h-4 w-4"></i></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $req->template->name ?? 'Document' }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $req->signers->where('status', 'signed')->count() }}/{{ $req->signers->count() }} signed · {{ $req->created_at->format('d M Y') }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $esBadge[$req->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $esLabel[$req->status] ?? ucfirst($req->status) }}</span>
                            @if($awaitingMe)
                                <a href="{{ route('documents.sign', $req) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-1.5 text-xs font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="pen-tool" class="h-3.5 w-3.5"></i> Sign</a>
                            @else
                                <a href="{{ route('documents.show', $req) }}" title="View" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-4 w-4"></i></a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-700 mb-3"><i data-lucide="file-signature" class="h-7 w-7 text-slate-400"></i></div>
                    <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No signature documents yet</p>
                    <p class="text-xs text-slate-400 mt-1">Documents sent for signature will appear here.</p>
                    @if($auth->isAdmin())
                        <a href="{{ route('document-templates.index') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-brand-700">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i> Send for signature
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- UPLOADS SUB-TAB --}}
        <div x-show="fileTab === 'upload'" x-cloak class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700">
            @if($employee->documents->count())
                <div class="flex flex-col gap-3 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Uploaded files ({{ $employee->documents->count() }})</span>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                            <input type="text" x-model="fileSearch" placeholder="Search files…"
                                   class="rounded-xl border-slate-300 pl-8 pr-3 py-1.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white w-44">
                        </div>
                        <button @click="uploadOpen = true" type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 shrink-0">
                            <i data-lucide="upload" class="h-3.5 w-3.5"></i> Upload
                        </button>
                    </div>
                </div>
                <div x-data="{ names: (window.__empDocNames || []) }">
                    <p x-show="fileSearch && !names.some(n => n.includes(fileSearch.toLowerCase()))" x-cloak class="px-6 py-8 text-center text-sm text-slate-400">No files match “<span x-text="fileSearch"></span>”.</p>
                    @foreach($employee->documents as $doc)
                        <div data-doc data-name="{{ strtolower($doc->name) }}"
                             x-show="fileSearch === '' || $el.dataset.name.includes(fileSearch.toLowerCase())"
                             class="flex items-center gap-4 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="file" class="h-4 w-4"></i></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $doc->name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $doc->readable_size }} · {{ $doc->created_at->format('d M Y') }}@if($doc->uploader) · {{ $doc->uploader->full_name }}@endif</p>
                            </div>
                            <a href="{{ route('employees.documents.download', [$employee->id, $doc->id]) }}" title="Download" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                <i data-lucide="download" class="h-4 w-4"></i>
                            </a>
                            <form action="{{ route('employees.documents.destroy', [$employee->id, $doc->id]) }}" method="POST" onsubmit="return confirm('Delete “{{ $doc->name }}”?');">
                                @csrf @method('DELETE')
                                <button type="submit" title="Delete" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-700 mb-3"><i data-lucide="folder-open" class="h-7 w-7 text-slate-400"></i></div>
                    <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No uploaded files yet</p>
                    <button @click="uploadOpen = true" type="button" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                        <i data-lucide="upload" class="h-4 w-4"></i> Upload
                    </button>
                </div>
            @endif
        </div>

        {{-- UPLOAD MODAL --}}
        <div x-show="uploadOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="uploadOpen = false"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-slate-800"
                 x-data="{ fileName: '',
                           pick(files) { this.fileName = (files && files[0]) ? files[0].name : ''; },
                           drop(e) { const f = e.dataTransfer.files; if (f && f.length) { $refs.fileInput.files = f; this.pick(f); } } }">
                <form action="{{ route('employees.documents.store', $employee->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Upload file</h2>
                        <button type="button" @click="uploadOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div x-data="{ n: '' }">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Name <span class="text-rose-500">*</span></label>
                                <span class="text-[11px]" :class="n.length > 70 ? 'text-rose-500' : 'text-slate-400'" x-text="n.length + '/70'"></span>
                            </div>
                            <input type="text" name="name" x-model="n" required maxlength="70" placeholder="e.g. Signed Contract 2026"
                                   class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">File <span class="text-rose-500">*</span></label>
                            <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-4 py-8 cursor-pointer hover:border-brand-400 hover:bg-brand-50/30 dark:border-slate-600"
                                   @dragover.prevent @drop.prevent="drop($event)">
                                <i data-lucide="upload-cloud" class="h-7 w-7 text-slate-400"></i>
                                <span class="text-sm text-slate-500 dark:text-slate-400" x-show="!fileName">Click to browse or drag &amp; drop</span>
                                <span class="text-sm font-semibold text-slate-700 dark:text-white" x-show="fileName" x-text="fileName"></span>
                                <span class="text-[11px] text-slate-400">PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, PNG, GIF · max 20 MB</span>
                                <input type="file" name="file" x-ref="fileInput" required @change="pick($event.target.files)" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif" class="hidden">
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="button" @click="uploadOpen = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                            <i data-lucide="upload" class="h-4 w-4"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @endif {{-- end self/admin-only tabs --}}

    {{-- ======================================================= --}}
    {{-- PROFILE INFORMATION — all 6 tabs, visible to everyone     --}}
    {{-- (per-field visibility still controls what each viewer sees) --}}
    {{-- ======================================================= --}}
    @php $infoTabsJs = "['personal','job','compensation','legal','experience','emergency']"; @endphp

    @if($editing)
    <form id="profile-edit-form" action="{{ route('employees.profile.update', $employee->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
        @csrf @method('PUT')
        <div x-show="section === 'information'" x-cloak class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="flex items-center gap-4">
                <div class="relative flex-shrink-0">
                    @if($employee->avatar_url)
                        <img id="avatar-preview" src="{{ $employee->avatar_url }}" class="h-20 w-20 rounded-2xl object-cover ring-2 ring-slate-200">
                    @else
                        <div id="avatar-placeholder" class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-indigo-500 text-2xl font-bold text-white ring-2 ring-brand-200">{{ $employee->initials }}</div>
                    @endif
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Profile Photo</label>
                    <div class="mt-2">
                        <label class="cursor-pointer inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                            <i data-lucide="upload" class="h-4 w-4"></i><span>Choose Photo</span>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" id="avatar-upload" onchange="previewAvatar(this)">
                        </label>
                        <p class="text-xs text-slate-400 mt-1.5">JPG, PNG or WebP — max 2MB</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- White content sheet — Workable-style grouped sections (within the Information tab) --}}
    <div x-show="section === 'information'" x-cloak
         class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700 p-6 sm:p-8">
        @foreach($templates as $template)
            @foreach($template->sections as $section)
                @php $secTab = $section->tab ?? 'personal'; @endphp
                @if($section->fields->isNotEmpty() && !(in_array($secTab, $hrOnlyTabs) && !$canSeeHrTabs))
                    <div id="section-{{ $section->id }}" x-show="tab === '{{ $secTab }}'" x-cloak
                         class="border-b border-slate-100 dark:border-slate-700/60 pb-7 mb-7">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-bold text-slate-800 dark:text-white">{{ $section->name }}</h3>
                            @if($canEdit && !$editing)
                                <a href="{{ route('employees.profile', ['employee'=>$employee->id,'edit'=>1]) }}#section-{{ $section->id }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-600 hover:text-brand-700 transition">
                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i><span>Edit</span>
                                </a>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-6">
                            @foreach($section->fields as $field)
                                @php $value=$employee->getFieldValue($field->key); $isFieldEditable=$field->isEditableTo($auth,$employee); @endphp
                                @if($editing && $isFieldEditable)
                                    @include('employees.partials.field-input',['field'=>$field,'value'=>$value,'employee'=>$employee])
                                @else
                                    @include('employees.partials.field-display',['field'=>$field,'value'=>$value])
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach

        {{-- PAY REVIEW / SALARY HISTORY — Compensation tab --}}
        @if($canSeeHrTabs)
            @php
                $cur = optional($payReviews->firstWhere('status', 'active'))->new_salary ?? $employee->salary;
                $curCode = optional($payReviews->first())->currency ?: ($employee->salary_currency ?: 'PKR');
                $money = fn ($n, $code) => $code . ' ' . number_format((float) $n, 0);
            @endphp
            <div x-show="tab === 'compensation'" x-cloak class="pt-1">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-white">Pay review &amp; salary history</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Salary timeline. Amounts are shown only when “Show sensitive data” is on.</p>
                    </div>
                    @if($auth->isAdmin())
                        <button type="button" @click="payReviewOpen = true" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add pay review
                        </button>
                    @endif
                </div>

                @if($payReviews->count())
                    <ol class="relative border-l-2 border-slate-200 dark:border-slate-700 ml-2 space-y-6">
                        @foreach($payReviews as $r)
                            <li class="ml-6">
                                <span class="absolute -left-[9px] flex h-4 w-4 items-center justify-center rounded-full {{ $r->status === 'active' ? 'bg-emerald-500' : ($r->status === 'upcoming' ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-600') }}"></span>
                                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-slate-800 dark:text-white">{{ $r->effective_date->format('d M Y') }}</span>
                                            @if($r->status === 'active')
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">CURRENT</span>
                                            @elseif($r->status === 'upcoming')
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">UPCOMING</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">HISTORICAL</span>
                                            @endif
                                            <span class="text-[11px] font-semibold text-slate-400">{{ $r->review_type_label }}</span>
                                        </div>
                                        @if($auth->isAdmin() && $r->status === 'upcoming')
                                            <form action="{{ route('employees.pay-reviews.destroy', [$employee->id, $r->id]) }}" method="POST" onsubmit="return confirm('Remove this upcoming pay review?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" title="Remove" class="text-slate-400 hover:text-rose-500"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                            </form>
                                        @endif
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <span class="text-lg font-extrabold text-slate-900 dark:text-white">
                                            <span x-show="showSensitive">{{ $money($r->new_salary, $r->currency) }}</span>
                                            <span x-show="!showSensitive" class="text-slate-400">••••••</span>
                                            <span class="text-xs font-semibold text-slate-400">/ {{ $r->pay_schedule }}</span>
                                        </span>
                                        @if($r->increment_amount != 0 && $r->previous_salary !== null)
                                            <span class="inline-flex items-center gap-0.5 text-xs font-bold {{ $r->increment_amount > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                <i data-lucide="{{ $r->increment_amount > 0 ? 'trending-up' : 'trending-down' }}" class="h-3.5 w-3.5"></i>
                                                <span x-show="showSensitive">{{ $r->increment_amount > 0 ? '+' : '' }}{{ $money($r->increment_amount, $r->currency) }}</span>
                                                @if($r->increment_percent !== null)<span>({{ $r->increment_percent > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($r->increment_percent, 2), '0'), '.') }}%)</span>@endif
                                            </span>
                                        @elseif($r->previous_salary === null)
                                            <span class="text-xs font-semibold text-slate-400">Starting salary</span>
                                        @endif
                                    </div>

                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                                        {{ $r->reason }}
                                        @if($r->months_since_last !== null) <span class="text-slate-300 dark:text-slate-600">·</span> {{ $r->months_since_last }} {{ Str::plural('month', $r->months_since_last) }} since last review @endif
                                        @if($r->approver) <span class="text-slate-300 dark:text-slate-600">·</span> Approved by {{ $r->approver->full_name }} @endif
                                    </p>
                                    @if($r->note && $auth->isAdmin())
                                        <p class="text-[11px] text-slate-400 mt-1 italic" x-show="showSensitive">{{ $r->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <div class="flex flex-col items-center justify-center py-10 text-center rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-2"><i data-lucide="trending-up" class="h-6 w-6"></i></div>
                        <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No pay reviews yet</p>
                        <p class="text-xs text-slate-400 mt-1">{{ $auth->isAdmin() ? 'Record the starting salary or a pay review to build the timeline.' : 'Salary history will appear here.' }}</p>
                    </div>
                @endif
            </div>

            {{-- Add pay review modal (admin) --}}
            @if($auth->isAdmin())
                <script>window.__payCurrent = @json($cur !== null ? (float) $cur : null);</script>
                <div x-show="payReviewOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/50" @click="payReviewOpen = false"></div>
                    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-slate-800 max-h-[90vh] overflow-y-auto"
                         x-data="{ prev: window.__payCurrent, newSalary: '',
                                   inc() { return (this.prev !== null && this.newSalary !== '') ? (parseFloat(this.newSalary) - this.prev) : null; },
                                   pct() { return (this.prev && this.prev > 0 && this.newSalary !== '') ? Math.round((this.inc() / this.prev) * 10000) / 100 : null; } }">
                        <form action="{{ route('employees.pay-reviews.store', $employee->id) }}" method="POST">
                            @csrf
                            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Add pay review</h2>
                                <button type="button" @click="payReviewOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs dark:bg-slate-900 dark:border-slate-700">
                                    <span class="text-slate-500">Previous salary:</span>
                                    <span class="font-bold text-slate-800 dark:text-white" x-text="prev !== null ? ('{{ $curCode }} ' + Number(prev).toLocaleString()) : 'None (starting salary)'"></span>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Effective date <span class="text-rose-500">*</span></label>
                                        <input type="date" name="effective_date" value="{{ now()->toDateString() }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">New salary <span class="text-rose-500">*</span></label>
                                        <input type="number" name="new_salary" x-model="newSalary" min="0" step="0.01" required placeholder="0" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    </div>
                                </div>
                                <div x-show="inc() !== null" x-cloak class="flex items-center gap-2 text-xs font-bold" :class="inc() >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                    <i data-lucide="trending-up" class="h-4 w-4"></i>
                                    <span x-text="(inc() >= 0 ? '+' : '') + '{{ $curCode }} ' + Number(inc()).toLocaleString() + (pct() !== null ? ' (' + (pct() >= 0 ? '+' : '') + pct() + '%)' : '')"></span>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Pay type</label>
                                        <select name="pay_type" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            <option value="salary">Salary</option><option value="hourly">Hourly</option><option value="contract">Contract</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Pay schedule</label>
                                        <select name="pay_schedule" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            <option value="monthly">Monthly</option><option value="biweekly">Bi-weekly</option><option value="weekly">Weekly</option><option value="annual">Annual</option><option value="hourly">Hourly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Review type</label>
                                        <select name="review_type" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            <option value="annual">Annual</option><option value="mid_year">Mid-Year</option><option value="promotion">Promotion</option><option value="market_adjustment">Market Adjustment</option><option value="correction">Correction</option><option value="joining">Joining</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Approved by</label>
                                        <select name="approved_by" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            <option value="">—</option>
                                            @foreach($allUsers as $u)<option value="{{ $u->id }}">{{ trim(($u->last_name ? $u->last_name.', ' : '').$u->first_name) }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason <span class="text-rose-500">*</span></label>
                                    <input type="text" name="reason" required maxlength="255" placeholder="e.g. Annual appraisal" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    <p class="text-[11px] text-slate-400 mt-1">A reason is required (especially for any decrease).</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Note <span class="text-slate-400 normal-case font-medium">(private, optional)</span></label>
                                    <textarea name="note" rows="2" maxlength="1000" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea>
                                </div>
                                <input type="hidden" name="currency" value="{{ $curCode }}">
                            </div>
                            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                                <button type="button" @click="payReviewOpen = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Save pay review</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        {{-- PROBATION — Job tab (admin + self) --}}
        @if($auth->isAdmin() || $isSelf)
            @php $isAdminViewer = $auth->isAdmin(); @endphp
            <div x-show="tab === 'job'" x-cloak class="border-b border-slate-100 dark:border-slate-700/60 pb-7 mb-7" x-data="{ extend: false, fail: false }">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Probation</h3>
                </div>

                @if($probation)
                    @php
                        $days = $probation->days_remaining;
                        $badge = $probation->status === 'passed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                            : ($probation->status === 'failed' ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400'
                            : ($probation->end_date->lt(now()->startOfDay()) ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'));
                    @endphp
                    <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700 space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge }}">{{ $probation->status_label }}</span>
                            @if($probation->is_extended)<span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">Extended</span>@endif
                            @if($probation->status === 'active')
                                <span class="text-xs font-semibold {{ $days < 0 ? 'text-rose-600' : ($days <= 14 ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ $days < 0 ? abs($days).' '.Str::plural('day', abs($days)).' overdue' : $days.' '.Str::plural('day', $days).' remaining' }}
                                </span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-2 text-sm">
                            <div><span class="block text-[11px] font-bold text-slate-400 uppercase">Start</span>{{ $probation->start_date->format('d M Y') }}</div>
                            <div><span class="block text-[11px] font-bold text-slate-400 uppercase">End</span>{{ $probation->end_date->format('d M Y') }}</div>
                            @if($probation->is_extended)<div><span class="block text-[11px] font-bold text-slate-400 uppercase">Original end</span>{{ $probation->original_end_date->format('d M Y') }}</div>@endif
                        </div>
                        @if($probation->note)<p class="text-sm text-slate-600 dark:text-slate-300">{{ $probation->note }}</p>@endif

                        @if($isAdminViewer && $probation->status === 'active')
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button type="button" @click="extend = true" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="calendar-plus" class="h-3.5 w-3.5"></i> Extend</button>
                                <form action="{{ route('employees.probation.confirm', [$employee->id, $probation->id]) }}" method="POST" onsubmit="return confirm('Confirm {{ $employee->first_name }} — probation passed?');">@csrf<button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700"><i data-lucide="check" class="h-3.5 w-3.5"></i> Confirm</button></form>
                                <button type="button" @click="fail = true" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:bg-slate-700 dark:border-rose-500/30"><i data-lucide="x-circle" class="h-3.5 w-3.5"></i> Not confirmed</button>
                            </div>
                        @endif

                        @if($probation->events->count())
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 space-y-2">
                                <span class="text-[11px] font-bold text-slate-400 uppercase">History</span>
                                @foreach($probation->events as $ev)
                                    <div class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-2">
                                        <i data-lucide="dot" class="h-3.5 w-3.5 mt-0.5 text-slate-400"></i>
                                        <span>
                                            <span class="font-semibold capitalize text-slate-700 dark:text-slate-200">{{ $ev->action }}</span>
                                            @if($ev->new_end_date) → {{ $ev->new_end_date->format('d M Y') }} @endif
                                            @if($ev->note) — {{ $ev->note }} @endif
                                            <span class="text-slate-300 dark:text-slate-600">·</span> {{ optional($ev->created_at)->format('d M Y') }}@if($ev->reviewer) by {{ $ev->reviewer->full_name }}@endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($isAdminViewer)
                            <form action="{{ route('employees.probation.destroy', [$employee->id, $probation->id]) }}" method="POST" onsubmit="return confirm('Remove this probation record?');" class="pt-1">@csrf @method('DELETE')<button type="submit" class="text-[11px] font-semibold text-slate-400 hover:text-rose-500">Remove probation record</button></form>
                        @endif
                    </div>

                    {{-- Extend modal --}}
                    @if($isAdminViewer)
                        <div x-show="extend" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="absolute inset-0 bg-slate-900/50" @click="extend = false"></div>
                            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                                <form action="{{ route('employees.probation.extend', [$employee->id, $probation->id]) }}" method="POST">
                                    @csrf
                                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Extend probation</h2><button type="button" @click="extend = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button></div>
                                    <div class="p-6 space-y-4">
                                        <p class="text-xs text-slate-500">Current end: <span class="font-bold text-slate-800 dark:text-white">{{ $probation->end_date->format('d M Y') }}</span></p>
                                        <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">New end date <span class="text-rose-500">*</span></label><input type="date" name="new_end_date" required min="{{ $probation->end_date->copy()->addDay()->toDateString() }}" value="{{ $probation->end_date->copy()->addMonths(1)->toDateString() }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                        <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason <span class="text-rose-500">*</span></label><textarea name="reason" required rows="2" maxlength="1000" placeholder="e.g. Needs more time to meet performance targets" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea></div>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60"><button type="button" @click="extend = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button><button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Extend</button></div>
                                </form>
                            </div>
                        </div>
                        {{-- Fail modal --}}
                        <div x-show="fail" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="absolute inset-0 bg-slate-900/50" @click="fail = false"></div>
                            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                                <form action="{{ route('employees.probation.fail', [$employee->id, $probation->id]) }}" method="POST">
                                    @csrf
                                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Mark not confirmed</h2><button type="button" @click="fail = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button></div>
                                    <div class="p-6 space-y-4"><div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Reason <span class="text-rose-500">*</span></label><textarea name="reason" required rows="3" maxlength="1000" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea></div></div>
                                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60"><button type="button" @click="fail = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button><button type="submit" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700">Mark not confirmed</button></div>
                                </form>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="rounded-xl border border-dashed border-slate-200 dark:border-slate-700 p-5 text-center">
                        <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">No probation set</p>
                        @if($isAdminViewer)
                            @php $defStart = $employee->hire_date ? \Carbon\Carbon::parse($employee->hire_date) : now(); @endphp
                            <p class="text-xs text-slate-400 mt-1">Start a 3-month probation (defaults from the hire date).</p>
                            <form action="{{ route('employees.probation.store', $employee->id) }}" method="POST" class="mt-3 flex flex-wrap items-end justify-center gap-2">
                                @csrf
                                <div class="text-left"><label class="block text-[10px] font-bold text-slate-400 uppercase">Start</label><input type="date" name="start_date" value="{{ $defStart->toDateString() }}" class="rounded-lg border-slate-300 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                <div class="text-left"><label class="block text-[10px] font-bold text-slate-400 uppercase">End</label><input type="date" name="end_date" value="{{ $defStart->copy()->addMonths(3)->toDateString() }}" class="rounded-lg border-slate-300 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="play" class="h-3.5 w-3.5"></i> Start probation</button>
                            </form>
                        @else
                            <p class="text-xs text-slate-400 mt-1">No probation period is currently recorded.</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- ZKTeco biometric mapping (admin only) — Job tab --}}
        @if($auth->hasRole('super_admin') || $auth->hasRole('hr_admin'))
            <div x-show="tab === 'job'" x-cloak class="border-b border-slate-100 dark:border-slate-700/60 pb-7 mb-7">
                <h3 class="text-base font-bold text-slate-800 dark:text-white mb-5">Biometric Device (ZKTeco)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-6">
                    @if($editing)
                        <div class="flex flex-col gap-1.5"><label class="text-xs text-slate-500 font-bold dark:text-slate-400">Device User ID (UID)</label><input type="number" name="zkteco_uid" value="{{ old('zkteco_uid',$employee->zkteco_uid) }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"></div>
                        <div class="flex flex-col gap-1.5"><label class="text-xs text-slate-500 font-bold dark:text-slate-400">Device Employee ID</label><input type="text" name="zkteco_employee_id" value="{{ old('zkteco_employee_id',$employee->zkteco_employee_id) }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white"></div>
                    @else
                        <div class="flex flex-col gap-0.5"><span class="text-xs text-slate-400 font-medium dark:text-slate-500">Device User ID (UID)</span><span class="text-sm font-semibold {{ $employee->zkteco_uid?'text-slate-800 dark:text-white':'text-slate-400 italic' }}">{{ $employee->zkteco_uid??'Not mapped' }}</span></div>
                        <div class="flex flex-col gap-0.5"><span class="text-xs text-slate-400 font-medium dark:text-slate-500">Device Employee ID</span><span class="text-sm font-semibold {{ $employee->zkteco_employee_id?'text-slate-800 dark:text-white':'text-slate-400 italic' }}">{{ $employee->zkteco_employee_id??'—' }}</span></div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Work schedule (Job tab) --}}
    <div x-show="section === 'information' && tab === 'job'" x-cloak>
        @include('employees.partials.work-schedule')
    </div>

    @if($editing)
        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
            <a href="{{ route('employees.profile',$employee->id) }}" id="cancel-edit-btn"
               class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">Cancel</a>
            <button type="submit" form="profile-edit-form"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-bold text-slate-900 shadow-md hover:bg-brand-700 transition">
                <i data-lucide="save" class="h-4 w-4"></i><span>Save Profile Changes</span>
            </button>
        </div>
        </form>
    @endif

</div>{{-- end profile-page-root --}}

@if($editing)
<script>
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const preview = document.getElementById('avatar-preview');
                const placeholder = document.getElementById('avatar-placeholder');
                if (preview) preview.src = e.target.result;
                else if (placeholder) placeholder.outerHTML = `<img id="avatar-preview" src="${e.target.result}" class="h-20 w-20 rounded-2xl object-cover ring-2 ring-brand-200">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    let formDirty = false;
    const profileForm = document.getElementById('profile-edit-form');
    if (profileForm) {
        profileForm.addEventListener('change', () => formDirty = true);
        profileForm.addEventListener('input', () => formDirty = true);
        profileForm.addEventListener('submit', () => formDirty = false);
    }
    const cancelBtn = document.getElementById('cancel-edit-btn');
    if (cancelBtn) cancelBtn.addEventListener('click', () => formDirty = false);
    window.addEventListener('beforeunload', e => { if (formDirty) { e.preventDefault(); e.returnValue=''; } });
    window.addEventListener('load', () => {
        if (window.location.hash) {
            const t = document.querySelector(window.location.hash);
            if (t) setTimeout(() => { t.scrollIntoView({behavior:'smooth',block:'start'}); t.classList.add('ring-2','ring-brand-400','ring-offset-2'); setTimeout(()=>t.classList.remove('ring-2','ring-brand-400','ring-offset-2'),2000); }, 200);
        }
    });
</script>
@endif
@endsection
