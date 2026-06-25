@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">ZKTeco Dashboard</h1>
        <div class="flex space-x-4">
            <a href="{{ route('zkteco.devices') }}" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Manage Devices</a>
            <a href="{{ route('zkteco.import') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md shadow-sm text-sm font-medium hover:bg-blue-700">Manual Excel Import</a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 bg-green-50 p-4 rounded-md">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 bg-red-50 p-4 rounded-md">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 truncate">Total Devices</h3>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $totalDevices }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 truncate">Last Sync</h3>
            <p class="mt-2 text-xl font-semibold text-gray-900">
                {{ $lastSync ? \Carbon\Carbon::parse($lastSync)->diffForHumans() : 'Never' }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 truncate">Imported Today</h3>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $todayImported }}</p>
        </div>
        <a href="{{ route('zkteco.unmapped') }}" class="bg-white rounded-lg shadow p-6 hover:bg-gray-50 cursor-pointer border-2 {{ $unmappedCount > 0 ? 'border-red-500' : 'border-transparent' }}">
            <h3 class="text-sm font-medium {{ $unmappedCount > 0 ? 'text-red-500' : 'text-gray-500' }} truncate">Unmapped Employees</h3>
            <p class="mt-2 text-3xl font-semibold {{ $unmappedCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $unmappedCount }}</p>
            @if($unmappedCount > 0)
                <p class="text-xs text-red-500 mt-1">Action required!</p>
            @endif
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Devices Overview</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sync</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Synced</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($devices as $device)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @php $isPush = ($device->connection_mode ?? 'pull') === 'push'; @endphp
                        <div class="text-sm font-medium text-gray-900 flex items-center gap-2">
                            {{ $device->name }}
                            <span class="px-2 py-0.5 inline-flex text-[10px] font-bold rounded-full {{ $isPush ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $isPush ? 'PUSH' : 'PULL' }}</span>
                        </div>
                        <div class="text-sm text-gray-500">{{ $isPush ? ('SN: ' . ($device->serial_number ?: '—')) : ($device->ip_address . ':' . $device->port) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $device->status_badge }}">
                            {{ ucfirst($device->last_sync_status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $device->last_synced_at ? $device->last_synced_at->format('M d, Y H:i:s') : 'Never' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $device->total_records_synced }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        @if($isPush)
                            <span class="text-xs text-gray-400 italic">Reports automatically</span>
                        @else
                            <form action="{{ route('zkteco.devices.sync', $device) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:text-blue-900 mr-3">Sync Now</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No devices found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
