<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\ZktecoDevice;
use App\Models\ZktecoRawPunch;
use App\Models\ZktecoUnmapped;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * One screen that answers "is the biometric side working right now".
 *
 * Reading this off the server usually means digging through a web server
 * access log, whose path differs per host and which is awkward to reach from a
 * browser terminal. Everything needed is already in our own tables.
 */
class ZktecoStatus extends Command
{
    protected $signature = 'zkteco:status {--days=7 : How many days of punch history to summarise}';

    protected $description = 'Are the biometric devices talking, and are punches arriving?';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $this->line('');
        $this->line('  Server time: ' . now()->toDateTimeString() . '  (' . config('app.timezone') . ')');
        $this->line('');

        // ---- devices ----
        $devices = ZktecoDevice::withoutGlobalScopes()->orderBy('id')->get();

        if ($devices->isEmpty()) {
            $this->error('  No devices registered.');

            return self::SUCCESS;
        }

        $this->line('  DEVICES');
        foreach ($devices as $d) {
            $this->line('');
            $this->line("   {$d->name}  ·  SN " . ($d->serial_number ?: 'MISSING')
                . '  ·  ' . strtoupper($d->connection_mode ?? 'pull')
                . ($d->is_active ? '' : '  ·  INACTIVE'));

            // Talking to us at all?
            if (! $d->last_contact_at) {
                $this->line('     talking to us : <fg=red>never</>  (nothing has reached /iclock with this serial)');
            } else {
                $mins = $d->last_contact_at->diffInMinutes(now());
                $tag = $mins <= 15 ? '<fg=green>yes</>' : '<fg=red>NO</>';
                $this->line("     talking to us : {$tag} — last heard {$d->last_contact_at->diffForHumans()}"
                    . ' (' . $d->last_contact_at->toDateTimeString() . ')');
            }

            // Sending punches?
            $this->line('     sending data  : '
                . ($d->last_synced_at
                    ? $d->last_synced_at->diffForHumans() . ' (' . $d->last_synced_at->toDateTimeString() . ')'
                    : '<fg=red>never</>'));

            $this->line('     total punches : ' . number_format($d->total_records_synced));

            if (! $d->serial_number) {
                $this->line('     <fg=red>no serial number — a device posting without one is silently discarded</>');
            }

            if (! $d->timezone) {
                $this->line('     <fg=yellow>no timezone set — its clock times are read as the company timezone</>');
            } else {
                $this->line("     timezone      : {$d->timezone}");
            }

            if ($d->last_sync_message) {
                $this->line('     last message  : ' . $d->last_sync_message);
            }
        }

        // ---- punches arriving ----
        $this->line('');
        $this->line('  PUNCHES ARRIVING  (last ' . $days . ' days)');

        $since = Carbon::today()->subDays($days - 1);
        $rows = ZktecoRawPunch::withoutGlobalScopes()
            ->where('punched_at', '>=', $since)
            ->selectRaw('DATE(punched_at) as d, COUNT(*) as total,
                         SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as unmatched')
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        for ($i = 0; $i < $days; $i++) {
            $day = $since->copy()->addDays($i);
            $key = $day->toDateString();
            $row = $rows->get($key);
            $total = (int) ($row->total ?? 0);
            $unmatched = (int) ($row->unmatched ?? 0);

            $this->line(sprintf('   %s  %s  %s',
                $day->format('D d M'),
                $total === 0 ? '<fg=red>      nothing</>' : str_pad(number_format($total) . ' punch(es)', 13, ' ', STR_PAD_LEFT),
                $unmatched > 0 ? "<fg=yellow>{$unmatched} not matched to anybody</>" : ''
            ));
        }

        // ---- the waiting pile ----
        $unmapped = ZktecoUnmapped::where('is_resolved', false)->get();
        $this->line('');
        $this->line('  UNMATCHED DEVICE IDS: ' . $unmapped->count()
            . ($unmapped->isEmpty() ? '' : '  (their punches never become attendance)'));

        foreach ($unmapped->sortByDesc('punch_count')->take(10) as $u) {
            $this->line(sprintf('   id %-10s %4d punch(es)  last seen %s',
                $u->zkteco_uid, $u->punch_count, $u->last_seen));
        }
        if ($unmapped->count() > 10) {
            $this->line('   … and ' . ($unmapped->count() - 10) . ' more');
        }

        // ---- what reached the dashboard ----
        $today = AttendanceRecord::withoutGlobalScopes()
            ->whereDate('date', today())->where('source', 'zkteco')->count();
        $yesterday = AttendanceRecord::withoutGlobalScopes()
            ->whereDate('date', today()->subDay())->where('source', 'zkteco')->count();

        $this->line('');
        $this->line('  ON THE DASHBOARD');
        $this->line("   today     : {$today} attendance row(s) from the device");
        $this->line("   yesterday : {$yesterday}");

        $this->line('');
        $this->verdict($devices, $today);
        $this->line('');

        return self::SUCCESS;
    }

    /** Say the one thing worth doing next. */
    private function verdict($devices, int $today): void
    {
        $talking = $devices->filter(fn ($d) => $d->last_contact_at
            && $d->last_contact_at->diffInMinutes(now()) <= 15);

        if ($talking->isEmpty()) {
            $this->warn('  No device has reached this server in the last 15 minutes.');
            $this->line('  The punches are still stored on the device itself — they are not lost.');
            $this->line('  Look at: the device is powered and on the network, its server address');
            $this->line('  and port, and whether it is being redirected from http to https.');

            return;
        }

        if ($today === 0) {
            $this->warn('  A device is talking to us, but nothing has become attendance today.');
            $this->line('  Either no punches arrived (see the day list above), or they arrived');
            $this->line('  under a device id nobody is linked to (see unmatched ids above).');

            return;
        }

        $this->info('  Devices are talking and attendance is arriving.');
    }
}
