<?php

namespace Tests\Feature\Chat;

use App\Events\MessageForwarded;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MessageInteractionsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $otherUser;
    private Conversation $conversation;
    private Conversation $targetConversation;
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        
        // Create conversations
        $this->conversation = Conversation::factory()->create();
        $this->targetConversation = Conversation::factory()->create();
        
        // Add users to conversations
        $this->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        
        $this->targetConversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        
        // Create a message
        $this->message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->otherUser->id,
            'encrypted_content' => base64_encode('Test message content'),
            'content_hash' => hash('sha256', 'Test message content'),
        ]);
    }

    public function test_user_can_add_reaction_to_message(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/{$this->message->id}/reactions", [
            'emoji' => '👍',
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $this->message->id,
            'user_id' => $this->user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_user_can_forward_message(): void
    {
        Event::fake();
        Passport::actingAs($this->user);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/{$this->message->id}/forward", [
            'target_conversation_ids' => [$this->targetConversation->id],
            'encrypted_content' => base64_encode('Forwarded content'),
            'content_hash' => hash('sha256', 'Forwarded content'),
        ]);

        $response->assertStatus(201);
        
        // Check that forwarded message was created
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->targetConversation->id,
            'sender_id' => $this->user->id,
            'forwarded_from_id' => $this->message->id,
            'original_conversation_id' => $this->conversation->id,
            'forward_count' => 1,
        ]);

        // Check that event was dispatched
        Event::assertDispatched(MessageForwarded::class);
    }
}
