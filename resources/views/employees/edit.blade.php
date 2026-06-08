<div>
    <h1>Edit Employee Profile: {{ $employee->first_name }} {{ $employee->last_name }}</h1>
    <form action="{{ route('employees.update', $employee->id) }}" method="POST">
        @csrf
        @method('PUT')
        @foreach ($fields as $key => $value)
            <div>
                <label>{{ $key }}</label>
                <input type="text" name="{{ $key }}" value="{{ is_array($value) ? json_encode($value) : $value }}">
            </div>
        @endforeach
        <button type="submit">Update Employee</button>
    </form>
</div>
