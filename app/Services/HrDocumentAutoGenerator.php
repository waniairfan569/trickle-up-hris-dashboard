<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ConductNote;
use App\Models\HrDocument;
use App\Models\HrDocumentTemplate;
use App\Models\TimeOffRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Auto-creates draft HR documents from attendance events:
 *  - one Lateness Review per late day (a "per-day" doc: period_start == period_end),
 *  - a consolidated monthly Lateness Review (admin-triggered from Time tracking),
 *  - a Return to Work Form per completed leave/absence.
 *
 * Every document is created as a DRAFT — an admin reviews/edits/sends it; the
 * employee then signs. Templates are found by their prefill token, and the field
 * values (employee info + late/absence dates) come from HrDocumentPrefillService.
 */
class HrDocumentAutoGenerator
{
    public function __construct(private HrDocumentPrefillService $prefiller) {}

    public function latenessTemplate(): ?HrDocumentTemplate
    {
        return HrDocumentTemplate::where('prefill', 'lateness')->orderByDesc('is_active')->orderBy('id')->first();
    }

    public function absenceTemplate(): ?HrDocumentTemplate
    {
        return HrDocumentTemplate::where('prefill', 'absence')->orderByDesc('is_active')->orderBy('id')->first();
    }

    /** Per-day lateness + per-leave return-to-work drafts for one employee. Returns count created. */
    public function generateForEmployee(User $employee, int $lookbackDays = 45): int
    {
        $today = Carbon::today();

        return $this->perDayLateness($employee, $today, $lookbackDays)
            + $this->returnToWork($employee, $today, $lookbackDays);
    }

    /** One Lateness Review draft per late day (up to yesterday), if not already made. */
    public function perDayLateness(User $employee, Carbon $today, int $lookbackDays = 45): int
    {
        $template = $this->latenessTemplate();
        if (! $template) {
            return 0;
        }

        $lateDays = AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'late')
            ->whereBetween('date', [$today->copy()->subDays($lookbackDays)->toDateString(), $today->copy()->subDay()->toDateString()])
            ->orderBy('date')
            ->pluck('date');

        $created = 0;
        foreach ($lateDays as $date) {
            $day = Carbon::parse($date)->startOfDay();
            if ($this->exists($employee, $template, $day, $day)) {
                continue;
            }
            $this->createDraft($template, $employee, $day, $day, $template->name . ' — ' . $day->format('d M Y'));
            $created++;
        }

        return $created;
    }

    /** A Return to Work Form draft per completed (non-WFH) leave the employee has returned from. */
    public function returnToWork(User $employee, Carbon $today, int $lookbackDays = 45): int
    {
        $template = $this->absenceTemplate();
        if (! $template) {
            return 0;
        }

        $leaves = TimeOffRequest::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->returnToWorkEligible() // unplanned / sick / casual / emergency / WFH — not planned
            ->whereDate('end_date', '<', $today->toDateString())
            ->whereDate('end_date', '>=', $today->copy()->subDays($lookbackDays)->toDateString())
            ->orderBy('start_date')
            ->get();

        $created = 0;
        $seen = [];
        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->start_date)->startOfDay();
            $end = Carbon::parse($leave->end_date)->startOfDay();
            // Don't create two docs for the same period (e.g. two leaves on the same dates).
            $key = $start->toDateString() . '|' . $end->toDateString();
            if (isset($seen[$key]) || $this->exists($employee, $template, $start, $end)) {
                continue;
            }
            $seen[$key] = true;
            $this->createDraft($template, $employee, $start, $end, $template->name . ' — ' . $this->periodLabel($start, $end));
            $created++;
        }

        return $created;
    }

    /** The consolidated monthly Lateness Review (all late days of the month). Admin-triggered; idempotent. */
    public function generateMonthlyLateness(User $employee, Carbon $month, ?int $by = null): ?HrDocument
    {
        $template = $this->latenessTemplate();
        if (! $template) {
            return null;
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        if ($existing = $this->find($employee, $template, $start, $end)) {
            return $existing;
        }

        $lateCount = AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'late')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->count();
        if ($lateCount === 0) {
            return null;
        }

        return $this->createDraft($template, $employee, $start, $end, $template->name . ' — ' . $start->format('M Y'), $by);
    }

    /**
     * Log a conduct note for any document that has been sent for signature but the
     * employee still hasn't signed after $days days. Idempotent (one per document).
     * Returns the number of notes created (tenant-scoped).
     */
    public function logUnsignedDocuments(int $days = 2): int
    {
        $cutoff = Carbon::today()->subDays($days)->endOfDay();

        $docs = HrDocument::where('status', 'sent')
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', $cutoff)
            ->with('signers')
            ->get();

        $created = 0;
        foreach ($docs as $doc) {
            // The employee's own signature is the one we care about.
            $signer = $doc->signers->firstWhere('user_id', $doc->user_id);
            if (! $signer || $signer->signed_at) {
                continue;
            }
            if (ConductNote::where('hr_document_id', $doc->id)->exists()) {
                continue;
            }

            $name = $doc->title ?: $doc->template_name;

            ConductNote::create([
                'user_id'        => $doc->user_id,
                'hr_document_id' => $doc->id,
                'author_id'      => null,
                'occurred_on'    => Carbon::today()->toDateString(),
                'category'       => 'Policy',
                'note'           => "Document not signed after {$days} days: {$name} (sent " . optional($doc->sent_at)->format('d M Y') . ').',
            ]);
            $created++;
        }

        return $created;
    }

    private function periodLabel(Carbon $start, Carbon $end): string
    {
        return $start->isSameDay($end)
            ? $start->format('d M Y')
            : $start->format('d M') . ' – ' . $end->format('d M Y');
    }

    private function exists(User $employee, HrDocumentTemplate $template, Carbon $start, Carbon $end): bool
    {
        return $this->baseQuery($employee, $template, $start, $end)->exists();
    }

    private function find(User $employee, HrDocumentTemplate $template, Carbon $start, Carbon $end): ?HrDocument
    {
        return $this->baseQuery($employee, $template, $start, $end)->first();
    }

    private function baseQuery(User $employee, HrDocumentTemplate $template, Carbon $start, Carbon $end)
    {
        return HrDocument::where('user_id', $employee->id)
            ->where('hr_document_template_id', $template->id)
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString());
    }

    private function createDraft(HrDocumentTemplate $template, User $employee, Carbon $start, Carbon $end, string $title, ?int $by = null): HrDocument
    {
        return HrDocument::create([
            'hr_document_template_id' => $template->id,
            'user_id'       => $employee->id,
            'template_name' => $template->name,
            'title'         => $title,
            'schema'        => $template->schema,
            'data'          => $this->prefiller->prefill($template, $employee, $start, $end),
            'period_start'  => $start->toDateString(),
            'period_end'    => $end->toDateString(),
            'status'        => 'draft',
            'created_by'    => $by,
        ]);
    }
}
