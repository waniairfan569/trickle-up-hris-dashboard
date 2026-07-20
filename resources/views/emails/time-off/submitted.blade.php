<div>
    <h2>New Time Off Request</h2>
    <p><strong>{{ $timeOffRequest->employee->first_name }} {{ $timeOffRequest->employee->last_name }}</strong> has requested time off.</p>
    <p><strong>Policy:</strong> {{ $timeOffRequest->policy->name }}</p>
    <p><strong>Dates:</strong> {{ $timeOffRequest->start_date->format('M d, Y') }} to {{ $timeOffRequest->end_date->format('M d, Y') }}</p>
    <p><strong>Days Requested:</strong> {{ (float) $timeOffRequest->days_requested }}</p>
    @if($timeOffRequest->reason)
        <p><strong>Reason:</strong> {{ $timeOffRequest->reason }}</p>
    @endif
    <p><a href="{{ url('/time-off') }}">View Request in Trickle Hub</a></p>
</div>
