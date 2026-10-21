<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * One vocabulary for the two kinds of signer row.
 *
 * The same idea is spelled differently on each side — an HR document signer has
 * `sign_order` and is done when `signed_at` is set; an e-signature signer has
 * `position` and a `status` column. Code that only wants to know "whose turn is
 * it" had to know which table it was looking at, which is why ordered signing
 * ended up written twice.
 *
 * The model keeps its own columns; this adds the shared names on top.
 */
trait IsDocumentSigner
{
    /** Where this person sits in the order. 0 means no order — sign any time. */
    public function signPosition(): int
    {
        foreach (['sign_order', 'position'] as $column) {
            if (array_key_exists($column, $this->getAttributes())) {
                return (int) ($this->{$column} ?? 0);
            }
        }

        return 0;
    }

    /**
     * Is the document still waiting on this person to act?
     *
     * The e-signature side carries a status and stamps `signed_at` when someone
     * declines as well as when they sign, so "has a date on it" is not the same
     * as "has signed". Where a status exists it decides; otherwise the date does.
     */
    public function isWaiting(): bool
    {
        if (array_key_exists('status', $this->getAttributes())) {
            return $this->status === 'pending';
        }

        return $this->signed_at === null;
    }

    /** Did this person actually sign — as opposed to declining? */
    public function hasSigned(): bool
    {
        if (array_key_exists('status', $this->getAttributes())) {
            return $this->status === 'signed';
        }

        return $this->signed_at !== null;
    }

    public function hasDeclined(): bool
    {
        return array_key_exists('status', $this->getAttributes())
            && $this->status === 'declined';
    }

    /** Is this row waiting on this person? */
    public function isFor(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    /**
     * Someone the document is still waiting on ahead of this person, or null
     * when it is their turn. A position of 0 never blocks and is never blocked,
     * which is how signing behaved before an order could be set.
     */
    public function blockedBy(): ?self
    {
        $position = $this->signPosition();
        if ($position <= 0) {
            return null;
        }

        return $this->siblingSigners()
            ->filter(fn ($s) => $s->isWaiting()
                && $s->signPosition() > 0
                && $s->signPosition() < $position)
            ->sortBy(fn ($s) => $s->signPosition())
            ->first();
    }

    /** Every signer on the same document, including this one. */
    protected function siblingSigners()
    {
        foreach (['hr_document_id', 'document_request_id'] as $column) {
            if (array_key_exists($column, $this->getAttributes())) {
                return static::where($column, $this->{$column})->with('user')->get();
            }
        }

        return collect([$this]);
    }
}
