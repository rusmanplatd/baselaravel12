<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\OrganizationPositionLevelSeeder::class);

        // Clear tenant context to avoid interference between tests
        TenantService::clearTenant();

        // Clear any static state
        $reflection = new \ReflectionClass(TenantService::class);
        $currentTenantProperty = $reflection->getProperty('currentTenant');
        $currentTenantProperty->setValue(null);

        $currentUserProperty = $reflection->getProperty('currentUser');
        $currentUserProperty->setValue(null);
    }

    public function test_can_set_and_get_current_tenant(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Org',
            'organization_code' => 'TEST',
        ]);

        TenantService::setTenant($organization);

        $currentTenant = TenantService::getCurrentTenant();
        $this->assertEquals($organization->id, $currentTenant->id);
        $this->assertEquals('Test Org', $currentTenant->name);
        $this->assertTrue(TenantService::hasTenant());
    }

    public function test_can_clear_tenant(): void
    {
        $organization = Organization::factory()->create();
        TenantService::setTenant($organization);

        $this->assertTrue(TenantService::hasTenant());

        TenantService::clearTenant();

        $this->assertFalse(TenantService::hasTenant());
        $this->assertNull(TenantService::getCurrentTenant());
    }

    public function test_can_get_user_tenants(): void
    {
        $user = User::factory()->create();
        $org1 = Organization::factory()->create(['name' => 'Org 1']);
        $org2 = Organization::factory()->create(['name' => 'Org 2']);

        // Create active memberships
        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org1->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org2->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        $this->actingAs($user);

        $tenants = TenantService::getUserTenants($user);

        $this->assertCount(2, $tenants);
        $this->assertTrue($tenants->contains('id', $org1->id));
        $this->assertTrue($tenants->contains('id', $org2->id));
    }

    public function test_can_check_tenant_access(): void
    {
        $user = User::factory()->create();
        $accessibleOrg = Organization::factory()->create();
        $inaccessibleOrg = Organization::factory()->create();

        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $accessibleOrg->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        $this->actingAs($user);

        $this->assertTrue(TenantService::canAccessTenant($accessibleOrg, $user));
        $this->assertFalse(TenantService::canAccessTenant($inaccessibleOrg, $user));
    }

    public function test_can_get_default_tenant(): void
    {
        $user = User::factory()->create();
        $parentOrg = Organization::factory()->create(['level' => 0]);
        $childOrg = Organization::factory()->create(['level' => 1, 'parent_organization_id' => $parentOrg->id]);

        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $childOrg->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $parentOrg->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        $this->actingAs($user);

        $defaultTenant = TenantService::getDefaultTenant($user);

        // Should return the parent org (level 0) as default
        $this->assertEquals($parentOrg->id, $defaultTenant->id);
    }

    public function test_can_switch_tenant(): void
    {
        $user = User::factory()->create();
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org1->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        OrganizationMembership::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org2->id,
            'status' => 'active',
            'start_date' => now()->subDays(30),
        ]);

        $this->actingAs($user);

        // Switch to org1
        $success1 = TenantService::switchTenant($org1->id);
        $this->assertTrue($success1);
        $this->assertEquals($org1->id, TenantService::getTenantId());

        // Switch to org2
        $success2 = TenantService::switchTenant($org2->id);
        $this->assertTrue($success2);
        $this->assertEquals($org2->id, TenantService::getTenantId());

        // Try to switch to inaccessible org
        $inaccessibleOrg = Organization::factory()->create();
        $success3 = TenantService::switchTenant($inaccessibleOrg->id);
        $this->assertFalse($success3);
        $this->assertEquals($org2->id, TenantService::getTenantId()); // Should remain unchanged
    }
}
