<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\HrDocumentTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Turns a template + employee + period into a set of prefilled field values,
 * keyed by field id. Fields opt in via their `prefill` token in the schema.
 */
class HrDocumentPrefillService
{
    /**
     * @return array<string, mixed> field-id => value
     */
    public function prefill(HrDocumentTemplate $template, User $employee, Carbon $start, Carbon $end): array
    {
        // Attendance facts for the window (computed once, reused across fields).
        $late = AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'late')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')->get();

        $absent = AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'absent')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')->get();

        $data = [];

        foreach ($template->schema as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $token = $field['prefill'] ?? null;
                if (! $token) {
                    continue;
                }

                $value = match ($token) {
                    'employee_name' => $employee->full_name,
                    'job_title'     => $employee->job_title ?: '',
                    'department'    => optional($employee->department)->name ?? '',
                    'line_manager'  => optional($employee->manager)->full_name ?? '',
                    'late_count'    => (string) $late->count(),
                    'absent_count'  => (string) $absent->count(),
                    'lateness_table' => $late->map(fn ($r) => [
                        'Date'   => $r->date->format('d M Y'),
                        'Reason' => '',
                    ])->values()->all(),
                    'absence_table' => $absent->map(fn ($r) => [
                        'Date'   => $r->date->format('d M Y'),
                        'Reason' => '',
                    ])->values()->all(),
                    default => null,
                };

                if ($value !== null) {
                    $data[$field['id']] = $value;
                }
            }
        }

        return $data;
    }
}
