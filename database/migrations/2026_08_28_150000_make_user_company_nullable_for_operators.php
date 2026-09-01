<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform operators (is_operator) belong to no company, so company_id must be
     * allowed to be NULL. Existing company members keep their company_id.
     *
     * users.company_id has a foreign key to companies. MariaDB (and some MySQL
     * configs) refuse to ALTER a column that is part of a foreign key, so we drop
     * the FK, change the column, then re-add the FK. All steps are idempotent so a
     * partially-applied or re-run migration is safe.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'company_id')) {
            return;
        }

        $fkName = $this->companyForeignKey();
        if ($fkName) {
            DB::statement("ALTER TABLE `users` DROP FOREIGN KEY `{$fkName}`");
        }

        // Raw MODIFY works regardless of dbal / driver and now that the FK is gone.
        DB::statement('ALTER TABLE `users` MODIFY `company_id` BIGINT UNSIGNED NULL');

        // Re-create the FK (nullable FKs are fine) if it isn't there any more.
        if (! $this->companyForeignKey()) {
            DB::statement(
                'ALTER TABLE `users` ADD CONSTRAINT `users_company_id_foreign` '
                . 'FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE'
            );
        }
    }

    public function down(): void
    {
        // Leaving it nullable on rollback is harmless; no data change.
    }

    /** The name of the FK on users.company_id, or null if none. */
    private function companyForeignKey(): ?string
    {
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'company_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        return $row->CONSTRAINT_NAME ?? null;
    }
};
