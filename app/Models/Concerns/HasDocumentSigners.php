<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * One vocabulary for the two kinds of signable document.
 *
 * An HR document and an e-signature request both go round a set of people for
 * signatures, but each answered "whose turn is it" its own way — one by
 * scanning for a null `signed_at`, the other by a `status` of pending against
 * `current_position`. Anything wanting to work on both had to be written twice.
 *
 * Each model keeps its own table and its own `signers()` relation; this adds
 * the shared questions on top of whatever is there.
 */
trait HasDocumentSigners
{
    /** Signers in the order they are asked, unordered ones last. */
    public function orderedSigners()
    {
        return $this->signers
            ->sortBy(fn ($s) => $s->signPosition() ?: PHP_INT_MAX)
            ->values();
    }

    /** The signer whose turn it is now, or null when none is waiting. */
    public function currentSigner()
    {
        return $this->orderedSigners()->first(fn ($s) => $s->isWaiting());
    }

    /** Is this person the one being waited on right now? */
    public function isAwaitingSignature(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $signer = $this->signerFor($user);

        return $signer !== null && $signer->isWaiting() && $signer->blockedBy() === null;
    }

    /** This person's signer row on this document, if they have one. */
    public function signerFor(?User $user)
    {
        return $user ? $this->signers->first(fn ($s) => $s->isFor($user)) : null;
    }

    /** Everyone still to sign, in order. */
    public function pendingSigners()
    {
        return $this->orderedSigners()->filter(fn ($s) => $s->isWaiting())->values();
    }

    /**
     * Everyone has signed. Not the same as "nobody is waiting" — someone who
     * declined has stopped waiting without signing, and a declined document is
     * not a completed one.
     */
    public function isFullySigned(): bool
    {
        return $this->signers->isNotEmpty()
            && $this->signers->every(fn ($s) => $s->hasSigned());
    }

    /** Anyone refused, which stops the document completing. */
    public function hasDecline(): bool
    {
        return $this->signers->contains(fn ($s) => $s->hasDeclined());
    }
}
