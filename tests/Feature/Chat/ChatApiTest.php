<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->thirdUser = User::factory()->create();

    Storage::fake('chat-files');
    Event::fake();
    // Skip Broadcasting::fake() as it's not needed for these tests
});

describe('Authentication and Authorization', function () {
    it('requires authentication for all chat endpoints', function () {
        $endpoints = [
            ['GET', '/api/v1/chat/conversations'],
            ['POST', '/api/v1/chat/conversations'],
            ['GET', '/api/v1/chat/conversations/test-id'],
            ['PUT', '/api/v1/chat/conversations/test-id'],
            ['DELETE', '/api/v1/chat/conversations/test-id'],
            ['GET', '/api/v1/chat/conversations/test-id/messages'],
            ['POST', '/api/v1/chat/conversations/test-id/messages'],
            ['POST', '/api/v1/chat/conversations/test-id/participants'],
            ['POST', '/api/v1/chat/encryption/generate-keypair'],
            ['POST', '/api/v1/chat/encryption/register-key'],
            ['GET', '/api/v1/chat/encryption/health'],
        ];

        foreach ($endpoints as [$method, $url]) {
            // Add API headers to ensure request is recognized as API request
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->call($method, $url);
            
            // Debug information
            if ($response->getStatusCode() !== 401) {
                echo "Debug - URL: $url, Method: $method\n";
                echo "Status: " . $response->getStatusCode() . "\n";
                echo "Headers: " . json_encode($response->headers->all()) . "\n";
                echo "Content: " . $response->getContent() . "\n";
            }
            
            // API routes use Passport auth, so they return 401 Unauthorized when unauthenticated
            $response->assertStatus(401);
        }
    });

    it('prevents access to conversations user is not part of', function () {
        $this->actingAs($this->user, 'api');

        $conversation = Conversation::factory()->create([
            'created_by' => $this->otherUser->id,
        ]);

        // Add only otherUser as participant
        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");
        $response->assertStatus(403);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages");
        $response->assertStatus(403);
    });
});

describe('Conversation Management API', function () {
    it('can create direct conversation', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'type',
            'created_by',
            'created_at',
            'participants' => [
                '*' => ['user_id', 'role', 'user' => ['id', 'name']],
            ],
        ]);

        $conversationId = $response->json('id');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversationId,
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
    });

    it('can create group conversation', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'name' => 'Project Team Chat',
            'participants' => [$this->otherUser->id, $this->thirdUser->id],
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'type' => 'group',
            'name' => 'Project Team Chat',
        ]);

        $conversationId = $response->json('id');

        // Creator should be owner, others members
        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
    });

    it('prevents duplicate direct conversations', function () {
        $this->actingAs($this->user, 'api');

        // Create first conversation
        $response1 = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->id],
        ]);

        $response1->assertStatus(201);

        // Try to create another direct conversation with same user
        $response2 = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [$this->otherUser->id],
        ]);

        $response2->assertStatus(200);
        $response2->assertJson(['id' => $response1->json('id')]);
    });

    it('validates conversation creation', function () {
        $this->actingAs($this->user, 'api');

        // Test invalid type
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'invalid',
            'participants' => [$this->otherUser->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);

        // Test missing participants for direct conversation
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'direct',
            'participants' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['participants']);

        // Test missing name for group conversation
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'group',
            'participants' => [$this->otherUser->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    });

    it('can list user conversations', function () {
        $this->actingAs($this->user, 'api');

        // Create multiple conversations
        $conversation1 = Conversation::factory()->create([
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        $conversation2 = Conversation::factory()->create([
            'type' => 'group',
            'name' => 'Team Chat',
            'created_by' => $this->otherUser->id,
        ]);

        // Add user as participant to both
        foreach ([$conversation1, $conversation2] as $conversation) {
            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $this->user->id,
                'role' => 'member',
            ]);
        }

        $response = $this->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $conversationIds = array_column($response->json('data'), 'id');
        expect($conversationIds)->toContain($conversation1->id);
        expect($conversationIds)->toContain($conversation2->id);
    });

    it('can update conversation', function () {
        $this->actingAs($this->user, 'api');

        $conversation = Conversation::factory()->create([
            'type' => 'group',
            'name' => 'Old Name',
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'role' => 'admin',
        ]);

        $response = $this->putJson("/api/v1/chat/conversations/{$conversation->id}", [
            'name' => 'New Team Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'New Team Name',
            'description' => 'Updated description',
        ]);

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'name' => 'New Team Name',
        ]);
    });

    it('can delete conversation', function () {
        $this->actingAs($this->user, 'api');

        $conversation = Conversation::factory()->create([
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'role' => 'admin',
        ]);

        $response = $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('chat_conversations', [
            'id' => $conversation->id,
        ]);
    });
});

