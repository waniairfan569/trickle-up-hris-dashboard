@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Office Locations & Geofences</h1>
        <div class="flex space-x-3">
            <a href="{{ route('office-locations.assignView') }}" class="bg-white border-2 border-slate-200 hover:bg-slate-50 text-slate-700 font-bold py-2 px-4 rounded-lg transition">
                Assign Employees
            </a>
            <a href="{{ route('office-locations.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition">
                + Add Location
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($locations as $location)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <div class="p-6 flex-grow">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-bold text-slate-800">{{ $location->name }}</h3>
                        @if($location->allow_remote)
                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">Remote OK</span>
                        @endif
                    </div>
                    
                    <div class="text-slate-600 text-sm space-y-2 mb-4">
                        <p class="flex items-start">
                            <i data-lucide="map-pin" class="w-4 h-4 mr-2 mt-0.5 text-slate-400"></i>
                            <span>{{ $location->address ?? 'No address provided' }}</span>
                        </p>
                        <p class="flex items-center">
                            <i data-lucide="navigation" class="w-4 h-4 mr-2 text-slate-400"></i>
                            <a href="https://maps.google.com/?q={{ $location->latitude }},{{ $location->longitude }}" target="_blank" class="text-brand-600 hover:underline">
                                {{ $location->latitude }}, {{ $location->longitude }}
                            </a>
                        </p>
                        <p class="flex items-center">
                            <i data-lucide="target" class="w-4 h-4 mr-2 text-slate-400"></i>
                            <span>{{ $location->radius_meters }}m radius</span>
                        </p>
                        <p class="flex items-center">
                            <i data-lucide="users" class="w-4 h-4 mr-2 text-slate-400"></i>
                            <span>{{ $location->employees_count }} employees assigned</span>
                        </p>
                    </div>
                </div>
                <div class="bg-slate-50 border-t border-slate-200 p-4 flex justify-end items-center space-x-4">
                    @if($location->employees_count > 0)
                        <span class="text-xs text-slate-500 italic">Unassign {{ $location->employees_count }} employees to delete</span>
                    @endif
                    <div class="flex space-x-2">
                        <a href="{{ route('office-locations.edit', $location) }}" class="text-slate-600 hover:text-brand-600 px-3 py-1 text-sm font-medium">Edit</a>
                        <form action="{{ route('office-locations.destroy', $location) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this location?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="{{ $location->employees_count > 0 ? 'text-slate-400 cursor-not-allowed' : 'text-red-600 hover:text-red-800' }} px-3 py-1 text-sm font-medium" {{ $location->employees_count > 0 ? 'disabled' : '' }}>Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        @if($locations->isEmpty())
            <div class="col-span-full bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                <i data-lucide="map" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-slate-900 mb-1">No office locations</h3>
                <p class="text-slate-500 mb-4">Add your first office location to enable geofenced time tracking.</p>
                <a href="{{ route('office-locations.create') }}" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center transition">
                    + Add Location
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
