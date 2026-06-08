@extends('layouts.hr-app')

@section('title', 'Performance Review')
@section('breadcrumb', 'Review Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white capitalize">
                {{ $performance->type }} Review: {{ $performance->reviewee->full_name }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">Cycle: {{ $performance->cycle->name }}</p>
        </div>
        
        <div class="flex gap-3">
            @if(auth()->user()->hasRole('super_admin') && $performance->status !== 'draft')
                <form action="{{ route('performance.reopen', $performance) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-rose-100 px-4 py-2 text-sm font-bold text-rose-700 shadow-sm hover:bg-rose-200">
                        Force Reopen
                    </button>
                </form>
            @endif

            @if($performance->type === 'manager' && $performance->status === 'submitted' && $performance->reviewer_id === auth()->id())
                <form action="{{ route('performance.share', $performance) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                        Share with Employee
                    </button>
                </form>
            @endif

            @if($performance->type === 'manager' && $performance->status === 'shared' && $performance->reviewee_id === auth()->id())
                <form action="{{ route('performance.sign', $performance) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">
                        Sign & Acknowledge
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Timeline -->
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-center justify-between">
            @php
                $states = ['draft', 'submitted', 'shared', 'signed'];
                $currentIndex = array_search($performance->status, $states);
            @endphp
            @foreach($states as $index => $state)
                <div class="flex flex-col items-center">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center font-bold text-sm
                        {{ $index <= $currentIndex ? 'bg-brand-600 text-slate-900' : 'bg-slate-100 text-slate-400 dark:bg-slate-700' }}">
                        {{ $index + 1 }}
                    </div>
                    <span class="mt-2 text-xs font-bold uppercase {{ $index <= $currentIndex ? 'text-brand-600' : 'text-slate-400' }}">
                        {{ $state }}
                    </span>
                </div>
                @if(!$loop->last)
                    <div class="flex-1 h-1 mx-4 rounded {{ $index < $currentIndex ? 'bg-brand-600' : 'bg-slate-100 dark:bg-slate-700' }}"></div>
                @endif
            @endforeach
        </div>
    </div>

    <!-- Form / Content -->
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm dark:bg-slate-800 dark:border-slate-700 p-8">
        
        @if($errors->any())
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 mb-6">
                <ul class="text-sm font-medium text-rose-800">
                    @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif
        @if(session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 mb-6">
                <span class="text-sm font-medium text-emerald-800">{{ session('success') }}</span>
            </div>
        @endif

        @php
            $isEditable = $performance->canBeEditedBy(auth()->user());
            $formAction = $performance->type === 'self' 
                ? route('performance.storeSelfReview') 
                : route('performance.storeManagerReview', $performance->reviewee_id);
            $content = $performance->content ?? [];
        @endphp

        <form action="{{ $formAction }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="cycle_id" value="{{ $performance->cycle_id }}">

            @if($performance->type === 'self')
                <!-- Self Review Fields -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Key Achievements</label>
                    @if($isEditable)
                        <textarea name="achievements" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">{{ old('achievements', $content['achievements'] ?? '') }}</textarea>
                    @else
                        <div class="mt-2 p-4 bg-slate-50 rounded-xl text-sm text-slate-700 dark:bg-slate-900/50 dark:text-slate-300 whitespace-pre-wrap">{{ $content['achievements'] ?? 'N/A' }}</div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Goals for Next Cycle</label>
                    @if($isEditable)
                        <textarea name="goals" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">{{ old('goals', $content['goals'] ?? '') }}</textarea>
                    @else
                        <div class="mt-2 p-4 bg-slate-50 rounded-xl text-sm text-slate-700 dark:bg-slate-900/50 dark:text-slate-300 whitespace-pre-wrap">{{ $content['goals'] ?? 'N/A' }}</div>
                    @endif
                </div>
            @else
                <!-- Manager Review Fields -->
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Manager Feedback</label>
                    @if($isEditable)
                        <textarea name="feedback" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">{{ old('feedback', $content['feedback'] ?? '') }}</textarea>
                    @else
                        <div class="mt-2 p-4 bg-slate-50 rounded-xl text-sm text-slate-700 dark:bg-slate-900/50 dark:text-slate-300 whitespace-pre-wrap">{{ $content['feedback'] ?? 'N/A' }}</div>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Strengths</label>
                        @if($isEditable)
                            <textarea name="strengths" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">{{ old('strengths', $content['strengths'] ?? '') }}</textarea>
                        @else
                            <div class="mt-2 p-4 bg-slate-50 rounded-xl text-sm text-slate-700 dark:bg-slate-900/50 dark:text-slate-300 whitespace-pre-wrap">{{ $content['strengths'] ?? 'N/A' }}</div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Areas for Improvement</label>
                        @if($isEditable)
                            <textarea name="areas_for_improvement" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">{{ old('areas_for_improvement', $content['areas_for_improvement'] ?? '') }}</textarea>
                        @else
                            <div class="mt-2 p-4 bg-slate-50 rounded-xl text-sm text-slate-700 dark:bg-slate-900/50 dark:text-slate-300 whitespace-pre-wrap">{{ $content['areas_for_improvement'] ?? 'N/A' }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Overall Rating -->
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Overall Rating (1-5)</label>
                @if($isEditable)
                    <input type="number" name="rating" min="1" max="5" value="{{ old('rating', $content['rating'] ?? '') }}" class="mt-1 block w-32 rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">
                @else
                    <div class="mt-2 p-3 bg-slate-50 rounded-xl text-sm font-bold text-brand-600 inline-block">{{ $content['rating'] ?? 'N/A' }} / 5</div>
                @endif
            </div>

            @if($isEditable)
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <button type="submit" name="action" value="save" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition">
                        Save Draft
                    </button>
                    <button type="submit" name="action" value="submit" class="rounded-xl bg-brand-600 px-6 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 transition" onclick="return confirm('Are you sure? You cannot edit after submitting.')">
                        Submit Final Review
                    </button>
                </div>
            @endif
        </form>
    </div>

</div>
@endsection
