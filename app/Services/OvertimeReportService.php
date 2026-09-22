<?php

namespace App\Services;

use App\Models\CompanyForm;
use App\Models\FormSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the finance "approved overtime" report from the designated Overtime
 * Approval Form (CompanyForm with system_key='overtime'). Because the form's
 * fields are admin-defined, the overtime date and hours are detected by
 * key/label/type, with sensible fallbacks (submitted date, no hours).
 */
class OvertimeReportService
{
    public function form(): ?CompanyForm
    {
        return CompanyForm::overtimeForm();
    }

    /**
     * @return array{form:?CompanyForm, rows:Collection, byEmployee:Collection,
     *               entryCount:int, employeeCount:int, totalHours:float, hasHours:bool, hasDate:bool}
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $form = $this->form();

        $empty = [
            'form' => $form, 'rows' => collect(), 'byEmployee' => collect(),
            'entryCount' => 0, 'employeeCount' => 0, 'totalHours' => 0.0,
            'hasHours' => false, 'hasDate' => false,
        ];

        if (! $form) {
            return $empty;
        }

        $fields = $form->fields->filter(fn ($f) => $f->isInputField())->values();
        $dateKey  = $this->detectField($fields, ['date'], ['date']);
        $hoursKey = $this->detectField($fields, ['hour'], ['number']);
        $detailKeys = $fields->pluck('field_key')->reject(fn ($k) => in_array($k, [$dateKey, $hoursKey], true))->all();

        $submissions = FormSubmission::where('form_id', $form->id)
            ->where('status', 'submitted')
            ->where('review_status', 'approved')
            ->with(['responses', 'employee', 'reviewer'])
            ->limit(5000)
            ->get();

        $rows = collect();
        foreach ($submissions as $sub) {
            $byKey = $sub->responses->keyBy('field_key');
            $workDate = $this->resolveDate($byKey, $dateKey, $sub);
            if ($workDate->lt($from->copy()->startOfDay()) || $workDate->gt($to->copy()->endOfDay())) {
                continue;
            }

            $details = collect($detailKeys)
                ->map(function ($k) use ($byKey, $fields) {
                    $val = optional($byKey->get($k))->getDisplayValue();
                    if (! filled($val)) {
                        return null;
                    }
                    $label = optional($fields->firstWhere('field_key', $k))->label ?? $k;
                    return "{$label}: {$val}";
                })
                ->filter()->implode(' · ');

            $rows->push((object) [
                'employee'    => optional($sub->employee)->full_name ?: '—',
                'email'       => optional($sub->employee)->email,
                'date'        => $workDate,
                'hours'       => $this->resolveHours($byKey, $hoursKey),
                'details'     => $details,
                'approved_on' => $sub->reviewed_at,
                'approved_by' => optional($sub->reviewer)->full_name,
                'submission_id' => $sub->id,
            ]);
        }

        $rows = $rows->sortBy([['employee', 'asc'], ['date', 'asc']])->values();

        $byEmployee = $rows->groupBy('employee')->map(fn ($g) => (object) [
            'employee' => $g->first()->employee,
            'email'    => $g->first()->email,
            'entries'  => $g->count(),
            'hours'    => round($g->sum('hours'), 2),
            'rows'     => $g->values(),
        ])->values();

        return [
            'form'          => $form,
            'rows'          => $rows,
            'byEmployee'    => $byEmployee,
            'entryCount'    => $rows->count(),
            'employeeCount' => $rows->pluck('employee')->unique()->count(),
            'totalHours'    => round($rows->sum('hours'), 2),
            'hasHours'      => $hoursKey !== null,
            'hasDate'       => $dateKey !== null,
        ];
    }

    /** First field whose key/label contains a keyword, else first of a given type. */
    private function detectField(Collection $fields, array $keywords, array $types): ?string
    {
        foreach ($fields as $f) {
            $hay = Str::lower(($f->field_key ?? '') . ' ' . ($f->label ?? ''));
            foreach ($keywords as $kw) {
                if (Str::contains($hay, $kw)) {
                    return $f->field_key;
                }
            }
        }
        foreach ($types as $type) {
            if ($f = $fields->firstWhere('type', $type)) {
                return $f->field_key;
            }
        }
        return null;
    }

    private function resolveDate($byKey, ?string $dateKey, FormSubmission $sub): Carbon
    {
        if ($dateKey && ($resp = $byKey->get($dateKey)) && filled($resp->value)) {
            try {
                return Carbon::parse($resp->value)->startOfDay();
            } catch (\Throwable $e) {
                // fall through to submitted date
            }
        }

        return Carbon::parse($sub->submitted_at ?? $sub->created_at)->startOfDay();
    }

    private function resolveHours($byKey, ?string $hoursKey): ?float
    {
        if (! $hoursKey || ! ($resp = $byKey->get($hoursKey)) || ! filled($resp->value)) {
            return null;
        }
        if (is_numeric($resp->value)) {
            return (float) $resp->value;
        }
        if (preg_match('/[\d]+(\.[\d]+)?/', (string) $resp->value, $m)) {
            return (float) $m[0];
        }
        return null;
    }
}
