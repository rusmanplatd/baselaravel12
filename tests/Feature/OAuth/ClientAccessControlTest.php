<?php

namespace Tests\Feature\OAuth;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPosition;
use App\Models\OrganizationPositionLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use App\Models\Auth\Permission;
use Tests\TestCase;

class ClientAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private Organization $organization;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->otherUser = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'organization_code' => 'TEST_ORG',
        ]);

        // Create organization membership for main user
        OrganizationMembership::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'membership_type' => 'employee',
            'status' => 'active',
        ]);

        // Give user OAuth client permissions
        setPermissionsTeamId($this->organization->id);
        $permission = Permission::firstOrCreate(
            ['name' => 'oauth.client.create', 'guard_name' => 'web'],
            ['created_by' => $this->user->id, 'updated_by' => $this->user->id]
        );
        $this->user->givePermissionTo('oauth.client.create');
    }

    public function test_client_allows_all_users_access()
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'all_users',
        ]);

        $this->assertTrue($client->userHasAccess($this->user));
        $this->assertTrue($client->userHasAccess($this->otherUser));
    }

    public function test_client_allows_organization_members_only()
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'organization_members',
        ]);

        $this->assertTrue($client->userHasAccess($this->user));
        $this->assertFalse($client->userHasAccess($this->otherUser));
    }

    public function test_client_custom_rules_specific_user_ids()
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'user_ids' => [$this->user->id],
            ],
        ]);

        $this->assertTrue($client->userHasAccess($this->user));
        $this->assertFalse($client->userHasAccess($this->otherUser));
    }

    public function test_client_custom_rules_email_domains()
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'email_domains' => ['example.com'],
            ],
        ]);

        $this->assertTrue($client->userHasAccess($this->user)); // test@example.com
        $this->assertTrue($client->userHasAccess($this->otherUser)); // other@example.com

        $thirdUser = User::factory()->create(['email' => 'user@different.com']);
        $this->assertFalse($client->userHasAccess($thirdUser));
    }

    public function test_client_custom_rules_organization_roles()
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'organization_roles' => ['manager'],
            ],
        ]);

        // Update user's membership to manager
        OrganizationMembership::where('user_id', $this->user->id)
            ->update(['membership_type' => 'manager']);

        $this->assertTrue($client->userHasAccess($this->user));
        $this->assertFalse($client->userHasAccess($this->otherUser));
    }

    public function test_client_custom_rules_position_levels()
    {
        // Create position level and position
        $positionLevel = OrganizationPositionLevel::factory()->create([
            'code' => 'manager',
            'name' => 'Manager',
        ]);

        $position = OrganizationPosition::factory()->create([
            'organization_id' => $this->organization->id,
            'organization_position_level_id' => $positionLevel->id,
            'title' => 'Department Manager',
        ]);

        // Update user's membership with position
        OrganizationMembership::where('user_id', $this->user->id)
            ->update(['organization_position_id' => $position->id]);

        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'position_levels' => ['manager'],
            ],
        ]);

        $this->assertTrue($client->userHasAccess($this->user));
        $this->assertFalse($client->userHasAccess($this->otherUser));
    }

    public function test_oauth_authorization_respects_access_control()
    {
        Auth::login($this->user);

        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'organization_members',
            'redirect_uris' => ['https://example.com/callback'],
        ]);

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
        ]));

        $response->assertOk(); // User has access

        // Test with user who doesn't have access
        Auth::login($this->otherUser);

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
        ]));

        $response->assertStatus(403); // User doesn't have access
    }

    public function test_all_users_client_allows_any_authenticated_user()
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'all_users',
            'redirect_uris' => ['https://example.com/callback'],
        ]);

        // Test with organization member
        Auth::login($this->user);
        $response = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
        ]));
        $response->assertOk();

        // Test with non-organization member
        Auth::login($this->otherUser);
        $response = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
        ]));
        $response->assertOk();
    }

    public function test_client_access_scope_description()
    {
        $allUsersClient = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'all_users',
        ]);

        $orgMembersClient = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'organization_members',
        ]);

        $customClient = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'user_ids' => [$this->user->id],
                'email_domains' => ['example.com'],
            ],
        ]);

        $this->assertEquals(
            'Any registered user can access this OAuth client',
            $allUsersClient->getAccessScopeDescription()
        );

        $this->assertStringContainsString(
            'Test Organization',
            $orgMembersClient->getAccessScopeDescription()
        );

        $this->assertStringContainsString(
            'custom rule',
            $customClient->getAccessScopeDescription()
        );
    }

    public function test_client_accessible_by_scope_query()
    {
        $allUsersClient = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'all_users',
        ]);

        $orgMembersClient = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_access_scope' => 'organization_members',
        ]);

        $otherOrgClient = Client::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'user_access_scope' => 'organization_members',
        ]);

        $accessibleClients = Client::accessibleBy($this->user)->get();

        $this->assertCount(2, $accessibleClients);
        $this->assertTrue($accessibleClients->contains('id', $allUsersClient->id));
        $this->assertTrue($accessibleClients->contains('id', $orgMembersClient->id));
        $this->assertFalse($accessibleClients->contains('id', $otherOrgClient->id));
    }

    public function test_client_validation_requires_user_access_scope()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/oauth/clients', [
            'name' => 'Test Client',
            'redirect_uris' => ['https://example.com/callback'],
            'organization_id' => $this->organization->id,
            'client_type' => 'public',
            // Missing user_access_scope - should fail validation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_access_scope']);
    }

    public function test_client_validation_for_custom_rules()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/oauth/clients', [
            'name' => 'Test Client',
            'redirect_uris' => ['https://example.com/callback'],
            'organization_id' => $this->organization->id,
            'client_type' => 'public',
            'user_access_scope' => 'custom',
            // Missing user_access_rules - should fail validation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_access_rules']);
    }

    public function test_client_validation_for_email_domains()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/oauth/clients', [
            'name' => 'Test Client',
            'redirect_uris' => ['https://example.com/callback'],
            'organization_id' => $this->organization->id,
            'client_type' => 'public',
            'user_access_scope' => 'custom',
            'user_access_rules' => [
                'email_domains' => ['invalid-domain'], // Invalid domain format
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_access_rules.email_domains.0']);
    }

    public function test_client_without_organization_is_rejected()
    {
        $client = Client::factory()->create([
            'organization_id' => null,  // No organization
            'user_access_scope' => 'all_users',
        ]);

        $this->assertFalse($client->userHasAccess($this->user));
        $this->assertFalse($client->userHasAccess($this->otherUser));
    }
}
