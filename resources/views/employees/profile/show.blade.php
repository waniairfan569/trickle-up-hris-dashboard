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

<div class="space-y-8" id="profile-page-root" x-data="{ tab: 'personal', section: 'information', showSensitive: false }">
    <!-- Back Button -->
    <div>
        <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 transition dark:text-slate-400 dark:hover:text-white">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Back
        </a>
    </div>

    <!-- Header Hero Banner -->
    <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <!-- Banner Gradient -->
        <div class="h-32 bg-gradient-to-r from-brand-500 to-indigo-600"></div>
        
        <!-- Profile Picture & Brief -->
        <div class="px-6 pb-6 relative flex flex-col items-center text-center sm:flex-row sm:items-end sm:text-left sm:gap-6 -mt-10">
            <div class="relative">
                @if($employee->avatar_url)
                    <img src="{{ $employee->avatar_url }}" alt="{{ $employee->full_name }}" class="h-24 w-24 rounded-2xl object-cover border-4 border-white bg-slate-100 ring-1 ring-slate-200/50 shadow-md dark:border-slate-800">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-indigo-500 text-3xl font-bold text-white border-4 border-white shadow-md dark:border-slate-800">
                        {{ $employee->initials }}
                    </div>
                @endif
            </div>

            <div class="mt-4 flex-1 sm:mt-0 space-y-1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $employee->full_name }}</h1>
                    <div class="flex items-center gap-1.5 justify-center sm:justify-start flex-wrap">
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 capitalize dark:bg-emerald-500/10 dark:text-emerald-400">
                            {{ \Illuminate\Support\Str::title($employee->employee_status ?? 'Active') }}
                        </span>
                        
                        @if($employee->account_status === 'invited')
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-700 capitalize dark:bg-amber-500/10 dark:text-amber-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Pending
                            </span>
                        @elseif($employee->account_status === 'deactivated')
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 capitalize dark:bg-slate-700 dark:text-slate-300">
                                Archived
                            </span>
                        @endif
                    </div>
                </div>
                @php
                    $jt = trim((string) $employee->job_title);
                    $showJobTitle = $jt !== '' && strtolower($jt) !== 'employee';
                @endphp
                @if($showJobTitle || $employee->department)
                <p class="text-xs font-semibold text-brand-600 dark:text-brand-400">
                    @if($showJobTitle){{ $jt }}@endif
                    @if($employee->department)
                        @if($showJobTitle) &bull; @endif
                        <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-100">{{ $employee->department->name }}</span>
                    @endif
                </p>
                @endif
                <div class="flex flex-wrap items-center gap-2 justify-center sm:justify-start pt-1">
                    @if($employee->companyEntity)
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->companyEntity->name }}</span>
                    @endif
                    @if($employee->email)
                        <a href="mailto:{{ $employee->email }}" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition dark:bg-slate-700 dark:text-slate-300">
                            <i data-lucide="mail" class="h-3 w-3"></i>{{ $employee->email }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Profile Action Buttons -->
            @if($canEdit && !$editing)
                <div class="mt-4 flex flex-wrap gap-2 sm:mt-0">
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
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Recent Attendance</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700">
                        <tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Clock In</th><th class="px-6 py-3">Clock Out</th><th class="px-6 py-3">Hours Worked</th><th class="px-6 py-3">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-xs">
                        @php $atts=\App\Models\AttendanceRecord::where('user_id',$employee->id)->orderByDesc('date')->limit(14)->get(); @endphp
                        @forelse($atts as $att)
                            @php $as=$att->status??'present'; $asc=match($as){'present'=>'bg-emerald-50 text-emerald-700','absent'=>'bg-red-50 text-red-700','late'=>'bg-amber-50 text-amber-700',default=>'bg-slate-50 text-slate-600'}; @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <td class="px-6 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ \Carbon\Carbon::parse($att->date)->format('D, d M Y') }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $att->clock_in?\Carbon\Carbon::parse($att->clock_in)->format('h:i A'):'-' }}</td>
                                <td class="px-6 py-3 text-slate-600">{{ $att->clock_out?\Carbon\Carbon::parse($att->clock_out)->format('h:i A'):'-' }}</td>
                                <td class="px-6 py-3 font-semibold text-slate-700 dark:text-slate-200">@if($att->clock_in&&$att->clock_out){{ floor($att->total_minutes_worked/60) }}h {{ $att->total_minutes_worked%60 }}m@else-@endif</td>
                                <td class="px-6 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold capitalize {{ $asc }}">{{ $as }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 italic">No attendance records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- DOCUMENTS TAB --}}
    <div x-show="section === 'files'" x-cloak>
        <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm dark:bg-slate-800 dark:border-slate-700 p-6">
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-700 mb-3"><i data-lucide="file-text" class="h-7 w-7 text-slate-400"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No documents yet</p>
                <p class="text-xs text-slate-400 mt-1">Documents will appear here.</p>
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
