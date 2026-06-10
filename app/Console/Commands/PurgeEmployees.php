<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeEmployees extends Command
{
    protected $signature = 'employees:purge {--force : Actually delete (otherwise this is a dry run)}';

    protected $description = 'Delete ALL users/employees except Super Admins (dependent data cascades). Dry run unless --force.';

    public function handle(): int
    {
        // Keep every Super Admin; delete everyone else.
        $adminIds = User::whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            $this->error('Aborting: no Super Admin found. Refusing to delete every user.');
            return self::FAILURE;
        }

        $toDelete = User::whereNotIn('id', $adminIds)->count();

        $this->line('');
        $this->info('Super Admins that will be KEPT:');
        foreach (User::whereIn('id', $adminIds)->get(['id', 'email']) as $a) {
            $this->line("  • #{$a->id}  {$a->email}");
        }
        $this->warn("Users to be PERMANENTLY deleted (everyone else): {$toDelete}");
        $this->line('Their profiles, attendance, leave, invitations and all related data go with them.');
        $this->line('');

        if (!$this->option('force')) {
            $this->info('Dry run — nothing deleted. Re-run with --force to proceed:');
            $this->line('  php artisan employees:purge --force');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($adminIds) {
            // Directory mirror (employees.user_id is SET NULL on user delete).
            Employee::whereNotIn('user_id', $adminIds)->orWhereNull('user_id')->delete();
            // Non-admin users — dependents cascade via FK constraints.
            User::whereNotIn('id', $adminIds)->delete();
            // Any mirror rows orphaned by the cascade.
            Employee::whereNull('user_id')->delete();
        });

        $this->info('Done.');
        $this->line('  Remaining users:     ' . User::count());
        $this->line('  Remaining employees: ' . Employee::count());

        return self::SUCCESS;
    }
}
