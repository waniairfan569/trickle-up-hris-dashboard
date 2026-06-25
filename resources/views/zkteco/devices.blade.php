@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Manage ZKTeco Devices</h1>
        <a href="{{ route('zkteco.dashboard') }}" class="text-blue-600 hover:text-blue-800">Back to Dashboard</a>
    </div>

    @if (session('success'))
        <div class="mb-4 bg-green-50 p-4 rounded-md">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Add New Device</h3>
        </div>
        <div class="p-6">
            @if ($errors->any())
                <div class="mb-4 bg-red-50 p-3 rounded-md text-sm text-red-700">{{ $errors->first() }}</div>
            @endif
            <form action="{{ route('zkteco.devices.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Device Name</label>
                        <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Connection Mode</label>
                        <select name="connection_mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="pull">Pull (server dials device — needs same LAN)</option>
                            <option value="push">Push / ADMS (device reports to this server)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Serial Number <span class="text-gray-400">(for push)</span></label>
                        <input type="text" name="serial_number" placeholder="From device → Info" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">IP Address</label>
                        <input type="text" name="ip_address" value="0.0.0.0" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Port</label>
                        <input type="number" name="port" value="4370" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md shadow-sm text-sm font-medium hover:bg-blue-700">Add Device</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200"><h3 class="text-lg font-medium text-gray-900">Devices</h3></div>
        <div class="divide-y divide-gray-200">
            @forelse($devices as $device)
                <form action="{{ route('zkteco.devices.update', $device) }}" method="POST" class="p-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    @csrf @method('PUT')
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-medium text-gray-500">Name</label>
                        <input type="text" name="name" value="{{ $device->name }}" required class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500">Mode</label>
                        <select name="connection_mode" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                            <option value="pull" {{ ($device->connection_mode ?? 'pull') === 'pull' ? 'selected' : '' }}>Pull</option>
                            <option value="push" {{ ($device->connection_mode ?? 'pull') === 'push' ? 'selected' : '' }}>Push (ADMS)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500">Serial #</label>
                        <input type="text" name="serial_number" value="{{ $device->serial_number }}" placeholder="—" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500">IP</label>
                        <input type="text" name="ip_address" value="{{ $device->ip_address }}" required class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-[11px] font-medium text-gray-500">Port</label>
                            <input type="number" name="port" value="{{ $device->port }}" required class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        </div>
                    </div>
                    <div class="md:col-span-6 flex items-center justify-end gap-3 pt-1">
                        <button type="button" onclick="testConnection({{ $device->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Test Connection</button>
                        <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">Save</button>
                        <button type="button"
                                onclick="if(confirm('Delete this device?')){ const f=this.form; f.querySelector('input[name=_method]').value='DELETE'; f.action='{{ route('zkteco.devices.destroy', $device) }}'; f.submit(); }"
                                class="px-3 py-1.5 bg-red-50 text-red-700 rounded-md text-sm font-medium hover:bg-red-100">Delete</button>
                    </div>
                </form>
            @empty
                <p class="p-6 text-sm text-gray-500 text-center">No devices added yet.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
function testConnection(deviceId) {
    fetch(`/zkteco/devices/${deviceId}/test`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
    })
    .catch(err => alert('Error testing connection'));
}
</script>
@endsection
