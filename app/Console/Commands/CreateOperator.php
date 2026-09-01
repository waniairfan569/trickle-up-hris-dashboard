<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create a dedicated, company-less platform operator account.
 * The platform owner must NOT be a company member — this keeps them separate.
 */
class CreateOperator extends Command
{
    protected $signature = 'operator:create
                            {email : Login email for the operator}
                            {--name=Platform Owner : Display name}
                            {--role=owner : owner or support}
                            {--password= : Password (a strong one is generated if omitted)}';

    protected $description = 'Create a dedicated, company-less platform operator account.';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");
            $this->line('Operators must be a SEPARATE, company-less account. Use a different email');
            $this->line('for the operator, or run  php artisan operator:revoke <email>  to un-flag a');
            $this->line('company user who was wrongly promoted.');

            return self::FAILURE;
        }

        $role = in_array($this->option('role'), ['owner', 'support'], true) ? $this->option('role') : 'owner';
        $password = $this->option('password') ?: Str::password(14);

        $parts = preg_split('/\s+/', trim($this->option('name')) ?: 'Platform Owner', 2);
        $first = $parts[0] ?? 'Platform';
        $last = $parts[1] ?? 'Owner';

        $op = new User();
        $op->first_name = $first;
        $op->last_name = $last;
        $op->email = $email;
        $op->password = Hash::make($password);
        $op->is_operator = true;
        $op->operator_role = $role;
        $op->account_status = 'active';
        $op->must_change_password = false;
        $op->email_verified_at = now();
        $op->save();

        // Belongs to no company / tenant (bypass the tenant-stamping model hook).
        DB::table('users')->where('id', $op->id)->update(['company_id' => null, 'tenant_id' => null]);

        $this->info("Operator created — {$email}  (role: {$role}, company-less).");
        $this->line('Password: ' . $password);
        $this->line('They can now sign in and land in the Platform Console at /operator.');

        return self::SUCCESS;
    }
}