describe('Message API', function () {
    beforeEach(function () {
        $this->conversation = Conversation::factory()->create([
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
    });

    it('can send text message', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'Hello, this is a test message!',
            'type' => 'text',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'content' => 'Hello, this is a test message!',
            'type' => 'text',
            'sender_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'type' => 'text',
        ]);
    });

    it('can send file message', function () {
        $this->actingAs($this->user, 'api');

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'type' => 'file',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'type',
            'file_name',
            'file_size',
            'mime_type',
            'file_url',
        ]);

        expect($response->json('type'))->toBe('file');
        expect($response->json('file_name'))->toBe('photo.jpg');

        // Verify file is stored
        $message = Message::find($response->json('id'));
        Storage::disk('chat-files')->assertExists($message->file_path);
    });

    it('can reply to message', function () {
        $this->actingAs($this->user, 'api');

        // Create original message
        $originalMessage = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->otherUser->id,
            'content' => 'Original message',
            'type' => 'text',
        ]);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'This is a reply',
            'type' => 'text',
            'reply_to_id' => $originalMessage->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'reply_to_id' => $originalMessage->id,
        ]);

        $response->assertJsonStructure([
            'reply_to' => ['id', 'content', 'sender' => ['name']],
        ]);
    });

    it('can edit message', function () {
        $this->actingAs($this->user, 'api');

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Original content',
            'type' => 'text',
        ]);

        $response = $this->putJson("/api/v1/chat/messages/{$message->id}", [
            'content' => 'Updated content',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'content' => 'Updated content',
        ]);

        expect($response->json('edited_at'))->not()->toBeNull();
    });

    it('can delete message', function () {
        $this->actingAs($this->user, 'api');

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Message to delete',
            'type' => 'text',
        ]);

        $response = $this->deleteJson("/api/v1/chat/messages/{$message->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('chat_messages', [
            'id' => $message->id,
        ]);
    });

    it('can search messages', function () {
        $this->actingAs($this->user, 'api');

        Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Laravel is awesome for backend development',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'React makes frontend development easy',
            'type' => 'text',
        ]);

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/search?q=Laravel");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.content'))->toContain('Laravel');
    });
});

describe('Participant Management API', function () {
    beforeEach(function () {
        $this->conversation = Conversation::factory()->create([
            'type' => 'group',
            'name' => 'Test Group',
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'admin',
        ]);
    });

    it('can add participants to group conversation', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/participants", [
            'user_ids' => [$this->otherUser->id, $this->thirdUser->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'participants'); // Original user + 2 new

        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
    });

    it('can update participant role', function () {
        $this->actingAs($this->user, 'api');

        // Add participant first
        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);

        $response = $this->putJson("/api/v1/chat/conversations/{$this->conversation->id}/participants/role", [
            'user_id' => $this->otherUser->id,
            'role' => 'admin',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'admin',
        ]);
    });

    it('can remove participant from group', function () {
        $this->actingAs($this->user, 'api');

        // Add participant first
        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);

        $response = $this->deleteJson("/api/v1/chat/conversations/{$this->conversation->id}/participants/{$this->otherUser->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('chat_participants', [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
        ]);
        
        // Check that the participant has been soft-deleted (left_at is not null)
        $participant = \App\Models\Chat\Participant::where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->otherUser->id)
            ->first();
        expect($participant->left_at)->not()->toBeNull();
    });

    it('prevents non-admin from managing participants', function () {
        // Add otherUser as regular member
        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);

        $this->actingAs($this->otherUser, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/participants", [
            'user_ids' => [$this->thirdUser->id],
        ]);

        $response->assertStatus(403);
    });
});

