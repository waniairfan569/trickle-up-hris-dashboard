<?php

namespace App\Console\Commands;

use App\Models\CompanyDocument;
use App\Models\CompanyPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AcknowledgementDue;
use App\Support\NotificationCategories;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Chase whoever still has to confirm they have read something.
 *
 * Signing has been chased for a while; acknowledgement never was, so a policy
 * or document could sit unacknowledged indefinitely with nobody told. Both
 * kinds answer the same questions now (NeedsAcknowledgement), so one job
 * covers them.
 *
 * Three guards, because switching this on in a workspace with years of history
 * would otherwise email everybody about everything at once:
 *
 *   --after   nothing published before this is ever chased. Defaults to 30 days
 *             back, so turning it on only chases current paperwork, not the
 *             backlog. Pass a date to go further back deliberately.
 *   --days    how long something may sit unacknowledged before the first chase.
 *   --every   how long to leave somebody alone between chases.
 */
class SendAcknowledgementReminders extends Command
{
    protected $signature = 'documents:send-acknowledgement-reminders
                            {--days=3 : Days something may sit unacknowledged before chasing}
                            {--every=7 : Days to leave a person alone between chases}
                            {--after= : Ignore anything published before this (date, or days back; default 30)}
                            {--limit=200 : Most reminders to send in one run}
                            {--dry : Report only, do not send}';

    protected $description = 'Remind people about policies and documents they have not acknowledged.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $sitFor = max(0, (int) $this->option('days'));
        $quietFor = max(1, (int) $this->option('every'));
        $limit = max(1, (int) $this->option('limit'));

        $publishedAfter = $this->publishedAfter();
        $staleBy = now()->subDays($sitFor);
        $quietSince = now()->subDays($quietFor);

        $this->line('Chasing things published after ' . $publishedAfter->format('j M Y')
            . ", unacknowledged for {$sitFor}+ day(s), at most one reminder per person every {$quietFor} day(s).");

        $manager = app(TenantManager::class);
        $tenants = Tenant::all();
        $sent = 0;
        $skipped = 0;
        $muted = 0;

        $run = function () use ($publishedAfter, $staleBy, $quietSince, $dry, $limit, &$sent, &$skipped, &$muted) {
            foreach ($this->subjects() as $subject) {
                $published = $subject->acknowledgementPublishedAt();

                // Too old to chase, or too new to be late yet.
                if (! $published || $published->lt($publishedAfter) || $published->gt($staleBy)) {
                    $skipped++;
                    continue;
                }

                foreach ($subject->acknowledgementChaseList($quietSince) as $user) {
                    if ($sent >= $limit) {
                        $this->warn("Reached the limit of {$limit} reminder(s); the rest wait for the next run.");

                        return;
                    }

                    if (! $user->email) {
                        continue;
                    }

                    // Somebody who has switched this category off on every
                    // channel is not chased, and is not recorded as chased —
                    // otherwise the count would claim sends that never happened
                    // and they would be skipped for the whole quiet period.
                    if ($this->hasMutedReminders($user, $subject)) {
                        $muted++;
                        continue;
                    }

                    if ($dry) {
                        $this->line("[dry] would chase {$user->email} about {$this->label($subject)}");
                        $sent++;
                        continue;
                    }

                    try {
                        $user->notify(new AcknowledgementDue($subject));
                        $subject->recordAcknowledgementReminder($user);
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                        $this->error("Could not chase {$user->email} about {$this->label($subject)}: {$e->getMessage()}");
                    }
                }
            }
        };

        if ($tenants->count() <= 1) {
            $manager->set($tenants->first());
            $run();
        } else {
            foreach ($tenants as $tenant) {
                $manager->set($tenant);
                $run();
            }
        }

        $manager->set(null);

        $summary = ($dry ? 'Would send ' : 'Sent ') . $sent
            . " reminder(s). Skipped {$skipped} item(s) outside the window.";

        if ($muted > 0) {
            $summary .= " Left {$muted} person(s) alone who switched these off.";
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Everything in this workspace that asks for an acknowledgement and is
     * actually live. A draft or archived item is nobody's problem yet.
     */
    private function subjects(): Collection
    {
        $subjects = collect();

        if (plan_allows('policies')) {
            $subjects = $subjects->merge(
                CompanyPolicy::where('requires_acknowledgment', true)
                    ->where('status', 'active')
                    ->get()
            );
        }

        if (plan_allows('documents') || plan_allows('esign')) {
            $subjects = $subjects->merge(
                CompanyDocument::where('requires_acknowledgment', true)
                    ->where('is_active', true)
                    ->get()
            );
        }

        return $subjects;
    }

    /** The oldest thing we are willing to chase about. */
    private function publishedAfter(): \Illuminate\Support\Carbon
    {
        $after = trim((string) $this->option('after'));

        if ($after === '') {
            return now()->subDays(30)->startOfDay();
        }

        // A plain number means "that many days back"; anything else is a date.
        if (ctype_digit($after)) {
            return now()->subDays((int) $after)->startOfDay();
        }

        try {
            return \Illuminate\Support\Carbon::parse($after)->startOfDay();
        } catch (\Throwable) {
            $this->warn("Could not read --after=\"{$after}\"; using the last 30 days.");

            return now()->subDays(30)->startOfDay();
        }
    }

    /**
     * Has this person switched these reminders off on every channel? The
     * category depends on the kind of thing, so ask the notification itself.
     */
    private function hasMutedReminders(User $user, Model $subject): bool
    {
        $category = NotificationCategories::for(new AcknowledgementDue($subject));

        if (! $category) {
            return false;
        }

        return ! $user->wantsNotification($category, 'mail')
            && ! $user->wantsNotification($category, 'database');
    }

    /** Something readable for the log line. */
    private function label(Model $subject): string
    {
        $kind = $subject instanceof CompanyPolicy ? 'policy' : 'document';

        return "{$kind} #{$subject->id} ({$subject->title})";
    }
}
