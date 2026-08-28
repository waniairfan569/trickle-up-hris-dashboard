@extends('layouts.hr-app')

@section('title', 'Pending Invitations')
@section('breadcrumb', 'Pending Invitations')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight dark:text-white">Pending Invitations</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage employees who haven't completed their account setup.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('employees.create') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span>Invite Employee</span>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/80">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Employee</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Department</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Sent By</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Sent At</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 min-w-[140px]">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700/80 dark:bg-slate-800">
                    @forelse($pendingUsers as $user)
                        @php
                            $isExpired = $user->invitation_expires_at && $user->invitation_expires_at->isPast();
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-700/50">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center text-white font-bold shadow-sm">
                                            {{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="font-bold text-slate-900 dark:text-white">
                                            <a href="{{ route('employees.profile', $user->id) }}" class="hover:text-brand-600 hover:underline">
                                                {{ $user->full_name }}
                                            </a>
                                        </div>
                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <div class="text-sm text-slate-900 dark:text-white">{{ $user->department->name ?? 'N/A' }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $user->job_title ?? 'Employee' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                @if($user->account_status === 'invited')
                                    @if($isExpired)
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
                                            Expired
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20">
                                            Pending
                                        </span>
                                    @endif
                                @elseif($user->account_status === 'active' && $user->must_change_password)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20">
                                        Must Change Password
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ $user->invitedBy->full_name ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">
                                @if($user->invitation_sent_at)
                                    <div title="{{ $user->invitation_sent_at }}">{{ $user->invitation_sent_at->diffForHumans() }}</div>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 sm:pr-6 text-right text-sm font-medium min-w-[140px]">
                                @if($user->account_status === 'invited')
                                    <div class="flex items-center justify-end gap-2" x-data="{ editOpen: false }">
                                        <button type="button" @click="editOpen = true" class="text-slate-600 hover:text-slate-900 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 px-2 py-1.5 rounded-lg transition-colors flex items-center gap-1.5" title="Edit email & resend">
                                            <i data-lucide="pencil" class="h-4 w-4"></i>
                                            <span class="hidden sm:inline">Edit email</span>
                                        </button>
                                        <form action="{{ route('invitation.resend', $user->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-brand-600 hover:text-brand-900 dark:text-brand-400 dark:hover:text-brand-300 bg-brand-50 hover:bg-brand-100 dark:bg-brand-500/10 dark:hover:bg-brand-500/20 px-2 py-1.5 rounded-lg transition-colors flex items-center gap-1.5" title="Resend Invitation">
                                                <i data-lucide="mail" class="h-4 w-4"></i>
                                                <span class="hidden sm:inline">Resend</span>
                                            </button>
                                        </form>
                                        <form action="{{ route('invitation.cancel', $user->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 px-2 py-1.5 rounded-lg transition-colors" title="Cancel Invitation" onclick="return confirm('Are you sure you want to cancel this invitation?')">
                                                <i data-lucide="x-circle" class="h-4 w-4"></i>
                                            </button>
                                        </form>

                                        <!-- Edit-email modal -->
                                        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                            <div class="absolute inset-0 bg-slate-900/50" @click="editOpen = false"></div>
                                            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-slate-800 text-left">
                                                <form action="{{ route('invitation.update-email', $user->id) }}" method="POST">
                                                    @csrf
                                                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                                                        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Edit email &amp; resend</h2>
                                                        <button type="button" @click="editOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
                                                    </div>
                                                    <div class="p-6 space-y-3">
                                                        <p class="text-xs text-slate-500 dark:text-slate-400">Update the email for <span class="font-semibold">{{ $user->full_name }}</span> and resend the invitation to the new address.</p>
                                                        <div>
                                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email address</label>
                                                            <input type="email" name="email" value="{{ $user->email }}" required class="w-full rounded-xl border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                                                        <button type="button" @click="editOpen = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="send" class="h-4 w-4"></i> Save &amp; resend</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-12 w-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3">
                                        <i data-lucide="mail-check" class="h-6 w-6 text-slate-400"></i>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">No pending invitations</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">All invited employees have set up their accounts.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Invitation history — full record (sent / accepted / expired) --}}
    <div id="history" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden scroll-mt-6">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="history" class="h-4 w-4 text-slate-400"></i> Invitation history</h2>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach(['all' => 'All', 'accepted' => 'Accepted', 'pending' => 'Pending', 'expired' => 'Expired'] as $key => $label)
                    <a href="{{ route('employees.pending-invitations', array_filter(['invite_status' => $key === 'all' ? null : $key])) }}#history"
                       class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold transition {{ ($inviteStatus ?? 'all') === $key ? 'bg-brand-600 text-slate-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300' }}">
                        {{ $label }}
                        <span class="rounded-full px-1.5 text-[10px] {{ ($inviteStatus ?? 'all') === $key ? 'bg-black/10' : 'bg-white/70 dark:bg-slate-800/70' }}">{{ $inviteCounts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700/80">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="py-3 pl-6 pr-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Employee</th>
                        <th class="px-3 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Department</th>
                        <th class="px-3 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Invited by</th>
                        <th class="px-3 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Sent</th>
                        <th class="px-3 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Accepted</th>
                        <th class="px-3 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700/80 dark:bg-slate-800">
                    @forelse($history as $user)
                        @php
                            $iStatus = $user->invitation_accepted_at ? 'accepted'
                                : (($user->invitation_expires_at && $user->invitation_expires_at->isPast()) ? 'expired' : 'pending');
                            $iBadge = [
                                'accepted' => ['Accepted', 'check', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400', 'from-emerald-400 to-emerald-600'],
                                'pending'  => ['Pending',  'clock', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', 'from-amber-400 to-amber-600'],
                                'expired'  => ['Expired',  'x',     'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400', 'from-slate-400 to-slate-600'],
                            ][$iStatus];
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-700/50">
                            <td class="whitespace-nowrap py-3.5 pl-6 pr-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br {{ $iBadge[3] }} flex items-center justify-center text-white text-xs font-bold shadow-sm">{{ $user->initials }}</div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 dark:text-white">{{ $user->full_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ optional($user->department)->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ optional($user->invitedBy)->full_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-sm text-slate-500 dark:text-slate-400" title="{{ $user->invitation_sent_at }}">{{ optional($user->invitation_sent_at)->format('d M Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5 text-sm text-slate-600 dark:text-slate-300" title="{{ $user->invitation_accepted_at }}">{{ optional($user->invitation_accepted_at)->format('d M Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3.5">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $iBadge[2] }}"><i data-lucide="{{ $iBadge[1] }}" class="h-3 w-3"></i> {{ $iBadge[0] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-12 w-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3"><i data-lucide="history" class="h-6 w-6 text-slate-400"></i></div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">No {{ ($inviteStatus ?? 'all') !== 'all' ? $inviteStatus . ' ' : '' }}invitations</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every invitation sent — accepted, still pending, or expired — is recorded here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($history->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">{{ $history->links() }}</div>
        @endif
    </div>
</div>
@endsection
