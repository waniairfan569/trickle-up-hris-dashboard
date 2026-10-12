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

        $lateReasons = \App\Models\AttendanceExplanation::reasonsFor($employee, $start, $end);

        // Leaves this template is about — a work-from-home form lists WFH days,
        // a return-to-work form lists absences. The reason is the one the
        // employee gave when they applied, so it is never asked for twice.
        $leaves = $this->leavesForTemplate($template, $employee, $start, $end);

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
                    // A month's review has no single date of lateness — list them.
                    'lateness_dates' => $late->isEmpty() ? '' : $late
                        ->map(fn ($r) => $r->date->format($late->count() > 1 ? 'd M' : 'd M Y'))
                        ->join(', ') . ($late->count() > 1 ? ' ' . $end->format('Y') : ''),
                    'absent_count'  => (string) $absent->count(),
                    // Total minutes lost, for a "Total time of lateness" box.
                    'late_minutes_total' => $this->minutesLabel((int) $late->sum('late_minutes')),
                    // The same date/reason pairs as lateness_table, but as text —
                    // for templates whose "Reason for lateness" is a plain field
                    // rather than a table.
                    'lateness_summary' => $late->map(fn ($r) =>
                        $r->date->format('d M Y') . ' — '
                        . ($lateReasons[$r->date->toDateString()] ?? 'No reason provided')
                    )->join("\n"),
                    // Reasons the employee already gave on the day — never ask twice.
                    'lateness_table' => $late->map(fn ($r) => [
                        'Date'   => $r->date->format('d M Y'),
                        'Reason' => $lateReasons[$r->date->toDateString()] ?? 'No reason provided',
                    ])->values()->all(),
                    'absence_table' => $absent->map(fn ($r) => [
                        'Date'   => $r->date->format('d M Y'),
                        'Reason' => '',
                    ])->values()->all(),
                    'leave_count' => (string) $leaves->count(),
                    'leave_days'  => (string) rtrim(rtrim(number_format((float) $leaves->sum('days_requested'), 1, '.', ''), '0'), '.'),
                    'leave_table' => $leaves->map(fn ($lv) => [
                        'Date'   => $this->leaveDateLabel($lv),
                        'Days'   => rtrim(rtrim(number_format((float) $lv->days_requested, 1, '.', ''), '0'), '.'),
                        'Reason' => trim((string) $lv->reason) !== '' ? $lv->reason : 'No reason given',
                    ])->values()->all(),
                    // The same leaves, plus what the employee wrote from their
                    // portal on coming back — the return-to-work conversation,
                    // in their own words, without anyone sitting down.
                    'leave_return_table' => $leaves->map(fn ($lv) => [
                        'Date'          => $this->leaveDateLabel($lv),
                        'Days'          => rtrim(rtrim(number_format((float) $lv->days_requested, 1, '.', ''), '0'), '.'),
                        'Reason'        => trim((string) $lv->reason) !== '' ? $lv->reason : 'No reason given',
                        'On returning'  => trim((string) $lv->return_note) !== '' ? $lv->return_note : 'No note given',
                    ])->values()->all(),
                    // The same pairs as text, for a template whose return field
                    // is a textarea rather than a table.
                    'leave_return_summary' => $leaves->map(fn ($lv) =>
                        $this->leaveDateLabel($lv) . ' — '
                        . (trim((string) $lv->return_note) !== '' ? $lv->return_note : 'No note given')
                    )->join("\n"),
                    // Singular forms, for a form drafted per leave rather than
                    // once a month — there the whole document is about one
                    // absence, so each fact belongs in its own box.
                    'leave_start_date' => $leaves->isEmpty() ? ''
                        : Carbon::parse($leaves->first()->start_date)->toDateString(),
                    'leave_return_date' => $leaves->isEmpty() ? ''
                        : Carbon::parse($leaves->first()->end_date)->addDay()->toDateString(),
                    'leave_reason' => $leaves->isEmpty() ? ''
                        : (trim((string) $leaves->first()->reason) !== '' ? $leaves->first()->reason : 'No reason given'),
                    'leave_return_note' => $leaves->isEmpty() ? ''
                        : (trim((string) $leaves->first()->return_note) !== '' ? $leaves->first()->return_note : 'No note given'),
                    default => null,
                };

                if ($value !== null) {
                    $data[$field['id']] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * The approved leaves in the period that this template is about, decided by
     * the attendance event it is assigned to on the Documents page.
     */
    private function leavesForTemplate($template, User $employee, Carbon $start, Carbon $end)
    {
        $trigger = $template->prefill ?? null;
        if (! in_array($trigger, ['absence', 'hourly', 'wfh'], true)) {
            return collect();
        }

        $leaves = \App\Models\TimeOffRequest::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                  ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->with('policy')->orderBy('start_date')->get();

        return $leaves->filter(function ($lv) use ($trigger) {
            $isWfh = optional($lv->policy)->isWorkFromHome() ?? false;

            return match ($trigger) {
                'wfh'     => $isWfh,
                'hourly'  => ! $isWfh && $lv->duration_type === 'hourly',
                'absence' => ! $isWfh && $lv->duration_type !== 'hourly',
                default   => false,
            };
        })->values();
    }

    /** 67 -> "1h 7m", 7 -> "7 min", 0 -> "". */
    private function minutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h && $m) {
            return $h . 'h ' . $m . 'm';
        }

        return $h ? $h . ' hour' . ($h === 1 ? '' : 's') : $m . ' min';
    }

    /** "12 Oct 2026", or "12 – 15 Oct 2026", with the hours when it was hourly. */
    private function leaveDateLabel($leave): string
    {
        $from = Carbon::parse($leave->start_date);
        $to = Carbon::parse($leave->end_date);

        $label = $from->isSameDay($to)
            ? $from->format('d M Y')
            : $from->format('d M') . ' – ' . $to->format('d M Y');

        if ($leave->duration_type === 'hourly' && $leave->time_range) {
            return $label . ' (' . $leave->time_range . ')';
        }
        if ($leave->is_half_day || $leave->duration_type === 'half_day') {
            return $label . ' (half day)';
        }

        return $label;
    }
}
