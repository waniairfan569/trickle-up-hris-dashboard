<?php

namespace App\Console\Commands;

use App\Models\DocumentRequest;
use App\Notifications\DocumentSignatureReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDocumentSignatureReminders extends Command
{
    protected $signature = 'documents:send-signature-reminders
                            {--hours=24 : How long a document may sit unsigned before a reminder is sent}
                            {--dry : Report only, do not send}';

    protected $description = 'Email the current signer when an assigned document has gone unsigned for 24+ hours.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours') ?: 24;
        $dry = (bool) $this->option('dry');
        $cutoff = now()->subHours($hours);

        // Only documents still awaiting signatures.
        $requests = DocumentRequest::where('status', 'in_progress')
            ->with(['signers.user', 'template', 'creator'])
            ->get();

        $checked = 0;
        $sent = 0;

        foreach ($requests as $req) {
            // The person whose turn it currently is (sequential signing).
            $current = $req->signers->firstWhere('status', 'pending');
            if (!$current || !$current->user || !$current->user->email) {
                continue;
            }
            $checked++;

            // When did this signer become responsible? The moment the previous
            // signer signed — or, if nobody has signed yet, when the request was
            // created (that's when the first signer was notified).
            $lastSignedAt = $req->signers->where('status', 'signed')->max('signed_at');
            $assignedAt = $lastSignedAt ? Carbon::parse($lastSignedAt) : $req->created_at;

            // Not old enough yet.
            if ($assignedAt->gt($cutoff)) {
                continue;
            }

            // Already reminded within the current window — don't nag more than once per period.
            if ($current->reminder_sent_at && $current->reminder_sent_at->gt($cutoff)) {
                continue;
            }

            if ($dry) {
                $this->line("[dry] would remind {$current->user->email} for request #{$req->id} ({$req->template?->name}) — assigned {$assignedAt->diffForHumans()}");
                $sent++;
                continue;
            }

            try {
                $current->user->notify(new DocumentSignatureReminder($req));
                $current->forceFill(['reminder_sent_at' => now()])->save();
                $req->logEvent('reminder_sent', null, "Signature reminder emailed to {$current->user->full_name}");
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Failed to remind for request #{$req->id}: {$e->getMessage()}");
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Checked {$checked} awaiting signer(s); reminded {$sent}.");

        return self::SUCCESS;
    }
}
