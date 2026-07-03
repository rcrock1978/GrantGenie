<?php

declare(strict_types=1);

namespace Tests\Integration\Isolation;

use App\Domain\Identity\AccountId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * T034: Multi-tenant RLS isolation (SC-005).
 *
 * Creates two tenants (A, B) and proves that with RLS enabled, the app
 * session for tenant A cannot read tenant B's rows. This is the SC-005
 * gate that runs in CI on every PR.
 *
 * If RLS is missing or misconfigured, this suite will fail loudly because
 * the cross-tenant SELECTs will return rows instead of zero.
 */
final class RlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_tenant_read_returns_zero_rows(): void
    {
        $tenantA = Uuid::uuid4()->toString();
        $tenantB = Uuid::uuid4()->toString();

        // Seed an org_profile for each tenant. The app role is not a Postgres
        // superuser, so SET ROLE app_user is required.
        DB::statement("SET ROLE app_user");
        try {
            DB::table('accounts')->insert([
                ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'a-' . substr($tenantA, 0, 8), 'plan' => 'free', 'created_at' => now(), 'updated_at' => now()],
                ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'b-' . substr($tenantB, 0, 8), 'plan' => 'free', 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('org_profiles')->insert([
                ['id' => Uuid::uuid4()->toString(), 'account_id' => $tenantA, 'mission' => 'A mission', 'programs' => '[]', 'service_area_states' => '[]', 'ntee_codes' => '[]', 'organization_type' => '501c3', 'contact_email' => 'a@example.com', 'completed' => true, 'created_at' => now(), 'updated_at' => now()],
                ['id' => Uuid::uuid4()->toString(), 'account_id' => $tenantB, 'mission' => 'B mission', 'programs' => '[]', 'service_area_states' => '[]', 'ntee_codes' => '[]', 'organization_type' => '501c3', 'contact_email' => 'b@example.com', 'completed' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);

            // Act as tenant A
            DB::statement("SET app.current_tenant_id = ?", [$tenantA]);
            $aRows = DB::table('org_profiles')->count();

            // Act as tenant B
            DB::statement("SET app.current_tenant_id = ?", [$tenantB]);
            $bRows = DB::table('org_profiles')->count();

            $this->assertSame(1, $aRows, 'Tenant A should see only their own profile');
            $this->assertSame(1, $bRows, 'Tenant B should see only their own profile');

            // Cross-tenant read (tenant A session trying to find B's profile) returns zero
            DB::statement("SET app.current_tenant_id = ?", [$tenantA]);
            $cross = DB::table('org_profiles')->where('account_id', $tenantB)->count();
            $this->assertSame(0, $cross, 'SC-005 violation: cross-tenant read returned rows');
        } finally {
            DB::statement("RESET ROLE");
        }
    }
}
