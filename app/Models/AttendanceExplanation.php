<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

use App\Models\AttendanceReportSettings;
use App\Models\HrDocument;

/**
 * What the employee said about one attendance exception, given on the day it
 * happened. The month-end Lateness Review fills its date/reason table from
 * these rows rather than asking again weeks later.
 */
class AttendanceExplanation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'attendance_record_id', 'date', 'type',
        'late_minutes', 'reason', 'acknowledged_at', 'acknowledged_ip', 'hr_document_id',
    ];

    protected $casts = [
        'date' => 'date',
        'late_minutes' => 'integer',
        'acknowledged_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function document()
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    /**
     * Late days in the window that the employee hasn't explained yet, newest
     * first — what the prompt asks about.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AttendanceRecord>
     */
    public static function outstandingFor(User $employee, int $withinDays = 45)
    {
        // "Ask for reasons from" is the admin's deliberate starting point, so it
        // decides the window outright — set it back a few months to collect
        // reasons for past lateness, or forward to stop asking about history.
        // With nothing set, fall back to the recent past.
        $since = AttendanceReportSettings::getSettings()->lateness_prompt_from ?? null;
        $from = $since
            ? Carbon::parse($since)->startOfDay()
            : Carbon::today()->subDays($withinDays);

        $days = AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'late')
            ->where('late_minutes', '>', 0)
            ->whereBetween('date', [$from->toDateString(), Carbon::today()->toDateString()])
            ->whereNotIn('id', self::where('user_id', $employee->id)->select('attendance_record_id'))
            ->orderByDesc('date')
            ->get();

        if ($days->isEmpty()) {
            return $days;
        }

        // A day already written up in a lateness document that has gone out has
        // been dealt with — asking for a reason now would be asking twice.
        // A draft has not gone anywhere and is rebuilt when it is regenerated,
        // so it must not stop the employee answering for those days.
        $documented = HrDocument::where('user_id', $employee->id)
            ->where('status', '!=', 'draft')
            ->whereHas('template', fn ($q) => $q->where('prefill', 'lateness'))
            ->get(['period_start', 'period_end']);

        if ($documented->isEmpty()) {
            return $days;
        }

        return $days->reject(fn ($day) => $documented->contains(
            fn ($doc) => $doc->period_start && $doc->period_end
                && $day->date->betweenIncluded($doc->period_start, $doc->period_end)
        ))->values();
    }

    /** date (Y-m-d) => reason, for the days covered by a month-end document. */
    public static function reasonsFor(User $employee, Carbon $from, Carbon $to): array
    {
        return self::where('user_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn ($e) => [$e->date->toDateString() => $e->reason])
            ->all();
    }
}
