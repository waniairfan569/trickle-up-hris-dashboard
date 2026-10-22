<?php

namespace App\Services;

use App\Models\ProfileChangeRequest;
use App\Models\ProfileField;
use App\Models\User;
use App\Notifications\ProfileChangeNoted;
use App\Notifications\ProfileChangeRequested;
use App\Notifications\ProfileChangeReviewed;

/**
 * What happens when somebody changes a field on their own record.
 *
 * An employee editing their own profile used to write straight to it, whatever
 * the field was: a change of address and a change of medical conditions were
 * treated identically, and nobody was told about either. A workspace can now
 * say, per field, that a change should be reported to HR or held until HR
 * approves it.
 *
 * Only self-edits pass through here. An admin editing somebody's record is
 * already the review step.
 */
class ProfileChangeGuard
{
    /**
     * Should this change be held back rather than written?
     *
     * Returns the request it raised when so, and null when the value should be
     * saved as normal. "Notify" saves and tells HR, so it returns null too.
     */
    public function intercept(User $employee, User $editor, ProfileField $field, $newValue, $oldValue): ?ProfileChangeRequest
    {
        // Only a person editing their own record. Anyone else doing it is
        // already somebody checking the change.
        if ($editor->id !== $employee->id) {
            return null;
        }

        if ($this->unchanged($oldValue, $newValue)) {
            return null;
        }

        return match ($field->changeAction()) {
            'review' => $this->hold($employee, $editor, $field, $newValue, $oldValue),
            'notify' => $this->note($employee, $field, $newValue, $oldValue),
            default  => null,
        };
    }

    /** Hold the change and ask HR to look at it. */
    private function hold(User $employee, User $editor, ProfileField $field, $newValue, $oldValue): ProfileChangeRequest
    {
        // One pending request per field: asking twice replaces the first ask
        // rather than queueing two answers for the same question.
        $request = ProfileChangeRequest::updateOrCreate(
            ['user_id' => $employee->id, 'field_id' => $field->id, 'status' => 'pending'],
            [
                'tenant_id' => $employee->tenant_id,
                'old_value' => $this->flatten($oldValue),
                'new_value' => $this->flatten($newValue),
                'requested_by' => $editor->id,
            ]
        );

        $this->tell(fn ($admin) => $admin->notify(new ProfileChangeRequested($request)));

        return $request;
    }

    /** Let the change through, but say that it happened. */
    private function note(User $employee, ProfileField $field, $newValue, $oldValue): ?ProfileChangeRequest
    {
        $this->tell(fn ($admin) => $admin->notify(
            new ProfileChangeNoted($employee, $field, $this->flatten($oldValue), $this->flatten($newValue))
        ));

        return null;
    }

    /** Apply a held change, or turn it down. */
    public function decide(ProfileChangeRequest $request, User $reviewer, bool $approve, ?string $note = null): void
    {
        if (! $request->isPending()) {
            return;
        }

        if ($approve) {
            $this->write($request);
        }

        $request->update([
            'status' => $approve ? 'approved' : 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        try {
            $request->employee?->notify(new ProfileChangeReviewed($request->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Put an approved value where the profile keeps it. */
    private function write(ProfileChangeRequest $request): void
    {
        $employee = $request->employee;
        $field = $request->field;

        if (! $employee || ! $field) {
            return;
        }

        // Native columns live on the user; everything else is a field value.
        if ($employee->isFillable($field->key) || array_key_exists($field->key, $employee->getAttributes())) {
            $employee->update([$field->key => $request->new_value]);

            return;
        }

        \App\Models\EmployeeFieldValue::updateOrCreate(
            ['user_id' => $employee->id, 'field_id' => $field->id],
            ['value' => $request->new_value, 'updated_by' => $request->reviewed_by]
        );
    }

    /** Whoever looks after employee records in this workspace. */
    private function tell(callable $send): void
    {
        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', '!=', 'deactivated')
            ->get();

        foreach ($admins as $admin) {
            try {
                $send($admin);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Values arrive as strings, arrays or nulls; store something readable. */
    private function flatten($value): ?string
    {
        if (is_array($value)) {
            return json_encode(array_values($value));
        }

        return $value === null ? null : (string) $value;
    }

    private function unchanged($old, $new): bool
    {
        return $this->flatten($old) === $this->flatten($new);
    }
}
