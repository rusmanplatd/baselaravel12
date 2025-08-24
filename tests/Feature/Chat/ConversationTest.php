<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_create_direct_conversation(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'type',
            'participants' => [
                '*' => [
                    'id',
                    'user_id',
                    'role',
                    'user' => ['id', 'name', 'email'],
                ],
            ],
        ]);

        $this->assertDatabaseHas('chat_conversations', [
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('chat_participants', [
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('chat_participants', [
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
    }

    public function test_user_can_create_group_conversation(): void
    {
        $thirdUser = User::factory()->create();

        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'name' => 'Test Group',
            'participants' => [$this->otherUser->id, $thirdUser->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'group']);
        $response->assertJsonFragment(['name' => 'Test Group']);

        $this->assertDatabaseHas('chat_conversations', [
            'type' => 'group',
            'name' => 'Test Group',
            'created_by' => $this->user->id,
        ]);

        // Should have 3 participants total
        $conversation = Conversation::latest()->first();
        $this->assertEquals(3, $conversation->participants()->count());
    }

    public function test_direct_conversation_requires_exactly_one_participant(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->id, User::factory()->create()->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Direct conversations must have exactly one other participant']);
    }

    public function test_user_can_list_conversations(): void
    {
        // Create some conversations
        $conversation1 = Conversation::factory()->create(['type' => 'direct']);
        $conversation2 = Conversation::factory()->create(['type' => 'group', 'name' => 'Test Group']);

        // Add user as participant
        $conversation1->participants()->create(['user_id' => $this->user->id, 'role' => 'owner']);
        $conversation2->participants()->create(['user_id' => $this->user->id, 'role' => 'member']);

        $response = $this->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_conversation_details(): void
    {
        $conversation = Conversation::factory()->create();
        $conversation->participants()->create(['user_id' => $this->user->id, 'role' => 'owner']);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $conversation->id]);
    }

    public function test_user_cannot_view_conversation_they_dont_participate_in(): void
    {
        $conversation = Conversation::factory()->create();
        // Don't add user as participant

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_group_conversation_name(): void
    {
        $conversation = Conversation::factory()->create(['type' => 'group']);
        $conversation->participants()->create(['user_id' => $this->user->id, 'role' => 'admin']);

        $response = $this->putJson("/api/v1/chat/conversations/{$conversation->id}", [
            'name' => 'Updated Group Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Group Name']);

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'name' => 'Updated Group Name',
        ]);
    }

    public function test_regular_member_cannot_update_conversation(): void
    {
        $conversation = Conversation::factory()->create(['type' => 'group']);
        $conversation->participants()->create(['user_id' => $this->user->id, 'role' => 'member']);

        $response = $this->putJson("/api/v1/chat/conversations/{$conversation->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_delete_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $conversation->participants()->create(['user_id' => $this->user->id, 'role' => 'owner']);

        $response = $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($conversation);
    }

    public function test_existing_direct_conversation_is_returned_instead_of_creating_duplicate(): void
    {
        // Create existing direct conversation
        $existingConversation = Conversation::factory()->create(['type' => 'direct']);
        $existingConversation->participants()->create(['user_id' => $this->user->id, 'role' => 'owner']);
        $existingConversation->participants()->create(['user_id' => $this->otherUser->id, 'role' => 'member']);

        // Try to create another direct conversation with same users
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->id],
        ]);

        $response->assertStatus(200); // Returns existing, not 201 created
        $response->assertJsonFragment(['id' => $existingConversation->id]);

        // Should still only have one conversation
        $this->assertEquals(1, Conversation::count());
    }

    public function test_user_can_create_direct_conversation_with_email(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->email],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'type',
            'participants' => [
                '*' => [
                    'id',
                    'user_id',
                    'role',
                    'user' => ['id', 'name', 'email'],
                ],
            ],
        ]);

        $this->assertDatabaseHas('chat_conversations', [
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('chat_participants', [
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
    }

    public function test_conversation_creation_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => ['nonexistent@example.com'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'User not found: nonexistent@example.com']);
    }
}
