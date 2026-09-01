<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Remove platform-operator access from a user. Use this to undo a company
 * super-admin who was wrongly promoted to operator — they stay a normal
 * company user, they just lose the Platform Console.
 */
class RevokeOperator extends Command
{
    protected $signature = 'operator:revoke {email : The operator account to un-flag}';

    protected $description = 'Remove platform-operator access from a user (they remain a normal company user).';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with {$email}.");

            return self::FAILURE;
        }

        if (! $user->is_operator) {
            $this->info("{$email} is not an operator — nothing to do.");

            return self::SUCCESS;
        }

        $user->is_operator = false;
        $user->operator_role = null;
        $user->save();

        $this->info("Operator access removed from {$email}.");
        $this->line('They remain a normal member of their company (their company role is unchanged).');

        return self::SUCCESS;
    }
}
