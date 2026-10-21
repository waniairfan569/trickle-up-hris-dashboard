<?php

namespace App\Console\Commands;

use App\Models\DocumentRequest;
use App\Models\HrDocument;
use App\Notifications\DocumentSignatureReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Chase whoever a document is waiting on.
 *
 * This used to look only at e-signature requests, because chasing was written
 * on that side and the two systems shared no code — so HR documents, the ones
 * actually in daily use, were never chased at all. Both are covered now, using
 * the same rule for whose turn it is.
 */
class SendDocumentSignatureReminders extends Command
{
    protected $signature = 'documents:send-signature-reminders
                            {--hours=24 : How long a document may sit unsigned before a reminder is sent}
                            {--dry : Report only, do not send}';

    protected $description = 'Remind the current signer when a document has gone unsigned for 24+ hours.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours') ?: 24;
        $dry = (bool) $this->option('dry');
        $cutoff = now()->subHours($hours);

        $checked = 0;
        $sent = 0;

        $documents = collect()
            ->merge(DocumentRequest::where('status', 'in_progress')
                ->with(['signers.user', 'template', 'creator'])->get())
            ->merge(HrDocument::where('status', 'sent')
                ->with(['signers.user', 'creator'])->get());

        foreach ($documents as $document) {
            $current = $document->currentSigner();
            if (! $current || ! $current->user || ! $current->user->email) {
                continue;
            }

            $checked++;

            // When did this person become responsible? The moment the one before
            // them signed, or when the document went out if nobody has yet.
            $lastSigned = $document->signers
                ->filter(fn ($s) => $s->hasSigned())
                ->max('signed_at');

            $assignedAt = $lastSigned
                ? Carbon::parse($lastSigned)
                : ($document->sent_at ?? $document->created_at);

            if ($assignedAt->gt($cutoff)) {
                continue;   // not old enough yet
            }

            if ($current->reminder_sent_at && $current->reminder_sent_at->gt($cutoff)) {
                continue;   // already chased this time round
            }

            $label = $this->label($document);

            if ($dry) {
                $this->line("[dry] would remind {$current->user->email} about {$label} — waiting since {$assignedAt->diffForHumans()}");
                $sent++;
                continue;
            }

            try {
                $current->user->notify(new DocumentSignatureReminder($document));
                $current->update(['reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $this->error("Could not remind {$current->user->email} about {$label}: {$e->getMessage()}");
            }
        }

        $this->info(($dry ? 'Would send ' : 'Sent ') . $sent . " reminder(s), from {$checked} document(s) awaiting a signature.");

        return self::SUCCESS;
    }

    /** Something readable for the log line. */
    private function label(Model $document): string
    {
        $kind = $document instanceof HrDocument ? 'HR document' : 'request';

        return "{$kind} #{$document->id} ({$document->signingTitle()})";
    }
}
