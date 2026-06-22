@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Manual Excel Import</h1>
        <a href="{{ route('zkteco.dashboard') }}" class="text-blue-600 hover:text-blue-800">Back to Dashboard</a>
    </div>

    @if (session('error'))
        <div class="mb-4 bg-red-50 p-4 rounded-md border-l-4 border-red-500">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    @if (session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="mb-8 bg-green-50 p-6 rounded-lg border border-green-200">
            <h3 class="text-lg font-medium text-green-900 mb-2">Import Successful</h3>
            <div class="grid grid-cols-4 gap-4 mt-4 text-center">
                <div class="bg-white p-3 rounded shadow-sm">
                    <p class="text-xs text-gray-500 uppercase">Total Rows</p>
                    <p class="text-xl font-bold text-gray-900">{{ $result['total'] }}</p>
                </div>
                <div class="bg-white p-3 rounded shadow-sm border-b-2 border-green-500">
                    <p class="text-xs text-gray-500 uppercase">Imported</p>
                    <p class="text-xl font-bold text-green-600">{{ $result['imported'] }}</p>
                </div>
                <div class="bg-white p-3 rounded shadow-sm border-b-2 border-yellow-500">
                    <p class="text-xs text-gray-500 uppercase">Skipped (Dupes)</p>
                    <p class="text-xl font-bold text-yellow-600">{{ $result['skipped'] }}</p>
                </div>
                <div class="bg-white p-3 rounded shadow-sm border-b-2 border-red-500">
                    <p class="text-xs text-gray-500 uppercase">Unmapped</p>
                    <p class="text-xl font-bold text-red-600">{{ $result['unmapped'] }}</p>
                </div>
            </div>
            @if($result['unmapped'] > 0)
                <div class="mt-4 text-sm text-red-700 bg-red-50 p-3 rounded">
                    You have {{ $result['unmapped'] }} new unmapped punches. <a href="{{ route('zkteco.unmapped') }}" class="underline font-bold">Resolve them here</a>.
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-medium text-gray-900">Upload Attendance Log (.xlsx, .xls, .csv)</h3>
        </div>
        <div class="p-6">
            <form action="{{ route('zkteco.import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="flex items-center space-x-6">
                    <div class="flex-grow">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Excel File</label>
                        <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-md p-2">
                    </div>
                    <div class="pt-7">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md shadow-sm text-sm font-medium hover:bg-blue-700">Import Records</button>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    <p><strong>Required columns:</strong></p>
                    <ul class="list-disc list-inside mt-1">
                        <li><code>no</code> or <code>id</code> or <code>emp_id</code> (The ZKTeco ID on the device)</li>
                        <li><code>time</code> or <code>date_time</code> or <code>check_time</code> (Format: YYYY-MM-DD HH:MM:SS)</li>
                        <li><code>state</code> or <code>punch_state</code> or <code>status</code> (Optional, 0 = In, 1 = Out)</li>
                    </ul>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Recent Imports</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stats</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($logs as $log)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $log->created_at->format('M d, Y H:i:s') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $log->filename }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $log->importer->first_name ?? 'System' }} {{ $log->importer->last_name ?? '' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ ucfirst($log->source) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="text-green-600 font-bold" title="Imported">{{ $log->imported }}</span> /
                        <span class="text-yellow-600" title="Skipped">{{ $log->skipped }}</span> /
                        <span class="text-red-600" title="Unmapped">{{ $log->unmapped }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No imports found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
