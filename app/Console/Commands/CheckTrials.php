<?php

namespace App\Console\Commands;

use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Drives the trial lifecycle once a day:
 *   1. Reminds trialing workspaces at the configured days-left thresholds.
 *   2. Marks a workspace 'trial_expired' the day its trial ends (the app is
 *      already limited by EnsureTrialActive; this records it for the console).
 *   3. Hard-suspends a workspace that stays expired past the grace period, so
 *      an abandoned trial doesn't sit half-open forever.
 *
 * All transitions are idempotent — safe to run repeatedly.
 */
class CheckTrials extends Command
{
    protected $signature = 'subscriptions:check-trials';

    protected $description = 'Remind, wall, and eventually suspend workspaces whose free trial has ended.';

    public function handle(): int
    {
        $graceDays  = (int) config('plans.trial_grace_days', 14);
        $thresholds = array_map('intval', (array) config('plans.trial_reminder_days', [7, 3, 1]));
        $now = now();

        $reminded = $expired = $suspended = 0;

        $trialing = Tenant::where('status', 'trialing')->whereNotNull('trial_ends_at')->get();

        foreach ($trialing as $tenant) {
            $endsAt = $tenant->trial_ends_at;

            if ($endsAt->isFuture()) {
                // Approaching expiry — remind once per day at a threshold.
                $daysLeft = (int) ceil($now->diffInDays($endsAt, false));
                if (in_array($daysLeft, $thresholds, true) && !$this->remindedToday($tenant, $now)) {
                    SubscriptionEvent::create([
                        'tenant_id'   => $tenant->id,
                        'type'        => 'trial_reminder',
                        'description' => "Trial ends in {$daysLeft} day(s) — reminder sent.",
                    ]);
                    $reminded++;
                }
                continue;
            }

            // Trial has ended.
            $daysPast = (int) $endsAt->diffInDays($now);

            if ($graceDays > 0 && $daysPast < $graceDays) {
                // Within grace: keep the workspace reachable (upgrade wall only),
                // but record the expiry once so operators can see it.
                if (!$this->hasEvent($tenant, 'trial_expired')) {
                    SubscriptionEvent::create([
                        'tenant_id'   => $tenant->id,
                        'type'        => 'trial_expired',
                        'description' => 'Free trial ended — workspace limited pending upgrade.',
                    ]);
                    $expired++;
                }
                continue;
            }

            // Grace elapsed (or no grace): hard-suspend — full lockout.
            $tenant->update(['status' => 'suspended']);
            SubscriptionEvent::create([
                'tenant_id'   => $tenant->id,
                'type'        => 'suspended',
                'description' => "Suspended automatically — trial ended {$daysPast} day(s) ago with no plan chosen.",
            ]);
            $suspended++;
        }

        $this->info("Trials checked: {$reminded} reminded, {$expired} newly expired (walled), {$suspended} suspended.");

        return self::SUCCESS;
    }

    private function remindedToday(Tenant $tenant, \Illuminate\Support\Carbon $now): bool
    {
        return SubscriptionEvent::where('tenant_id', $tenant->id)
            ->where('type', 'trial_reminder')
            ->whereDate('created_at', $now->toDateString())
            ->exists();
    }

    private function hasEvent(Tenant $tenant, string $type): bool
    {
        return SubscriptionEvent::where('tenant_id', $tenant->id)->where('type', $type)->exists();
    }
}
