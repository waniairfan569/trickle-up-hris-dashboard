<?php

namespace App\Services;

use App\Models\HrDocument;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sending an HR document out for signature — the one place it happens.
 *
 * This used to live inside the controller method behind the Send button, which
 * meant a document generated at one in the morning could only ever be left as a
 * draft: the logic needed a request to read from. The decisions it reads are
 * the same either way — what happens to the manager's signature section, and
 * whose signature goes on it — so they are arguments now, whether they come
 * from somebody filling in the send form or from a template that was told once.
 */
class HrDocumentDispatcher
{
    /**
     * Send a document to whoever still has to sign it.
     *
     * $options:
     *   manager_mode  'self' | 'manager' | 'none'  what happens to the manager section
     *   manager_id    who to ask, when the mode is 'manager' (defaults to their line manager)
     *   signature     a data: image to stamp, when the mode is 'self'
     *   signed_by     the User that signature belongs to
     *   ip            where it was signed from, where that is known
     *
     * @return array{ok: bool, error: ?string, sent_to: array<string>, signed_now: bool, issued: bool}
     */
    public function send(HrDocument $document, array $options = []): array
    {
        $fields = collect($document->signatureFields());

        // Nothing to sign is not a failure. A return-to-work record or a
        // confirmation note is meant to be read, not signed, and used to be
        // stuck as a draft until an admin marked it complete by hand.
        if ($fields->isEmpty()) {
            return $this->issue($document);
        }

        [$mgrFields, $empFields] = $this->split($fields);

        $mode = $mgrFields->isEmpty() ? 'none' : ($options['manager_mode'] ?? 'none');
        $signature = trim((string) ($options['signature'] ?? ''));
        $signedBy = $options['signed_by'] ?? null;

        if ($mode === 'self') {
            if (! Str::startsWith($signature, 'data:image/')) {
                return $this->fail('Add your signature before sending, or choose another option for the manager section.');
            }
            if (! $signedBy instanceof User) {
                return $this->fail('That signature has nobody to attribute it to.');
            }
        }

        $employee = $document->employee;
        $managerId = $options['manager_id'] ?? optional($employee)->manager_id;
        $manager = $mode === 'manager' && $managerId ? User::find($managerId) : null;

        if ($mode === 'manager' && ! $manager) {
            return $this->fail('Pick the line manager who should sign, or sign the manager section yourself.');
        }

        // "Date of meeting" is the day the document goes out — it cannot be
        // known when the draft is built, so stamp it now if it is still blank.
        $this->stampMeetingDate($document);

        // Where each person sits in the signing order: the earliest position
        // among the fields they sign. Zero throughout means no order, which is
        // how every template behaves until somebody sets one.
        $orderOf = fn ($f) => (int) collect($f)->map(fn ($x) => (int) ($x['sign_order'] ?? 0))->filter()->min();

        // Rebuild the signer set (supports re-sending).
        $document->signers()->delete();
        $sentTo = [];
        $signedNow = false;

        // The manager section is signed first, so the document reaches the
        // employee already countersigned.
        if ($mode === 'self') {
            $data = $document->data ?? [];
            foreach ($mgrFields->pluck('id') as $fieldId) {
                $data[$fieldId] = [
                    'image' => $signature,
                    'name'  => $signedBy->full_name,
                    'date'  => now()->toDateString(),
                ];
            }
            $document->data = $data;
            $document->save();

            $document->signers()->create([
                'user_id'    => $signedBy->id,
                'role'       => 'manager',
                'field_ids'  => $mgrFields->pluck('id')->all(),
                'sign_order' => $orderOf($mgrFields),
                'signed_at'  => now(),
                'signed_ip'  => $options['ip'] ?? null,
            ]);
            $signedNow = true;
        }

        if ($employee && $empFields->isNotEmpty()) {
            $document->signers()->create([
                'user_id' => $employee->id, 'role' => 'employee',
                'field_ids' => $empFields->pluck('id')->all(), 'sign_order' => $orderOf($empFields),
            ]);
            $employee->notify(new \App\Notifications\HrDocumentSignatureRequested($document));
            $sentTo[] = $employee->full_name;
        }

        if ($manager) {
            $document->signers()->create([
                'user_id' => $manager->id, 'role' => 'manager',
                'field_ids' => $mgrFields->pluck('id')->all(), 'sign_order' => $orderOf($mgrFields),
            ]);
            $manager->notify(new \App\Notifications\HrDocumentSignatureRequested($document));
            $sentTo[] = $manager->full_name;
        }

        if (empty($sentTo)) {
            return $this->fail('Could not send: no one was left to sign it.');
        }

        $document->update(['status' => 'sent', 'sent_at' => now()]);

        return ['ok' => true, 'error' => null, 'sent_to' => $sentTo, 'signed_now' => $signedNow, 'issued' => false];
    }

