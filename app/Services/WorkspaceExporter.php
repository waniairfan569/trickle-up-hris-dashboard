<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * GDPR data portability + erasure for a single workspace (tenant).
 *
 * export() gathers every tenant-owned row (all BelongsToTenant models, scoped
 * to the tenant) into a structured array with sensitive fields redacted.
 * purge() permanently deletes all of that workspace's data — used for
 * off-boarding and the right-to-erasure. Both operate on ONE tenant only.
 */
class WorkspaceExporter
{
    /** Columns never included in an export (secrets, not "personal data"). */
    private const REDACT = [
        'users' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
        'code_requests' => ['code_provided', 'code'],
    ];

    /** [class => table] for every model that belongs to a tenant. Memoised. */
    private ?array $models = null;

    public function tenantModels(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $models = [];
        foreach (glob(app_path('Models') . '/*.php') as $file) {
            if (!str_contains(file_get_contents($file), 'BelongsToTenant')) {
                continue;
            }
            $class = 'App\\Models\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            try {
                $models[$class] = (new $class())->getTable();
            } catch (\Throwable $e) {
                // skip models that can't be instantiated bare
            }
        }

        return $this->models = $models;
    }

    /** The full data export for a workspace as a nested array. */
    public function export(Tenant $tenant): array
    {
        $data = [];
        foreach ($this->tenantModels() as $class => $table) {
            $rows = DB::table($table)->where('tenant_id', $tenant->id)->get()
                ->map(fn ($row) => $this->redact($table, (array) $row))
                ->all();
            if ($rows) {
                $data[$table] = $rows;
            }
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'workspace' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'created_at' => (string) $tenant->created_at,
            ],
            'record_counts' => array_map('count', $data),
            'data' => $data,
        ];
    }

    public function toJson(Tenant $tenant): string
    {
        return json_encode($this->export($tenant), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Permanently delete every row owned by this workspace, plus the workspace
     * itself. FK checks are disabled inside the transaction so table order
     * doesn't matter. Returns the number of rows deleted per table.
     */
    public function purge(Tenant $tenant): array
    {
        $tables = array_values($this->tenantModels());
        // users + companies are tenant-owned too (users IS a tenant model, but
        // ensure companies are covered even if the model list misses one).
        foreach (['users', 'companies'] as $t) {
            if (!in_array($t, $tables, true)) {
                $tables[] = $t;
            }
        }

        $deleted = [];

        DB::transaction(function () use ($tenant, $tables, &$deleted) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($tables as $table) {
                    $count = DB::table($table)->where('tenant_id', $tenant->id)->delete();
                    if ($count) {
                        $deleted[$table] = $count;
                    }
                }
                $deleted['tenants'] = DB::table('tenants')->where('id', $tenant->id)->delete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        return $deleted;
    }

    private function redact(string $table, array $row): array
    {
        foreach (self::REDACT[$table] ?? [] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = '[redacted]';
            }
        }

        return $row;
    }
}
