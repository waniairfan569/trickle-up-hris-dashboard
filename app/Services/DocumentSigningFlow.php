<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * What happens the moment somebody signs — written once for both kinds of
 * document.
 *
 * HR documents and e-signature requests each did the same five things in their
 * own controller: stamp the signer, work out who is left, tell the next person,
 * mark the document finished, tell whoever was waiting on it. They had drifted:
 * the e-signature side notified the next signer and the HR side did not, so an
 * HR document with a signing order left the second person waiting for a
 * message that never came.
 *
 * This owns the sequence. Who to notify stays with the caller, because the two
 * systems have their own notification classes and their own idea of what
 * "finished" involves.
 */
class DocumentSigningFlow
{
    /**
     * Record one person's signature and report what it means for the document.
     *
     * @return array{next: ?Model, complete: bool, declined: bool}
     */
    public function recordSignature(Model $document, Model $signer, ?string $ip = null): array
    {
        $update = ['signed_at' => now()];

        // Each side stores slightly different things about a signature; only
        // write the columns that exist on this one.
        foreach (['signed_ip' => $ip, 'status' => 'signed'] as $column => $value) {
            if ($value !== null && array_key_exists($column, $signer->getAttributes())) {
                $update[$column] = $value;
            }
        }

        $signer->update($update);

        return $this->state($document);
    }

    /**
     * Record a refusal. The document stops here — a decline is not a signature,
     * and nobody after them is asked.
     *
     * @return array{next: ?Model, complete: bool, declined: bool}
     */
    public function recordDecline(Model $document, Model $signer, ?string $ip = null): array
    {
        $update = ['signed_at' => now()];

        foreach (['signed_ip' => $ip, 'status' => 'declined'] as $column => $value) {
            if ($value !== null && array_key_exists($column, $signer->getAttributes())) {
                $update[$column] = $value;
            }
        }

        $signer->update($update);

        return $this->state($document);
    }

    /** Where the document stands now: who is next, and is it finished. */
    public function state(Model $document): array
    {
        $document->load('signers');

        $declined = method_exists($document, 'hasDecline') && $document->hasDecline();

        return [
            // Nobody is "next" once someone has refused.
            'next'     => $declined ? null : $document->currentSigner(),
            'complete' => $document->isFullySigned(),
            'declined' => $declined,
        ];
    }
}
