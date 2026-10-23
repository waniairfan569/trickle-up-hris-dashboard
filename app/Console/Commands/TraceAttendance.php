<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\ZktecoDevice;
use App\Models\ZktecoRawPunch;
use App\Models\ZktecoUnmapped;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Follow one person's biometric punches from the device to the dashboard.
 *
 * A fingerprint device says "thank you" as soon as it recognises the finger —
 * that happens on the device, before anything is sent anywhere. So "it beeped
 * but nothing appeared" can break at four different places, and guessing which
 * costs more than looking. This prints each stage in order and says which one
 * is empty.
 */
class TraceAttendance extends Command
{
    protected $signature = 'attendance:trace
                            {employee : Email, employee id, or user id}
                            {--date= : The day to look at (default: today)}';

    protected $description = 'Trace why a biometric punch did or did not reach the dashboard.';

    public function handle(): int
    {
        $user = $this->findUser((string) $this->argument('employee'));

        if (! $user) {
            $this->error('No employee matches "' . $this->argument('employee') . '".');

            return self::FAILURE;
        }

        $tz = app(TimezoneService::class);
        $userTz = $tz->getEffectiveTimezone($user);
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'), $userTz)->startOfDay()
            : Carbon::now($userTz)->startOfDay();

        $this->line('');
        $this->info("  {$user->full_name}  <{$user->email}>");
        $this->line("  user id {$user->id} · tenant " . ($user->tenant_id ?? 'none')
            . " · timezone {$userTz} · looking at " . $date->toDateString());
        $this->line('  server time now: ' . now()->toDateTimeString() . ' (' . config('app.timezone') . ')');
        $this->line('');

        $ok = true;

        // ---- 1. is this person linked to a finger on the device at all ----
        $this->heading('1. Is this person linked to the device?');
        if (trim((string) $user->zkteco_uid) === '') {
            $this->fail1('No zkteco_uid on their record. The device has no idea who they are, '
                . 'so every punch they make is filed as unmapped.');
            $ok = false;
        } else {
            $this->good("zkteco_uid = {$user->zkteco_uid}"
                . ($user->zkteco_employee_id ? " · device employee id {$user->zkteco_employee_id}" : ''));

            $clash = User::withoutGlobalScopes()
                ->where('zkteco_uid', $user->zkteco_uid)
                ->where('id', '!=', $user->id)->get();
            if ($clash->isNotEmpty()) {
                $this->warn2('Shared with: ' . $clash->map(fn ($u) => "{$u->full_name} (#{$u->id})")->implode(', ')
                    . ' — whoever is found first takes the punch.');
            }
        }

        // ---- 2. did anything arrive from the device ----
        $this->heading('2. Did any punch arrive on that day?');
        $from = $date->copy()->setTimezone(config('app.timezone'));
        $to = $date->copy()->endOfDay()->setTimezone(config('app.timezone'));

        $punches = ZktecoRawPunch::withoutGlobalScopes()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (trim((string) $user->zkteco_uid) !== '') {
                    $q->orWhere('zkteco_uid', $user->zkteco_uid);
                }
            })
            ->whereBetween('punched_at', [$from, $to])
            ->orderBy('punched_at')->get();

        if ($punches->isEmpty()) {
            $this->fail1('Nothing arrived. The device accepted the finger but the punch never '
                . 'reached this server, or it arrived under a different id.');
            $ok = false;
        } else {
            $this->good($punches->count() . ' punch(es) stored:');
            foreach ($punches as $p) {
                $local = $tz->toUserTime($p->punched_at, $user);
                $this->line(sprintf('      %s local (%s stored) · state %s · %s · %s',
                    $local->format('H:i:s'),
                    $p->punched_at->format('H:i:s'),
                    $p->punch_state,
                    $p->user_id ? 'matched to this person' : 'NOT MATCHED to anybody',
                    $p->is_processed ? 'processed' : 'not processed'
                ));
            }

            if ($punches->whereNull('user_id')->isNotEmpty()) {
                $this->fail1('Some punches are not matched to anybody. They are waiting under '
                    . 'Attendance → Devices → Unmapped.');
                $ok = false;
            }
        }

        // ---- 3. is their id sitting in the unmapped pile ----
        $this->heading('3. Is their device id sitting unmapped?');
        $unmapped = ZktecoUnmapped::where('is_resolved', false)
            ->when(trim((string) $user->zkteco_uid) !== '',
                fn ($q) => $q->where('zkteco_uid', $user->zkteco_uid))
            ->get();

        if ($unmapped->isEmpty()) {
            $this->good('Nothing unmapped for this id.');
        } else {
            foreach ($unmapped as $u) {
                $this->fail1("Device id {$u->zkteco_uid} has {$u->punch_count} unmatched punch(es), "
                    . "last seen {$u->last_seen}. Map it on the Unmapped page.");
            }
            $ok = false;
        }

        // ---- 4. the row the dashboard actually reads ----
        $this->heading('4. The attendance row the dashboard shows');
        $record = AttendanceRecord::withoutGlobalScopes()
            ->where('user_id', $user->id)->whereDate('date', $date->toDateString())->first();

        if (! $record) {
            $this->fail1('No attendance row for ' . $date->toDateString() . ' — so the dashboard has '
                . 'nothing to show, whatever the device did.');
            $ok = false;
        } else {
            $this->good(sprintf('status=%s · in=%s · out=%s · source=%s · tenant=%s%s',
                $record->status,
                $record->clock_in ? $tz->toUserTime($record->clock_in, $user)->format('H:i') : '—',
                $record->clock_out ? $tz->toUserTime($record->clock_out, $user)->format('H:i') : '—',
                $record->source ?: '—',
                $record->tenant_id ?? 'NONE',
                $record->deleted_at ? ' · DELETED' : ''
            ));

            if ($record->deleted_at) {
                $this->fail1('This row is soft-deleted, so nothing will display it.');
                $ok = false;
            }

            if ($record->tenant_id !== $user->tenant_id) {
                $this->fail1("Row belongs to tenant {$record->tenant_id} but the employee is in "
                    . "{$user->tenant_id} — every screen filters by tenant, so it stays hidden.");
                $ok = false;
            }
        }

        // ---- 5. the device itself ----
        $this->heading('5. The device');
        $devices = ZktecoDevice::withoutGlobalScopes()->get();
        if ($devices->isEmpty()) {
            $this->fail1('No devices registered at all.');
            $ok = false;
        }
        $fallbackTz = optional(\App\Models\CompanyEntity::primary())->timezone
            ?: TimezoneService::FALLBACK_TIMEZONE;

        foreach ($devices as $d) {
            $silent = $d->last_synced_at && $d->last_synced_at->diffInHours(now()) >= 12;
            $this->line(sprintf('      %s (%s) · last contact %s%s',
                $d->name, $d->serial_number ?: 'no serial',
                $d->last_synced_at ? $d->last_synced_at->diffForHumans() : 'never',
                $silent ? '  <- quiet for a long time' : ''
            ));
            if ($d->last_sync_message) {
                $this->line('        ' . $d->last_sync_message);
            }

            // A device sends a wall-clock time with no timezone on it. Which
            // timezone we read it as is a setting — and getting it wrong moves
            // every punch by that many hours, which near midnight moves it to
            // the wrong day entirely.
            if (! $d->timezone) {
                $offset = Carbon::now($fallbackTz)->getOffset() - Carbon::now($userTz)->getOffset();
                $hours = round($offset / 3600, 1);

                if (abs($hours) >= 0.5) {
                    $this->fail1("No timezone set on this device, so its punches are read as "
                        . "{$fallbackTz}. The employee is in {$userTz}, which is {$hours}h apart — "
                        . "every punch lands {$hours}h out, and one near midnight lands on the wrong day.");
                    $ok = false;
                } else {
                    $this->warn2("No timezone set; punches are read as {$fallbackTz}, which happens "
                        . "to match the employee. Set it anyway before the clocks change.");
                }
            } else {
                $this->good("Device timezone {$d->timezone}.");
            }
        }

        $this->line('');
        $ok
            ? $this->info('  Everything lines up. If it still is not on screen, the screen is '
                . 'looking at a different day or a different person.')
            : $this->warn('  The first failing step above is where it stops.');
        $this->line('');

        return self::SUCCESS;
    }

    private function findUser(string $needle): ?User
    {
        $q = User::withoutGlobalScopes();

        return (is_numeric($needle) ? $q->where('id', $needle)->first() : null)
            ?: User::withoutGlobalScopes()->where('email', $needle)->first()
            ?: User::withoutGlobalScopes()->where('employee_id', $needle)->first()
            ?: User::withoutGlobalScopes()->where('zkteco_uid', $needle)->first();
    }

    private function heading(string $text): void
    {
        $this->line('  ' . $text);
    }

    private function good(string $text): void
    {
        $this->line('    <fg=green>OK</>   ' . $text);
    }

    private function fail1(string $text): void
    {
        $this->line('    <fg=red>STOP</> ' . $text);
    }

    private function warn2(string $text): void
    {
        $this->line('    <fg=yellow>NOTE</> ' . $text);
    }
}
