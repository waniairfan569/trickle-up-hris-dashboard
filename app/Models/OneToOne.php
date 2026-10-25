<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A one-to-one between an employee and whoever is holding it.
 *
 * A time is only a fixture once the other side has agreed to it, so a booking
 * starts as `proposed` and carries `proposed_by`. Whoever did not propose is
 * the one who owes an answer, and they may answer with a different time — at
 * which point the proposal changes hands rather than starting again, so the
 * trail of what was offered and by whom survives.
 */
class OneToOne extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    /** How long a booking lasts if nobody says otherwise. */
    public const DEFAULT_MINUTES = 30;

    protected $fillable = [
        'user_id',
        'manager_id',
        'scheduled_at',
        'duration_minutes',
        'subject',
        'agenda',
        'location',
        'is_online',
        'meeting_link',
        'status',
        'proposed_by',
        'responded_at',
        'held_at',
        'outcome',
        'private_note',
        'about_type',
        'about_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'responded_at' => 'datetime',
        'held_at' => 'datetime',
        'is_online' => 'boolean',
        'duration_minutes' => 'integer',
    ];

    // --- Relationships -------------------------------------------------------

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The thing that caused this conversation, if anything did. */
    public function about()
    {
        return $this->morphTo();
    }

    public function events()
    {
        return $this->hasMany(OneToOneEvent::class)->orderBy('id');
    }

    // --- Scopes --------------------------------------------------------------

    /** Either side of the conversation — the employee or the manager. */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('manager_id', $user->id);
        });
    }

    /** Still going to happen: agreed or awaiting an answer, and not yet past. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', ['proposed', 'confirmed'])
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at');
    }

    /** Happened, or should have. */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['held', 'declined', 'cancelled', 'missed'])
                ->orWhere('scheduled_at', '<', now()->startOfDay());
        })->orderByDesc('scheduled_at');
    }

    /**
     * Agreed, the time has gone by, and nobody has said whether it happened.
     * These are the ones that quietly rot, so they are asked for by name.
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['proposed', 'confirmed'])
            ->where('scheduled_at', '<', now())
            ->orderBy('scheduled_at');
    }

    // --- State ---------------------------------------------------------------

    public function endsAt(): Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes ?: self::DEFAULT_MINUTES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['proposed', 'confirmed'], true);
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    /**
     * Who owes an answer — the participant who did not put this time forward.
     * Null once the booking is settled one way or the other.
     */
    public function awaitingAnswerFrom(): ?User
    {
        if ($this->status !== 'proposed') {
            return null;
        }

        return $this->proposed_by === $this->user_id ? $this->manager : $this->employee;
    }

    public function isAwaitingAnswerFrom(User $user): bool
    {
        return optional($this->awaitingAnswerFrom())->id === $user->id;
    }

    /** The other person, seen from one side. */
    public function otherParty(User $user): ?User
    {
        return $user->id === $this->user_id ? $this->manager : $this->employee;
    }

    public function involves(User $user): bool
    {
        return in_array($user->id, [$this->user_id, $this->manager_id], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'proposed' => 'Awaiting an answer',
            'confirmed' => 'Agreed',
            'declined' => 'Declined',
            'held' => 'Held',
            'cancelled' => 'Cancelled',
            'missed' => 'Did not happen',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusColour(): string
    {
        return match ($this->status) {
            'confirmed' => 'emerald',
            'proposed' => 'amber',
            'held' => 'slate',
            'declined', 'missed' => 'rose',
            default => 'slate',
        };
    }

    /** What this conversation was called for, in words, if anything called it. */
    public function aboutLabel(): ?string
    {
        if (! $this->about_type) {
            return null;
        }

        return match ($this->about_type) {
            Probation::class => 'Probation review',
            PayReview::class => 'Pay review',
            default => class_basename($this->about_type),
        };
    }

    // --- Trail ---------------------------------------------------------------

    public function logEvent(string $action, ?User $by = null, ?string $note = null, ?Carbon $newTime = null): void
    {
        $this->events()->create([
            'action' => $action,
            'note' => $note,
            'new_time' => $newTime,
            'acted_by' => $by?->id,
            'created_at' => now(),
        ]);
    }
}
