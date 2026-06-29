{{--
    On-leave-today widget. Params:
      $people  - collection from TimeOffRequest::onLeaveToday()
      $compact - bool, true = slim strip (attendance pages), false = full card (dashboard)
--}}
@php($compact = $compact ?? false)

@if($compact)
    @if($people->count())
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-5 py-4 dark:bg-slate-800 dark:border-slate-700">
            <div class="flex items-center flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-700 uppercase tracking-wider mr-1">
                    <i data-lucide="palmtree" class="h-4 w-4"></i> On leave today ({{ $people->count() }})
                </span>
                @foreach($people as $p)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 pl-1 pr-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-200 text-[9px] font-bold text-blue-800">{{ $p['initials'] }}</span>
                        {{ $p['name'] }}
                        @if($p['half'])<span class="text-blue-400">· {{ $p['half'] }}</span>@endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif
@else
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i data-lucide="palmtree" class="h-4 w-4 text-blue-500"></i> On leave today
            </h2>
            <span class="text-xs font-bold text-blue-600 bg-blue-50 rounded-full px-2.5 py-0.5 dark:bg-blue-500/10">{{ $people->count() }}</span>
        </div>
        @if($people->count())
            <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($people as $p)
                    <div class="flex items-center gap-3 px-5 py-3">
                        @if($p['avatar'])
                            <img src="{{ $p['avatar'] }}" class="h-8 w-8 rounded-full object-cover" alt="">
                        @else
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-500/20">{{ $p['initials'] }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ $p['name'] }}</p>
                            <p class="text-xs text-slate-400">{{ $p['policy'] ?: 'Leave' }}@if($p['half']) · {{ $p['half'] }}@endif</p>
                        </div>
                        <span class="ml-auto text-xs text-slate-400 whitespace-nowrap">Back {{ $p['returns'] }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="px-5 py-8 text-center text-sm text-slate-400">Everyone's in today 🎉</p>
        @endif
    </div>
@endif
