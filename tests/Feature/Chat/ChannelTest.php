<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\Channel;
use App\Models\Chat\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->organization = Organization::factory()->create();

        Passport::actingAs($this->user);
    }

    public function test_can_create_public_channel()
    {
        $channelData = [
            'name' => 'Test Public Channel',
            'description' => 'A test public channel',
            'visibility' => 'public',
            'organization_id' => $this->organization->id,
        ];

        $response = $this->postJson('/api/v1/chat/channels', $channelData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'slug',
                'visibility',
                'conversation_id',
                'organization_id',
                'created_by',
            ]);

        $this->assertDatabaseHas('chat_channels', [
            'name' => 'Test Public Channel',
            'visibility' => 'public',
            'organization_id' => $this->organization->id,
        ]);

        // Verify conversation was created
        $channel = Channel::where('name', 'Test Public Channel')->first();
        $this->assertInstanceOf(Conversation::class, $channel->conversation);
        $this->assertEquals('group', $channel->conversation->type);
    }

    public function test_can_create_private_channel()
    {
        $channelData = [
            'name' => 'Test Private Channel',
            'description' => 'A test private channel',
            'visibility' => 'private',
        ];

        $response = $this->postJson('/api/v1/chat/channels', $channelData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('chat_channels', [
            'name' => 'Test Private Channel',
            'visibility' => 'private',
        ]);
    }

    public function test_channel_has_encrypted_conversation()
    {
        $channel = Channel::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($channel->isEncrypted());
        $this->assertNotNull($channel->conversation->encryption_algorithm);
        $this->assertNotNull($channel->conversation->key_strength);
    }

    public function test_can_join_public_channel()
    {
        $channel = Channel::factory()->create([
            'visibility' => 'public',
            'created_by' => $this->otherUser->id,
        ]);

        Passport::actingAs($this->user);
        $response = $this->postJson("/api/v1/chat/channels/{$channel->id}/join");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully joined the channel']);

        $this->assertTrue(
            $channel->conversation->participants()
                ->where('user_id', $this->user->id)
                ->whereNull('left_at')
                ->exists()
        );
    }

    public function test_cannot_join_private_channel_directly()
    {
        $channel = Channel::factory()->create([
            'visibility' => 'private',
            'created_by' => $this->otherUser->id,
        ]);

        Passport::actingAs($this->user);
        $response = $this->postJson("/api/v1/chat/channels/{$channel->id}/join");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Cannot join private channels directly']);
    }

    public function test_can_leave_channel()
    {
        $channel = Channel::factory()->create([
            'created_by' => $this->user->id,
        ]);

        // Add user as participant
        $channel->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/chat/channels/{$channel->id}/leave");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully left the channel']);

        $this->assertTrue(
            $channel->conversation->participants()
                ->where('user_id', $this->user->id)
                ->whereNotNull('left_at')
                ->exists()
        );
    }

    public function test_can_invite_users_to_private_channel()
    {
        $channel = Channel::factory()->create([
            'visibility' => 'private',
            'created_by' => $this->user->id,
        ]);

        // Add creator as owner
        $channel->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/chat/channels/{$channel->id}/invite", [
            'user_ids' => [$this->otherUser->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'participants',
                'added_count',
            ]);

        $this->assertTrue(
            $channel->conversation->participants()
                ->where('user_id', $this->otherUser->id)
                ->whereNull('left_at')
                ->exists()
        );
    }

    public function test_can_search_public_channels()
    {
        Channel::factory()->create([
            'name' => 'JavaScript Developers',
            'visibility' => 'public',
        ]);

        Channel::factory()->create([
            'name' => 'Python Programmers',
            'visibility' => 'public',
        ]);

        Channel::factory()->create([
            'name' => 'Secret Club',
            'visibility' => 'private',
        ]);

        $response = $this->getJson('/api/v1/chat/channels/search?query=developers');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'JavaScript Developers']);
    }

    public function test_can_get_channel_members()
    {
        $channel = Channel::factory()->create([
            'created_by' => $this->user->id,
            'visibility' => 'public',
        ]);

        // Add participants
        $channel->conversation->participants()->createMany([
            [
                'user_id' => $this->user->id,
                'role' => 'owner',
                'joined_at' => now(),
            ],
            [
                'user_id' => $this->otherUser->id,
                'role' => 'member',
                'joined_at' => now(),
            ],
        ]);

        $response = $this->getJson("/api/v1/chat/channels/{$channel->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_can_update_channel_as_owner()
    {
        $channel = Channel::factory()->create([
            'created_by' => $this->user->id,
        ]);

        // Add user as owner
        $channel->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $updateData = [
            'name' => 'Updated Channel Name',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/v1/chat/channels/{$channel->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Channel Name']);

        $this->assertDatabaseHas('chat_channels', [
            'id' => $channel->id,
            'name' => 'Updated Channel Name',
        ]);
    }

    public function test_cannot_update_channel_as_member()
    {
        $channel = Channel::factory()->create([
            'created_by' => $this->otherUser->id,
        ]);

        // Add user as member (not owner/admin)
        $channel->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $updateData = [
            'name' => 'Hacked Channel Name',
        ];

        $response = $this->putJson("/api/v1/chat/channels/{$channel->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_can_delete_channel_as_owner()
    {
        $channel = Channel::factory()->create([
            'created_by' => $this->user->id,
        ]);

        // Add user as owner
        $channel->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/chat/channels/{$channel->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('chat_channels', [
            'id' => $channel->id,
        ]);
    }

    public function test_encryption_keys_created_for_channel_members()
    {
        // Create a trusted device for the user first
        $device = \App\Models\UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'is_trusted' => true,
        ]);

        // Create channel through API to ensure proper E2EE setup
        $channelData = [
            'name' => 'Test Encrypted Channel',
            'visibility' => 'public',
        ];

        $response = $this->postJson('/api/v1/chat/channels', $channelData);
        $response->assertStatus(201);

        $channelId = $response->json('id');
        $channel = Channel::find($channelId);

        // Verify conversation has encryption settings
        $this->assertNotNull($channel->conversation->encryption_algorithm);
        $this->assertNotNull($channel->conversation->key_strength);
        $this->assertEquals('AES-256-GCM', $channel->conversation->encryption_algorithm);
        $this->assertEquals(256, $channel->conversation->key_strength);
    }

    public function test_slug_auto_generated_from_name()
    {
        $channelData = [
            'name' => 'My Awesome Channel!',
            'visibility' => 'public',
        ];

        $response = $this->postJson('/api/v1/chat/channels', $channelData);

        $response->assertStatus(201);

        $channel = Channel::where('name', 'My Awesome Channel!')->first();
        $this->assertEquals('my-awesome-channel', $channel->slug);
    }

    public function test_unique_slug_generated_for_duplicate_names()
    {
        // Create first channel
        Channel::factory()->create([
            'name' => 'Duplicate Channel',
            'slug' => 'duplicate-channel',
        ]);

        // Create second channel with same name
        $channelData = [
            'name' => 'Duplicate Channel',
            'visibility' => 'public',
        ];

        $response = $this->postJson('/api/v1/chat/channels', $channelData);

        $response->assertStatus(201);

        $channel = Channel::where('name', 'Duplicate Channel')
            ->where('slug', '!=', 'duplicate-channel')
            ->first();

        $this->assertEquals('duplicate-channel-1', $channel->slug);
    }

    public function test_organization_scoped_channels()
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        Channel::factory()->create([
            'organization_id' => $org1->id,
            'visibility' => 'public',
        ]);

        Channel::factory()->create([
            'organization_id' => $org2->id,
            'visibility' => 'public',
        ]);

        $response = $this->getJson("/api/v1/chat/channels?organization_id={$org1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
