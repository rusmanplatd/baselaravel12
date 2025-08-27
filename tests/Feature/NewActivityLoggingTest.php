<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_message_activity_is_logged_when_message_is_created()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'encrypted_content' => json_encode(['data' => 'test', 'iv' => 'test']),
            'content_hash' => 'test-hash',
            'type' => 'text',
            'status' => 'sent',
        ]);

        $activity = Activity::where('subject_id', $message->id)
            ->where('subject_type', Message::class)
            ->where('log_name', 'chat')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertStringContainsString('created', $activity->description);
    }

    public function test_oauth_client_activity_is_logged_when_client_is_created()
    {
        $organization = Organization::factory()->create();

        $client = Client::create([
            'name' => 'Test OAuth Client',
            'secret' => 'test-secret',
            'redirect_uris' => json_encode(['http://localhost']),
            'grant_types' => json_encode(['authorization_code']),
            'organization_id' => $organization->id,
            'user_access_scope' => 'organization_members',
            'revoked' => false,
        ]);

        $activity = Activity::where('subject_id', $client->id)
            ->where('subject_type', Client::class)
            ->where('log_name', 'oauth')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertStringContainsString('created', $activity->description);
    }

    public function test_organization_unit_activity_is_logged_when_unit_is_created()
    {
        $organization = Organization::factory()->create();

        $orgUnit = OrganizationUnit::create([
            'organization_id' => $organization->id,
            'unit_code' => 'TEST_UNIT',
            'name' => 'Test Unit',
            'unit_type' => 'department',
            'is_active' => true,
        ]);

        $activity = Activity::where('subject_id', $orgUnit->id)
            ->where('subject_type', OrganizationUnit::class)
            ->where('log_name', 'organization')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertStringContainsString('created', $activity->description);
    }

    public function test_organization_position_activity_is_logged_when_position_is_created()
    {
        $organization = Organization::factory()->create();
        $orgUnit = OrganizationUnit::create([
            'organization_id' => $organization->id,
            'unit_code' => 'TEST_UNIT',
            'name' => 'Test Unit',
            'unit_type' => 'department',
            'is_active' => true,
        ]);

        $position = OrganizationPosition::create([
            'organization_id' => $organization->id,
            'organization_unit_id' => $orgUnit->id,
            'position_code' => 'TEST_POS',
            'title' => 'Test Position',
            'is_active' => true,
            'max_incumbents' => 1,
        ]);

        $activity = Activity::where('subject_id', $position->id)
            ->where('subject_type', OrganizationPosition::class)
            ->where('log_name', 'organization')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertStringContainsString('created', $activity->description);
    }

    public function test_model_updates_are_logged_correctly()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $organization = Organization::factory()->create();

        // Create models
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'encrypted_content' => json_encode(['data' => 'test', 'iv' => 'test']),
            'content_hash' => 'test-hash',
            'type' => 'text',
            'status' => 'sent',
        ]);

        $client = Client::create([
            'name' => 'Test OAuth Client',
            'secret' => 'test-secret',
            'redirect_uris' => json_encode(['http://localhost']),
            'grant_types' => json_encode(['authorization_code']),
            'organization_id' => $organization->id,
            'user_access_scope' => 'organization_members',
            'revoked' => false,
        ]);

        // Clear existing activities to focus on updates
        Activity::where('event', 'created')->delete();

        // Update models
        $message->update(['status' => 'read']);
        $client->update(['name' => 'Updated OAuth Client']);

        $updateActivities = Activity::where('event', 'updated')
            ->whereIn('log_name', ['chat', 'oauth'])
            ->count();

        $this->assertEquals(2, $updateActivities);
    }

    public function test_activity_log_uses_correct_log_names()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $organization = Organization::factory()->create();

        // Create models that should use different log names
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'encrypted_content' => json_encode(['data' => 'test', 'iv' => 'test']),
            'content_hash' => 'test-hash',
            'type' => 'text',
            'status' => 'sent',
        ]);

        $client = Client::create([
            'name' => 'Test OAuth Client',
            'secret' => 'test-secret',
            'redirect_uris' => json_encode(['http://localhost']),
            'grant_types' => json_encode(['authorization_code']),
            'organization_id' => $organization->id,
            'user_access_scope' => 'organization_members',
            'revoked' => false,
        ]);

        $orgUnit = OrganizationUnit::create([
            'organization_id' => $organization->id,
            'unit_code' => 'TEST_UNIT',
            'name' => 'Test Unit',
            'unit_type' => 'department',
            'is_active' => true,
        ]);

        // Verify log names
        $this->assertTrue(Activity::where('log_name', 'chat')->exists());
        $this->assertTrue(Activity::where('log_name', 'oauth')->exists());
        $this->assertTrue(Activity::where('log_name', 'organization')->exists());
    }
}
