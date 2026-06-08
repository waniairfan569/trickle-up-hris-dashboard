@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('office-locations.index') }}" class="text-brand-600 hover:text-brand-800 font-medium text-sm flex items-center">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Locations
        </a>
    </div>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Assign Office Locations</h1>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800">Bulk Assign</h3>
                <p class="text-sm text-slate-500">Select multiple employees to assign them to a specific location.</p>
            </div>
            
            <form action="{{ route('office-locations.assign') }}" method="POST" class="flex flex-col sm:flex-row gap-3 items-end sm:items-center w-full md:w-auto" id="bulk-assign-form">
                @csrf
                <div class="w-full sm:w-64">
                    <label for="office_location_id" class="sr-only">Office Location</label>
                    <select name="office_location_id" id="office_location_id" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">-- Select Location --</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button type="button" onclick="submitBulkAssign('assign')" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition whitespace-nowrap flex-1 sm:flex-auto">
                        Assign
                    </button>
                    <button type="button" onclick="submitBulkAssign('unassign')" class="bg-white border border-slate-300 text-red-600 hover:bg-red-50 hover:border-red-300 font-bold py-2 px-4 rounded-lg shadow-sm transition whitespace-nowrap flex-1 sm:flex-auto" title="Remove selected employees from the chosen location">
                        Unassign
                    </button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 text-sm uppercase tracking-wider">
                        <th class="px-6 py-4 font-medium border-b border-slate-200 w-12">
                            <input type="checkbox" id="select-all" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        </th>
                        <th class="px-6 py-4 font-medium border-b border-slate-200">Employee</th>
                        <th class="px-6 py-4 font-medium border-b border-slate-200">Email</th>
                        <th class="px-6 py-4 font-medium border-b border-slate-200">Current Assigned Location</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                    @foreach($employees as $employee)
                        <tr class="border-b border-slate-200 hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <input type="checkbox" name="selected_users[]" value="{{ $employee->id }}" class="employee-checkbox rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            </td>
                            <td class="px-6 py-4 font-medium flex items-center">
                                <div class="h-8 w-8 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center font-bold mr-3">
                                    {{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}
                                </div>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $employee->email }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $primaryLocation = $employee->officeLocations->first();
                                @endphp
                                @if($primaryLocation)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ $primaryLocation->name }}
                                        @if($primaryLocation->allow_remote)
                                            (Remote)
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Unassigned
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if($employees->isEmpty())
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                No employees found.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });

    function submitBulkAssign(actionType) {
        const selected = document.querySelectorAll('.employee-checkbox:checked');
        if (selected.length === 0) {
            alert('Please select at least one employee.');
            return;
        }

        const locationId = document.getElementById('office_location_id').value;
        if (!locationId) {
            alert('Please select an office location.');
            return;
        }

        const form = document.getElementById('bulk-assign-form');
        
        if (actionType === 'assign') {
            form.action = "{{ route('office-locations.assign') }}";
        } else if (actionType === 'unassign') {
            form.action = "{{ route('office-locations.unassign') }}";
            if(!confirm('Are you sure you want to unassign the selected employees from this location?')) {
                return;
            }
        }
        
        // Remove old hidden inputs if they exist
        form.querySelectorAll('input[name="user_ids[]"]').forEach(el => el.remove());

        // Add selected user IDs to form
        selected.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });

        form.submit();
    }
</script>
@endsection
