@extends('layouts.hr-app')

@section('title', 'Performance Reviews')
@section('breadcrumb', 'Performance')

@section('content')
<div x-data="{ activeTab: 'my-reviews', showCycleModal: false }" class="space-y-6">

    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Performance Reviews</h1>
        @if(auth()->user()->isAdmin())
            <!-- Admin could create cycles, keeping UI simple for now -->
            <button @click="showCycleModal = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 transition">
                <i data-lucide="plus" class="h-4 w-4"></i>
                New Cycle
            </button>
        @endif
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-slate-200 dark:border-slate-700 space-x-8">
        <button @click="activeTab = 'my-reviews'" :class="activeTab === 'my-reviews' ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium focus:outline-none">
            My Reviews
        </button>
        @if(auth()->user()->isManager())
        <button @click="activeTab = 'team-reviews'" :class="activeTab === 'team-reviews' ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium focus:outline-none">
            My Team
        </button>
        @endif
        @if(auth()->user()->isAdmin())
        <button @click="activeTab = 'all-reviews'" :class="activeTab === 'all-reviews' ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium focus:outline-none">
            All Reviews (Admin)
        </button>
        @endif
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
            </div>
        </div>
    @endif

    <!-- TAB: My Reviews -->
    <div x-show="activeTab === 'my-reviews'" class="space-y-8">
        
        <!-- Active Cycles Needing Action -->
        @if($activeCycles->isNotEmpty())
        <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Active Review Cycles</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($activeCycles as $cycle)
                <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white">{{ $cycle->name }}</h4>
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-800">Active</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">Ends on {{ $cycle->end_date->format('M d, Y') }}</p>
                    
                    @php
                        $selfReview = $mySelfReviews->where('cycle_id', $cycle->id)->first();
                    @endphp
                    
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center">
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Self Review Status:</span>
                            <span class="text-sm font-bold capitalize ml-1 {{ $selfReview && $selfReview->status === 'submitted' ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $selfReview ? $selfReview->status : 'Not Started' }}
                            </span>
                        </div>
                        
                        @if($selfReview)
                            <a href="{{ route('performance.show', $selfReview) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">View / Edit &rarr;</a>
                        @else
                            <!-- To create a blank self review, just show a form post creating the shell -->
                            <form action="{{ route('performance.storeSelfReview') }}" method="POST">
                                @csrf
                                <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                                <input type="hidden" name="action" value="save">
                                <button type="submit" class="text-sm font-bold text-brand-600 hover:text-brand-800">Start Review &rarr;</button>
                            </form>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- My Manager Reviews (Shared) -->
        <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Feedback from Manager</h3>
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Cycle</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Reviewer</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($myManagerReviews as $req)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">{{ $req->cycle->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $req->reviewer->full_name ?? 'Manager' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold capitalize 
                                    {{ $req->status === 'signed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('performance.show', $req) }}" class="text-brand-600 hover:text-brand-900 font-bold">View</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-4 text-center text-sm text-slate-500">No shared feedback yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: Team Reviews -->
    @if(auth()->user()->isManager())
    <div x-show="activeTab === 'team-reviews'" x-cloak>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Team Performance</h3>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Cycle</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($teamReviews as $req)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">{{ $req->reviewee->full_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $req->cycle->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400 capitalize">{{ $req->type }} Review</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold capitalize">{{ $req->status }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('performance.show', $req) }}" class="text-brand-600 hover:text-brand-900 font-bold">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-slate-500">No team reviews found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activeCycles->isNotEmpty())
        <div class="mt-8">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Start Manager Review</h3>
            <div class="flex gap-4">
                <form action="" method="POST" class="flex gap-4 items-end" id="startManagerReviewForm">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Select Cycle</label>
                        <select name="cycle_id" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach($activeCycles as $cycle)
                                <option value="{{ $cycle->id }}">{{ $cycle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Select Employee</label>
                        <select name="employee_id" id="managerReviewEmployeeId" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach(auth()->user()->directReports as $report)
                                <option value="{{ $report->id }}">{{ $report->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="action" value="save">
                    <button type="button" onclick="startManagerReview()" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-800">Start</button>
                </form>
            </div>
        </div>
        <script>
            function startManagerReview() {
                const form = document.getElementById('startManagerReviewForm');
                const empId = document.getElementById('managerReviewEmployeeId').value;
                form.action = `/performance/manager-review/${empId}`;
                form.submit();
            }
        </script>
        @endif
    </div>
    @endif

    <!-- TAB: All Reviews (Admin) -->
    @if(auth()->user()->isAdmin())
    <div x-show="activeTab === 'all-reviews'" x-cloak>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">All Performance Reviews</h3>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Reviewer</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Cycle</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($allReviews as $req)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">{{ $req->reviewee->full_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $req->reviewer->full_name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $req->cycle->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400 capitalize">{{ $req->type }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold capitalize">{{ $req->status }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('performance.show', $req) }}" class="text-brand-600 hover:text-brand-900 font-bold">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-4 text-center text-sm text-slate-500">No reviews found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Create Cycle Modal -->
    <div x-show="showCycleModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showCycleModal" class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showCycleModal = false">
                <div class="absolute inset-0 bg-slate-900 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showCycleModal" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full dark:bg-slate-800">
                <form action="{{ route('performance.storeCycle') }}" method="POST">
                    @csrf
                    <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-bold text-slate-900 dark:text-white mb-4">Create New Review Cycle</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Cycle Name</label>
                                <input type="text" name="name" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="e.g., Q1 2026 Review">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Start Date</label>
                                    <input type="date" name="start_date" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">End Date</label>
                                    <input type="date" name="end_date" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-brand-600 text-base font-bold text-white hover:bg-brand-700 sm:ml-3 sm:w-auto sm:text-sm">
                            Create Cycle
                        </button>
                        <button type="button" @click="showCycleModal = false" class="mt-3 w-full inline-flex justify-center rounded-xl border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-slate-700 hover:bg-slate-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
