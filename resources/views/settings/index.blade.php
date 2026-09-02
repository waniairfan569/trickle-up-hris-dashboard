@extends('layouts.hr-app')

@section('title', 'Settings')
@section('breadcrumb', 'Settings')

@php
    use App\Support\NotificationCategories;
    $np = $user->notification_prefs ?? [];
    $isOn = fn ($cat, $ch) => (bool) ($np[$cat][$ch] ?? true);
    $currentTz = ($user->use_custom_timezone && $user->timezone) ? $user->timezone : '';
    $currentDf = $user->date_format ?: 'd M Y';
    $currentWs = $user->week_start ?: 'monday';
    $dateFormats = ['d M Y' => now()->format('d M Y'), 'M d, Y' => now()->format('M d, Y'), 'd/m/Y' => now()->format('d/m/Y'), 'm/d/Y' => now()->format('m/d/Y'), 'Y-m-d' => now()->format('Y-m-d')];
    $tabs = [
        'notifications' => ['Notifications', 'bell'],
        'appearance'    => ['Appearance', 'palette'],
        'security'      => ['Security', 'shield'],
        'preferences'   => ['Preferences', 'sliders-horizontal'],
    ];
@endphp

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ session('tab', 'notifications') }}' }">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your notifications, appearance, security and preferences.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">

        {{-- Vertical tab nav --}}
        <nav class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible rounded-2xl bg-white border border-slate-200/80 shadow-sm p-2 dark:bg-slate-800 dark:border-slate-700">
            @foreach($tabs as $key => [$label, $icon])
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700/50'"
                        class="flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-bold transition whitespace-nowrap shrink-0 text-left">
                    <i data-lucide="{{ $icon }}" class="h-4 w-4 shrink-0"></i> {{ $label }}
                </button>
            @endforeach
        </nav>

        <div>
            {{-- ── NOTIFICATIONS ──────────────────────────────────── --}}
            <div x-show="tab === 'notifications'" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <form method="POST" action="{{ route('settings.notifications') }}">
                    @csrf @method('PUT')
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Notifications</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Choose how you're notified. <b>Email</b> lands in your inbox; <b>In-app</b> shows in the 🔔 bell.</p>
                    </div>

                    <div class="hidden sm:grid grid-cols-[1fr_80px_80px] gap-2 px-6 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                        <span>Category</span><span class="text-center">Email</span><span class="text-center">In-app</span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach(NotificationCategories::CATEGORIES as $cat => [$label, $desc, $icon])
                            @php $mandEmail = in_array($cat, NotificationCategories::MANDATORY_EMAIL, true); @endphp
                            <div class="grid grid-cols-[1fr_80px_80px] gap-2 items-center px-6 py-3.5">
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-700/50 shrink-0"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white">{{ $label }}</p>
                                        <p class="text-xs text-slate-400 leading-snug">{{ $desc }}</p>
                                    </div>
                                </div>
                                <div class="flex justify-center">
                                    @if($mandEmail)
                                        <input type="checkbox" checked disabled title="Required — always emailed" class="h-5 w-5 rounded border-slate-300 text-brand-600 opacity-60 cursor-not-allowed">
                                        <input type="hidden" name="prefs[{{ $cat }}][mail]" value="1">
                                    @else
                                        <input type="checkbox" name="prefs[{{ $cat }}][mail]" value="1" @checked($isOn($cat, 'mail')) class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                                    @endif
                                </div>
                                <div class="flex justify-center">
                                    <input type="checkbox" name="prefs[{{ $cat }}][database]" value="1" @checked($isOn($cat, 'database')) class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <p class="text-[11px] text-slate-400">Some categories are always emailed for record-keeping.</p>
                        <button type="submit" class="btn-brand btn-sm">Save preferences</button>
                    </div>
                </form>
            </div>

            {{-- ── APPEARANCE ─────────────────────────────────────── --}}
            <div x-show="tab === 'appearance'" x-cloak class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <form method="POST" action="{{ route('settings.appearance') }}" x-data="{ theme: '{{ $user->theme ?? 'system' }}', apply(t){ const d = t==='dark' || (t==='system' && matchMedia('(prefers-color-scheme: dark)').matches); const el = document.documentElement; el.classList.toggle('dark', d); el.style.backgroundColor = d ? '#0f172a' : '#f8fafc'; el.style.colorScheme = d ? 'dark' : 'light'; } }">
                    @csrf @method('PUT')
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Appearance</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Pick a theme. <b>System</b> follows your device.</p>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach(['light' => ['Light','sun'], 'dark' => ['Dark','moon'], 'system' => ['System','laptop']] as $val => [$lbl, $ic])
                                <label @click="theme='{{ $val }}'; apply('{{ $val }}')"
                                       :class="theme === '{{ $val }}' ? 'border-brand-400 ring-2 ring-brand-400/40' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'"
                                       class="cursor-pointer rounded-xl border p-4 flex flex-col items-center gap-2 transition">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="{{ $ic }}" class="h-5 w-5"></i></span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-white">{{ $lbl }}</span>
                                    <input type="radio" name="theme" value="{{ $val }}" x-model="theme" class="sr-only">
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="submit" class="btn-brand btn-sm">Save appearance</button>
                    </div>
                </form>
            </div>

            {{-- ── SECURITY ───────────────────────────────────────── --}}
            <div x-show="tab === 'security'" x-cloak class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <form method="POST" action="{{ route('settings.password') }}">
                    @csrf @method('PUT')
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Change password</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Use at least 8 characters. You'll stay signed in on this device.</p>
                    </div>
                    <div class="p-6 space-y-4 max-w-md">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Current password</label>
                            <input type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">New password</label>
                            <input type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Confirm new password</label>
                            <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                    </div>
                    <div class="flex justify-end px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="submit" class="btn-brand btn-sm">Update password</button>
                    </div>
                </form>
            </div>

            {{-- ── TWO-FACTOR AUTHENTICATION ─────────────────────── --}}
            <div x-show="tab === 'security'" x-cloak class="mt-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i data-lucide="shield-check" class="h-4 w-4 text-brand-500"></i> Two-factor authentication
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Add a one-time code from an authenticator app to your login. Strongly recommended for admins.</p>
                    </div>
                    @if($tfaState === 'enabled')
                        <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><i data-lucide="check-circle" class="h-3.5 w-3.5"></i> On</span>
                    @elseif($tfaState === 'pending')
                        <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><i data-lucide="clock" class="h-3.5 w-3.5"></i> Finish setup</span>
                    @else
                        <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">Off</span>
                    @endif
                </div>

                <div class="p-6">
                    @if($tfaState === 'disabled')
                        <form method="POST" action="{{ route('settings.2fa.enable') }}">
                            @csrf
                            <button type="submit" class="btn-brand btn-sm inline-flex items-center gap-2"><i data-lucide="shield-plus" class="h-4 w-4"></i> Enable two-factor</button>
                        </form>

                    @elseif($tfaState === 'pending')
                        <div class="grid gap-6 md:grid-cols-[auto,1fr] items-start max-w-2xl">
                            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200 dark:ring-slate-600 w-fit">
                                <div id="settings-tfa-qr"></div>
                            </div>
                            <div class="space-y-3">
                                <p class="text-sm text-slate-600 dark:text-slate-300">Scan the QR with Google Authenticator, 1Password, Authy, etc. Can't scan? Enter this key:</p>
                                <code class="block text-sm font-mono font-bold text-slate-800 dark:text-white break-all bg-slate-50 dark:bg-slate-900 rounded-lg px-3 py-2">{{ $tfaSecret }}</code>
                                <form method="POST" action="{{ route('settings.2fa.confirm') }}" class="flex items-end gap-2 pt-1">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">6-digit code</label>
                                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="w-36 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm tracking-widest font-mono shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    </div>
                                    <button type="submit" class="btn-brand btn-sm">Verify &amp; turn on</button>
                                </form>
                                @error('code')<p class="text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                        <script>(function(){var el=document.getElementById('settings-tfa-qr');
                            if(el && window.QRCode){ new QRCode(el,{text:@json($tfaQrUri),width:168,height:168,correctLevel:QRCode.CorrectLevel.M}); }})();</script>

                    @else
                        <p class="text-sm text-slate-600 dark:text-slate-300">Two-factor is protecting your account. Keep these recovery codes somewhere safe — each works once if you lose your device:</p>
                        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs max-w-lg">
                            @foreach($recoveryCodes as $code)<span class="rounded bg-slate-50 px-2 py-1 text-slate-700 dark:bg-slate-900 dark:text-slate-200 text-center">{{ $code }}</span>@endforeach
                        </div>
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('settings.2fa.recovery') }}">
                                @csrf
                                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/40">Regenerate recovery codes</button>
                            </form>
                            <form method="POST" action="{{ route('settings.2fa.disable') }}" class="flex items-end gap-2">
                                @csrf @method('DELETE')
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Password to disable</label>
                                    <input type="password" name="password" autocomplete="current-password" class="w-48 rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                </div>
                                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700">Turn off</button>
                            </form>
                        </div>
                        @error('password')<p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                    @endif
                </div>
            </div>

            {{-- ── PREFERENCES ────────────────────────────────────── --}}
            <div x-show="tab === 'preferences'" x-cloak class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <form method="POST" action="{{ route('settings.preferences') }}">
                    @csrf @method('PUT')
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Preferences</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Timezone affects how your attendance times are shown.</p>
                    </div>
                    <div class="p-6 space-y-4 max-w-md">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Timezone</label>
                            <select name="timezone" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <option value="" @selected($currentTz === '')>Automatic (company default)</option>
                                @foreach($tzList as $region => $zones)
                                    <optgroup label="{{ $region }}">
                                        @foreach($zones as $tz => $label)
                                            <option value="{{ $tz }}" @selected($currentTz === $tz)>{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Date format</label>
                            <select name="date_format" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                @foreach($dateFormats as $fmt => $example)
                                    <option value="{{ $fmt }}" @selected($currentDf === $fmt)>{{ $example }}  ({{ $fmt }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Week starts on</label>
                            <select name="week_start" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <option value="monday" @selected($currentWs === 'monday')>Monday</option>
                                <option value="sunday" @selected($currentWs === 'sunday')>Sunday</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="submit" class="btn-brand btn-sm">Save preferences</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
