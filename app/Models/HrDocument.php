<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A filled, on-file copy of an HR document template for one employee.
 */
class HrDocument extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'hr_document_template_id', 'letterhead_id', 'user_id', 'template_name', 'title',
        'schema', 'data', 'period_start', 'period_end', 'status', 'created_by', 'sent_at', 'archived_at',
    ];

    protected $casts = [
        'schema'       => 'array',
        'data'         => 'array',
        'period_start' => 'date',
        'period_end'   => 'date',
        'sent_at'      => 'datetime',
        'archived_at'  => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(HrDocumentTemplate::class, 'hr_document_template_id');
    }

    public function letterhead()
    {
        return $this->belongsTo(LetterheadTemplate::class, 'letterhead_id');
    }

    /** Letterhead to render with: the document's own, else its template's, else the workspace default. */
    public function effectiveLetterhead(): ?LetterheadTemplate
    {
        return $this->letterhead
            ?? optional($this->template)->letterhead
            ?? LetterheadTemplate::default();
    }

    public function signers()
    {
        return $this->hasMany(HrDocumentSigner::class);
    }

    /** All signature fields declared in the (snapshotted) schema. */
    public function signatureFields(): array
    {
        return collect($this->schema)
            ->flatMap(fn ($s) => $s['fields'] ?? [])
            ->where('type', 'signature')
            ->values()->all();
    }

    /**
     * The meeting date recorded on the form, if its template has one — the
     * built-in `date_of_meeting` field, or any date field labelled "meeting".
     */
    public function getMeetingDateAttribute(): ?\Illuminate\Support\Carbon
    {
        $field = $this->dateFields()->first(fn ($f) => ($f['id'] ?? null) === 'date_of_meeting'
            || str_contains(strtolower($f['label'] ?? ''), 'meeting'));

        return $field ? $this->dateValue($field['id']) : null;
    }

    /**
     * The leave / absence / lateness date(s) the form is about, read from its
     * own fields: e.g. "Date of unplanned leave" → "Date of return" on a
     * return-to-work form, or "Date of lateness" on a lateness review.
     *
     * @return array{from: ?\Illuminate\Support\Carbon, to: ?\Illuminate\Support\Carbon, text: ?string}
     */
    public function getLeaveDatesAttribute(): array
    {
        $label = fn ($f) => strtolower($f['label'] ?? '');
        $isLeave = fn ($f) => \Illuminate\Support\Str::contains($label($f), ['leave', 'absence', 'absent', 'lateness', 'late', 'unplanned', 'off'])
            && ! \Illuminate\Support\Str::contains($label($f), ['return', 'meeting', 'notified', 'total']);

        $fromField = $this->dateFields()->first($isLeave);
        $toField = $this->dateFields()->first(fn ($f) => str_contains($label($f), 'return'));

        $from = $fromField ? $this->dateValue($fromField['id']) : null;
        $to = $toField ? $this->dateValue($toField['id']) : null;

        // Free text such as "10 Sep, 12 Sep" that isn't a single parseable date.
        $raw = $fromField ? trim((string) (($this->data ?? [])[$fromField['id']] ?? '')) : '';
        $text = (! $from && $raw !== '') ? \Illuminate\Support\Str::limit($raw, 40) : null;

        return ['from' => $from, 'to' => $to, 'text' => $text];
    }

    /** Fields that hold a date: typed as date, or labelled "Date of …". */
    private function dateFields(): \Illuminate\Support\Collection
    {
        return collect($this->schema)
            ->flatMap(fn ($s) => $s['fields'] ?? [])
            ->filter(fn ($f) => ($f['type'] ?? null) === 'date' || str_starts_with(strtolower($f['label'] ?? ''), 'date'))
            ->values();
    }

    /** A field's stored value as a Carbon date, or null if empty / not a date. */
    private function dateValue(string $fieldId): ?\Illuminate\Support\Carbon
    {
        $value = trim((string) (($this->data ?? [])[$fieldId] ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** True once every assigned signer has signed. */
    public function getFullySignedAttribute(): bool
    {
        return $this->signers->isNotEmpty() && $this->signers->whereNull('signed_at')->isEmpty();
    }

    /** The employee this document is about. */
    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** True once at least one signature field has been signed. */
    public function getIsSignedAttribute(): bool
    {
        foreach ((array) $this->data as $value) {
            if (is_array($value) && ! empty($value['image'])) {
                return true;
            }
        }

        return false;
    }
}