    /**
     * Put a document that asks for no signature onto the employee's record and
     * tell them it is there.
     *
     * It is finished the moment it goes out, because nothing is outstanding:
     * no signers, so nothing to chase and nothing to wait for.
     */
    private function issue(HrDocument $document): array
    {
        $employee = $document->employee;

        if (! $employee) {
            return $this->fail('Could not issue: this document is not about anybody.');
        }

        $this->stampMeetingDate($document);

        $document->signers()->delete();
        $document->update(['status' => 'completed', 'sent_at' => now()]);

        try {
            $employee->notify(new \App\Notifications\HrDocumentIssued($document));
        } catch (\Throwable $e) {
            // The document is on their record either way; a mail problem is not
            // a reason to pretend it was not issued.
            report($e);
        }

        return [
            'ok' => true, 'error' => null,
            'sent_to' => [$employee->full_name],
            'signed_now' => false, 'issued' => true,
        ];
    }

    /**
     * Send a freshly generated document, if its template says to.
     *
     * Returns false — leaving a draft exactly as before — whenever the template
     * has not asked for this, or has asked but is not set up to do it. Nothing
     * here ever stops a document being generated: the worst case is the draft
     * an admin would have had anyway.
     */
    public function autoSend(HrDocument $document): bool
    {
        $template = $document->template;

        if (! $template || ! $template->auto_send) {
            return false;
        }

        $mode = in_array($template->auto_send_manager_mode, ['self', 'manager', 'none'], true)
            ? $template->auto_send_manager_mode
            : 'none';

        $options = ['manager_mode' => $mode];

        if ($mode === 'self') {
            $signature = $template->autoSendSignature;

            // Told to stamp a signature it no longer has: leave the draft for a
            // person rather than send a letter with an empty signature box.
            if (! $signature || ! Str::startsWith((string) $signature->image_data, 'data:image/')) {
                return false;
            }

            // Whose signature it is. The admin whose action raised this document
            // comes first; otherwise whoever saved the signature block.
            $signedBy = User::find($document->created_by) ?? User::find($signature->created_by);

            if (! $signedBy) {
                return false;
            }

            $options['signature'] = $signature->image_data;
            $options['signed_by'] = $signedBy;
        }

        $result = $this->send($document, $options);

        if (! $result['ok']) {
            // Worth knowing about — the workspace asked for this and did not get
            // it — but never worth failing the generation over.
            \Log::info("Auto-send skipped for document #{$document->id}: {$result['error']}");
        }

        return $result['ok'];
    }

    /**
     * Who signs which section. The field says so; templates and documents from
     * before that setting existed fall back to the rule that used to decide it,
     * so nothing already in flight changes behaviour.
     */
    private function split(Collection $fields): array
    {
        $isMgr = fn ($f) => isset($f['signer_role'])
            ? $f['signer_role'] === 'manager'
            : Str::contains(Str::lower($f['label'] ?? ''), 'manager');

        return [$fields->filter($isMgr), $fields->reject($isMgr)];
    }

    /** Fill an empty "date of meeting" field with today, on the way out. */
    private function stampMeetingDate(HrDocument $document): void
    {
        $data = $document->data ?? [];
        $touched = false;

        foreach (collect($document->schema)->flatMap(fn ($s) => $s['fields'] ?? []) as $field) {
            if (! is_array($field) || empty($field['id'])) {
                continue;
            }
            $label = strtolower((string) ($field['label'] ?? ''));
            $isMeeting = $field['id'] === 'date_of_meeting'
                || (str_contains($label, 'date') && str_contains($label, 'meeting'));

            if ($isMeeting && trim((string) ($data[$field['id']] ?? '')) === '') {
                $data[$field['id']] = now()->toDateString();
                $touched = true;
            }
        }

        if ($touched) {
            $document->data = $data;
            $document->save();
        }
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'sent_to' => [], 'signed_now' => false, 'issued' => false];
    }
}
