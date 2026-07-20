<div>
    <h2>Time Off Request Approved</h2>
    <p>Hi {{ $timeOffRequest->employee->first_name }},</p>
    <p>Your time off request has been approved.</p>
    <p><strong>Policy:</strong> {{ $timeOffRequest->policy->name }}</p>
    <p><strong>Dates:</strong> {{ $timeOffRequest->start_date->format('M d, Y') }} to {{ $timeOffRequest->end_date->format('M d, Y') }}</p>
    <p><strong>Days:</strong> {{ (float) $timeOffRequest->days_requested }}</p>
    <p><a href="{{ url('/time-off') }}">View Details in Trickle Hub</a></p>
</div>
