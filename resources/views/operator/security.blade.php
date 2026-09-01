@extends('layouts.operator')

@section('title', 'Security')
@section('breadcrumb', 'Security')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="shield-check" class="h-6 w-6 text-indigo-500"></i> Security
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Protect your operator account with two-factor authentication.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ $errors->first() }}</div>@endif

    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-900 dark:text-white">Two-factor authentication</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">A code from your phone is required at login.</p>
            </div>
            @if($state==='enabled')
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"><i data-lucide="check-circle" class="h-3.5 w-3.5"></i> On</span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300"><i data-lucide="shield-off" class="h-3.5 w-3.5"></i> Off</span>
            @endif
        </div>

        {{-- DISABLED --}}
        @if($state==='disabled')
            <form action="{{ route('operator.security.enable') }}" method="POST" class="mt-5">@csrf
                <button class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700"><i data-lucide="shield-plus" class="h-4 w-4"></i> Enable two-factor</button>
            </form>

        {{-- PENDING (scan + confirm) --}}
        @elseif($state==='pending')
            <div class="mt-5 grid sm:grid-cols-[auto,1fr] gap-5 items-start">
                <div class="rounded-xl bg-white p-3 border border-slate-200 dark:border-slate-600 w-max"><div id="qr"></div></div>
                <div class="space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-300">1. Scan this QR code with Google Authenticator, Authy or 1Password.</p>
                    <div>
                        <p class="text-[11px] text-slate-400">Or enter this key manually:</p>
                        <code class="text-sm font-mono font-bold text-slate-800 dark:text-white break-all">{{ $secret }}</code>
                    </div>
                    <form action="{{ route('operator.security.confirm') }}" method="POST" class="pt-1">@csrf
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">2. Enter the 6-digit code to confirm</label>
                        <div class="flex gap-2">
                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-center font-mono tracking-widest dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
                (function(){ var el=document.getElementById('qr');
                    if(el && window.QRCode){ new QRCode(el,{text:@json($qrUri),width:168,height:168,correctLevel:QRCode.CorrectLevel.M}); } })();
            </script>

        {{-- ENABLED --}}
        @else
            <div class="mt-5 space-y-4">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-600">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-slate-800 dark:text-white">Recovery codes</p>
                        <form action="{{ route('operator.security.recovery') }}" method="POST">@csrf
                            <button class="text-xs font-bold text-indigo-600 hover:text-indigo-700">Regenerate</button>
                        </form>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Each can be used once if you lose your device. Store them somewhere safe.</p>
                    <div class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-sm">
                        @foreach($recoveryCodes as $code)<span class="rounded bg-slate-50 px-2 py-1 text-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $code }}</span>@endforeach
                    </div>
                </div>

                <form action="{{ route('operator.security.disable') }}" method="POST" class="rounded-xl border border-rose-200 p-4 dark:border-rose-500/30">@csrf @method('DELETE')
                    <p class="text-sm font-bold text-slate-800 dark:text-white">Turn off two-factor</p>
                    <div class="flex gap-2 mt-2">
                        <input type="password" name="password" placeholder="Confirm your password" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <button class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Disable</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
