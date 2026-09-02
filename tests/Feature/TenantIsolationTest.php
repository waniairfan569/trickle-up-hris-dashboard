<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The single most important safety net for a multi-tenant HR product: proof
 * that one workspace can never read another's data. Guards the BelongsToTenant
 * trait + TenantScope against regressions.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenants;
    private Tenant $alpha;
    private Tenant $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants = app(TenantManager::class);
        $this->alpha = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active', 'plan' => 'scale']);
        $this->beta = Tenant::create(['name' => 'Beta', 'slug' => 'beta', 'status' => 'active', 'plan' => 'scale']);
    }

    private function departmentFor(Tenant $tenant, string $name): Department
    {
        $this->tenants->set($tenant);
        $company = Company::create(['name' => $name . ' Co']);

        return Department::create(['name' => $name, 'company_id' => $company->id]);
    }

    public function test_new_records_are_stamped_with_the_active_tenant(): void
    {
        $depA = $this->departmentFor($this->alpha, 'Eng A');
        $depB = $this->departmentFor($this->beta, 'Eng B');

        $this->assertSame($this->alpha->id, $depA->tenant_id);
        $this->assertSame($this->beta->id, $depB->tenant_id);
    }

    public function test_a_workspace_cannot_read_another_workspaces_rows(): void
    {
        $depA = $this->departmentFor($this->alpha, 'Eng A');
        $depB = $this->departmentFor($this->beta, 'Eng B');

        // Scoped to Alpha: sees its own, never Beta's.
        $this->tenants->set($this->alpha);
        $this->assertNotNull(Department::find($depA->id));
        $this->assertNull(Department::find($depB->id), 'Alpha must not read Beta\'s department');
        $this->assertFalse(Department::pluck('id')->contains($depB->id));

        // Scoped to Beta: the mirror image.
        $this->tenants->set($this->beta);
        $this->assertNotNull(Department::find($depB->id));
        $this->assertNull(Department::find($depA->id), 'Beta must not read Alpha\'s department');
    }

    public function test_counts_and_queries_are_scoped(): void
    {
        $this->departmentFor($this->alpha, 'A1');
        $this->departmentFor($this->alpha, 'A2');
        $this->departmentFor($this->beta, 'B1');

        $this->tenants->set($this->alpha);
        $this->assertSame(2, Department::count());

        $this->tenants->set($this->beta);
        $this->assertSame(1, Department::count());
    }

    public function test_without_global_scopes_is_the_only_cross_tenant_path(): void
    {
        $depB = $this->departmentFor($this->beta, 'Eng B');

        $this->tenants->set($this->alpha);
        $this->assertNull(Department::find($depB->id));
        $this->assertNotNull(
            Department::withoutGlobalScopes()->find($depB->id),
            'withoutGlobalScopes is the deliberate admin/console bypass'
        );
    }

    public function test_no_tenant_context_does_not_leak_a_scope(): void
    {
        $this->departmentFor($this->alpha, 'A1');
        $this->departmentFor($this->beta, 'B1');

        // No active tenant (console/boot) — the scope is a no-op, all rows visible.
        $this->tenants->set(null);
        $this->assertGreaterThanOrEqual(2, Department::count());
    }

    /** Every tenant-owned model must carry a tenant_id column, or its rows can't be scoped. */
    public function test_every_tenant_scoped_model_has_a_tenant_id_column(): void
    {
        $missing = [];
        $checked = 0;

        foreach (glob(app_path('Models') . '/*.php') as $file) {
            if (!str_contains(file_get_contents($file), 'BelongsToTenant')) {
                continue;
            }
            $class = 'App\\Models\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            try {
                $table = (new $class())->getTable();
            } catch (\Throwable $e) {
                continue;
            }
            $checked++;
            if (!Schema::hasColumn($table, 'tenant_id')) {
                $missing[] = "{$class} ({$table})";
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected to find tenant-scoped models');
        $this->assertSame([], $missing, 'These tenant-scoped models are missing a tenant_id column: ' . implode(', ', $missing));
    }
}
