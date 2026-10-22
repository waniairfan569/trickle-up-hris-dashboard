<?php

namespace App\Models\Concerns;

use App\Models\AcknowledgementReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One vocabulary for "who has confirmed they read this".
 *
 * Policies and company documents both ask people to acknowledge something, and
 * both record it — but they record it differently. A policy creates a row for
 * every assigned person up front and moves it through pending → viewed →
 * acknowledged, so the people who still owe it are rows you can count. A
 * document only writes a row when somebody actually acknowledges, so the people
 * who still owe it are the audience minus those rows.
 *
 * That difference is why progress could not be read the same way on both, and
 * why anything reporting on it had to know which kind it was looking at. Each
 * model keeps its own table and its own answer to "who is the audience"; this
 * puts the same questions on top.
 */
trait NeedsAcknowledgement
{
    /** Where to send somebody who still has to acknowledge this. */
    abstract public function acknowledgementUrl(): string;

    /** Everyone expected to acknowledge this. */
    public function acknowledgementAudience()
    {
        // A policy knows its assignees; a document knows who can see it.
        foreach (['getAssignedUsers', 'eligibleUsers'] as $method) {
            if (method_exists($this, $method)) {
                return $this->{$method}();
            }
        }

        return collect();
    }

    /** How many people have actually acknowledged it. */
    public function acknowledgedCount(): int
    {
        $query = $this->acknowledgments();

        // Where a status is kept, only "acknowledged" counts — a row that is
        // merely pending or viewed is somebody who has not done it yet.
        if ($this->acknowledgementsHaveStatus()) {
            $query = $query->where('status', 'acknowledged');
        }

        return (int) $query->count();
    }

    /**
     * How many people were asked. For a policy that is the rows it created; for
     * a document the rows only exist once done, so the audience is the measure.
     */
    public function acknowledgementExpected(): int
    {
        if ($this->acknowledgementsHaveStatus()) {
            return (int) $this->acknowledgments()->count();
        }

        return $this->acknowledgementAudience()->count();
    }

    /** Percentage done, or null when nobody has been asked yet. */
    public function acknowledgementRate(): ?int
    {
        $expected = $this->acknowledgementExpected();

        return $expected > 0
            ? (int) round($this->acknowledgedCount() / $expected * 100)
            : null;
    }

    public function isAcknowledgedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $query = $this->acknowledgments()->where('user_id', $user->id);

        if ($this->acknowledgementsHaveStatus()) {
            $query = $query->where('status', 'acknowledged');
        }

        return $query->exists();
    }

    /**
     * Everyone who was asked and has not done it.
     *
     * Read from whatever counts as "asked" on this side — the rows where a
     * status is kept, the audience where rows only appear once done. Mixing the
     * two gives nonsense: a policy with rows but no assignment records would
     * report people outstanding and nobody waiting.
     */
    public function awaitingAcknowledgement()
    {
        if ($this->acknowledgementsHaveStatus()) {
            // Look the people up by id rather than through a relation: the two
            // acknowledgement models both call theirs "employee", and a third
            // one need not call it anything in particular.
            return User::whereIn('id', $this->acknowledgments()
                ->where('status', '!=', 'acknowledged')
                ->pluck('user_id'))->get();
        }

        $done = $this->acknowledgments()->pluck('user_id')->all();

        return $this->acknowledgementAudience()
            ->reject(fn ($u) => in_array($u->id, $done))
            ->values();
    }

    /** Times we have chased somebody about this one. */
    public function acknowledgementReminders(): MorphMany
    {
        return $this->morphMany(AcknowledgementReminder::class, 'acknowledgeable');
    }

    /**
     * Who should be chased about this now: everyone still outstanding, minus
     * anybody already chased since $quietSince.
     *
     * The caller decides what counts as too recent to chase again, and whether
     * this thing is old enough to chase at all — this only answers who.
     */
    public function acknowledgementChaseList(?Carbon $quietSince = null)
    {
        $outstanding = $this->awaitingAcknowledgement();

        if ($outstanding->isEmpty() || ! $quietSince) {
            return $outstanding;
        }

        $chased = $this->acknowledgementReminders()
            ->where('sent_at', '>=', $quietSince)
            ->pluck('user_id')
            ->all();

        return $outstanding->reject(fn ($u) => in_array($u->id, $chased))->values();
    }

    /** Note that we have just chased this person, keeping a count of attempts. */
    public function recordAcknowledgementReminder(User $user): void
    {
        $reminder = $this->acknowledgementReminders()
            ->where('user_id', $user->id)
            ->first();

        if ($reminder) {
            $reminder->update([
                'sent_at' => now(),
                'times_sent' => $reminder->times_sent + 1,
            ]);

            return;
        }

        $this->acknowledgementReminders()->create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $user->id,
            'sent_at' => now(),
            'times_sent' => 1,
        ]);
    }

    /**
     * When this was put in front of people — what "it has sat unacknowledged
     * for N days" is measured from. Each side can override; the fallback is
     * when the record was created.
     */
    public function acknowledgementPublishedAt(): ?Carbon
    {
        return $this->created_at;
    }

    /** A date by which it should have been acknowledged, where one is set. */
    public function acknowledgementDueAt(): ?Carbon
    {
        return null;
    }

    /**
     * Does this side keep a status on each row? Policies do and track pending
     * and viewed; documents write a row only when it is done.
     */
    protected function acknowledgementsHaveStatus(): bool
    {
        // Asked once per model class, not once per call — this sits inside loops
        // that render a whole list.
        static $cache = [];

        $related = $this->acknowledgments()->getRelated();
        $table = $related->getTable();

        return $cache[$table] ??= $related->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($table, 'status');
    }
}