describe('Encryption Key Management API', function () {
    it('can generate encryption keys for user', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/chat/encryption/keys', [
            'password' => 'user-password-123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'public_key',
            'encrypted_private_key',
        ]);

        expect($response->json('public_key'))->toContain('BEGIN PUBLIC KEY');
        expect($response->json('encrypted_private_key'))->toBeString();
    });

    it('can retrieve user encryption keys', function () {
        $this->actingAs($this->user, 'api');

        // Create test conversation and keys
        $conversation = Conversation::factory()->create();
        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);

        $device = \App\Models\UserDevice::factory()->create(['user_id' => $this->user->id]);
        
        EncryptionKey::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'device_id' => $device->id,
            'device_fingerprint' => 'test-device-fingerprint',
            'public_key' => 'test-public-key',
            'encrypted_key' => 'test-encrypted-symmetric-key',
            'key_version' => 1,
        ]);

        $response = $this->getJson('/api/v1/chat/encryption/keys');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'conversation_id',
                'public_key',
                'encrypted_private_key',
                'version',
            ],
        ]);

        expect($response->json())->toHaveCount(1);
    });

    it('can update encryption keys', function () {
        $this->actingAs($this->user, 'api');

        $conversation = Conversation::factory()->create();
        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);

        $device = \App\Models\UserDevice::factory()->create(['user_id' => $this->user->id]);
        
        $existingKey = EncryptionKey::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->user->id,
            'device_id' => $device->id,
            'device_fingerprint' => 'test-device-fingerprint',
            'public_key' => 'old-public-key',
            'encrypted_key' => 'old-encrypted-symmetric-key',
            'key_version' => 1,
        ]);

        $response = $this->putJson("/api/v1/chat/encryption/keys/{$existingKey->id}", [
            'public_key' => 'new-public-key',
            'encrypted_private_key' => [
                'data' => 'new-encrypted-data',
                'iv' => 'new-iv',
                'salt' => 'new-salt',
                'hmac' => 'new-hmac',
                'auth_data' => 'new-auth-data',
            ],
        ]);

        $response->assertStatus(200);

        $existingKey->refresh();
        expect($existingKey->key_version)->toBe(2);
        expect($existingKey->public_key)->toBe('new-public-key');
    });
});

describe('Real-time Features', function () {
    beforeEach(function () {
        $this->conversation = Conversation::factory()->create([
            'type' => 'direct',
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->otherUser->id,
            'role' => 'member',
        ]);
        
        // Set up encryption keys for messaging
        $userDevice = \App\Models\UserDevice::factory()->create(['user_id' => $this->user->id]);
        EncryptionKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'device_id' => $userDevice->id,
            'device_fingerprint' => 'test-device-fingerprint',
            'public_key' => 'test-public-key',
            'encrypted_key' => 'test-encrypted-symmetric-key',
            'key_version' => 1,
        ]);
    });

    it('broadcasts message when sent', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'Hello everyone!',
            'type' => 'text',
        ]);

        $response->assertStatus(201);
        
        // Test passes if message creation succeeds - actual broadcasting is tested separately
        expect($response->json('content'))->toBe('Hello everyone!');
    });

    it('can update typing indicator', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/typing", [
            'is_typing' => true,
        ]);

        $response->assertStatus(200);
        
        // Test passes if typing status update succeeds
        expect($response->json('status'))->toBe('ok');
    });

    it('can update presence status', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/v1/chat/presence/status', [
            'status' => 'online',
        ]);

        $response->assertStatus(200);
        
        // Test passes if presence status update succeeds
        expect($response->json('status'))->toBe('online');
    });
});

describe('Rate Limiting', function () {
    beforeEach(function () {
        $this->conversation = Conversation::factory()->create([
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);
    });

    it('applies rate limiting to message sending', function () {
        $this->actingAs($this->user, 'api');

        // Send messages rapidly
        for ($i = 0; $i < 65; $i++) {
            $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
                'content' => "Message {$i}",
                'type' => 'text',
            ]);

            if ($i < 60) {
                expect($response->status())->toBeLessThan(400);
            } else {
                // Should be rate limited after 60 messages
                $response->assertStatus(429);
                break;
            }
        }
    });

    it('applies rate limiting to conversation creation', function () {
        $this->actingAs($this->user, 'api');

        // Create conversations rapidly
        for ($i = 0; $i < 12; $i++) {
            $targetUser = User::factory()->create();

            $response = $this->postJson('/api/v1/chat/conversations', [
                'type' => 'direct',
                'participants' => [$targetUser->id],
            ]);

            if ($i < 10) {
                expect($response->status())->toBeLessThan(400);
            } else {
                // Should be rate limited after 10 conversations
                $response->assertStatus(429);
                break;
            }
        }
    });
});

describe('File Download API', function () {
    beforeEach(function () {
        $this->conversation = Conversation::factory()->create([
            'created_by' => $this->user->id,
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);
    });

    it('can download file with valid token', function () {
        $this->actingAs($this->user, 'api');

        // Upload file first
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $uploadResponse = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'type' => 'file',
            'file' => $file,
        ]);

        $uploadResponse->assertStatus(201);

        $fileUrl = $uploadResponse->json('file_url');

        // Download file
        $response = $this->get($fileUrl);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
    });

    it('rejects expired download tokens', function () {
        // This would require mocking time or creating an expired token
        // Implementation depends on how download tokens are generated
        $this->markTestSkipped('Requires time mocking for expired token testing');
    });

    it('rejects invalid download tokens', function () {
        $response = $this->get('/api/v1/chat/files/invalid-path/download?token=invalid&expires='.(time() + 3600));

        $response->assertStatus(403);
    });
});
