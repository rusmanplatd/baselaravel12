<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user->id,
    ]);

    // Create participants
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

    // Create encryption keys properly
    $this->encryptionService = new \App\Services\ChatEncryptionService;

    // Generate key pairs for both users
    $userKeyPair = $this->encryptionService->generateKeyPair();
    $otherUserKeyPair = $this->encryptionService->generateKeyPair();
    $this->symmetricKey = $this->encryptionService->generateSymmetricKey();

    // Update users' public keys
    $this->user->update(['public_key' => $userKeyPair['public_key']]);
    $this->otherUser->update(['public_key' => $otherUserKeyPair['public_key']]);

    // Cache private keys for testing
    $userCacheKey = 'user_private_key_'.$this->user->id;
    $otherUserCacheKey = 'user_private_key_'.$this->otherUser->id;

    cache()->put($userCacheKey, $this->encryptionService->encryptForStorage($userKeyPair['private_key']), now()->addHours(24));
    cache()->put($otherUserCacheKey, $this->encryptionService->encryptForStorage($otherUserKeyPair['private_key']), now()->addHours(24));

    // Create encryption keys for both users using the proper method
    EncryptionKey::createForUser(
        $this->conversation->id,
        $this->user->id,
        $this->symmetricKey,
        $userKeyPair['public_key']
    );

    EncryptionKey::createForUser(
        $this->conversation->id,
        $this->otherUser->id,
        $this->symmetricKey,
        $otherUserKeyPair['public_key']
    );

    // Helper function to create encrypted test messages
    $this->createTestMessage = function ($content, $senderId = null, $type = 'text', $additionalData = []) {
        $senderId = $senderId ?? $this->user->id;

        return Message::createEncrypted(
            $this->conversation->id,
            $senderId,
            $content,
            $this->symmetricKey,
            array_merge(['type' => $type], $additionalData)
        );
    };
});

describe('Message Creation', function () {
    it('can create a text message', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'Hello, this is a test message',
            'type' => 'text',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'content',
            'type',
            'sender_id',
            'created_at',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'type' => 'text',
        ]);
    });

    it('can create a reply message', function () {
        $this->actingAs($this->user, 'api');

        // Create original message
        $originalMessage = ($this->createTestMessage)('Original message', $this->otherUser->id);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'This is a reply',
            'type' => 'text',
            'reply_to_id' => $originalMessage->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'reply_to_id' => $originalMessage->id,
        ]);
    });

    it('validates message content', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => '',
            'type' => 'text',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    });

    it('validates message type', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'Test message',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    });

    it('prevents creating messages in conversations user is not part of', function () {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'Unauthorized message',
            'type' => 'text',
        ]);

        $response->assertStatus(403);
    });
});

describe('Message Retrieval', function () {
    beforeEach(function () {
        // Create test messages using encrypted format
        for ($i = 1; $i <= 25; $i++) {
            $senderId = ($i % 2 === 0) ? $this->user->id : $this->otherUser->id;
            $content = "Test message {$i}";

            // Create encrypted message
            $message = ($this->createTestMessage)($content, $senderId, 'text');

            // Update the created_at timestamp after creation
            $message->update(['created_at' => now()->subMinutes(25 - $i)]);
        }
    });

    it('can retrieve messages with pagination', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'content',
                    'type',
                    'sender_id',
                    'created_at',
                    'sender' => ['id', 'name'],
                ],
            ],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        expect($response->json('data'))->toHaveCount(20); // Default page size
    });

    it('can retrieve messages with custom page size', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages?per_page=10");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(10);
        expect($response->json('meta.per_page'))->toBe(10);
    });

    it('can retrieve older messages', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages?page=2");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(5); // Remaining messages
        expect($response->json('meta.current_page'))->toBe(2);
    });

    it('includes sender information', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages");

        $response->assertStatus(200);

        $firstMessage = $response->json('data.0');
        expect($firstMessage)->toHaveKey('sender');
        expect($firstMessage['sender'])->toHaveKeys(['id', 'name']);
    });

    it('orders messages by creation date descending', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages");

        $response->assertStatus(200);

        $messages = $response->json('data');
        $timestamps = array_column($messages, 'created_at');

        // Verify messages are in descending order (newest first)
        expect($timestamps)->toBe(array_values(array_sort($timestamps, function ($value) {
            return -strtotime($value);
        })));
    });
});

describe('Message Updates', function () {
    beforeEach(function () {
        $this->message = ($this->createTestMessage)('Original message');
    });

    it('can update own message', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->putJson("/api/v1/chat/messages/{$this->message->id}", [
            'content' => 'Updated message content',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->message->id,
            'content' => 'Updated message content',
            'edited_at' => now()->toISOString(),
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'id' => $this->message->id,
            'content' => 'Updated message content',
        ]);
    });

    it('cannot update other users messages', function () {
        $this->actingAs($this->otherUser);

        $response = $this->putJson("/api/v1/chat/messages/{$this->message->id}", [
            'content' => 'Unauthorized update',
        ]);

        $response->assertStatus(403);
    });

    it('validates updated content', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->putJson("/api/v1/chat/messages/{$this->message->id}", [
            'content' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    });

    it('tracks edit timestamp', function () {
        $this->actingAs($this->user, 'api');

        $this->putJson("/api/v1/chat/messages/{$this->message->id}", [
            'content' => 'Updated content',
        ]);

        $this->message->refresh();
        expect($this->message->edited_at)->not()->toBeNull();
    });
});

describe('Message Deletion', function () {
    beforeEach(function () {
        $this->message = ($this->createTestMessage)('Message to delete');
    });

    it('can soft delete own message', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->deleteJson("/api/v1/chat/messages/{$this->message->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('chat_messages', [
            'id' => $this->message->id,
        ]);
    });

    it('cannot delete other users messages', function () {
        $this->actingAs($this->otherUser);

        $response = $this->deleteJson("/api/v1/chat/messages/{$this->message->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('chat_messages', [
            'id' => $this->message->id,
            'deleted_at' => null,
        ]);
    });

    it('admin can delete any message in conversation', function () {
        // Make user admin of the conversation
        $this->conversation->participants()
            ->where('user_id', $this->user->id)
            ->update(['role' => 'admin']);

        // Create message by other user
        $otherMessage = ($this->createTestMessage)('Message by other user', $this->otherUser->id);

        $this->actingAs($this->user, 'api');

        $response = $this->deleteJson("/api/v1/chat/messages/{$otherMessage->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('chat_messages', [
            'id' => $otherMessage->id,
        ]);
    });
});

describe('File Messages', function () {
    beforeEach(function () {
        Storage::fake('chat-files');
    });

    it('can create file message', function () {
        $this->actingAs($this->user, 'api');

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

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

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'type' => 'file',
        ]);
    });

    it('validates file size', function () {
        $this->actingAs($this->user, 'api');

        // Create file larger than 100MB
        $largeFile = UploadedFile::fake()->create('large.pdf', 101 * 1024); // 101MB

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'type' => 'file',
            'file' => $largeFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    });

    it('validates file type', function () {
        $this->actingAs($this->user, 'api');

        $invalidFile = UploadedFile::fake()->create('script.exe', 1024);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'type' => 'file',
            'file' => $invalidFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    });

    it('encrypts uploaded files', function () {
        $this->actingAs($this->user, 'api');

        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'type' => 'file',
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $message = Message::find($response->json('id'));

        // Verify file is stored encrypted
        expect($message->file_path)->not()->toBeNull();
        Storage::disk('chat-files')->assertExists($message->file_path);

        // File content should be encrypted (different from original)
        $storedContent = Storage::disk('chat-files')->get($message->file_path);
        $originalContent = $file->getContent();

        expect($storedContent)->not()->toBe($originalContent);
    });
});

describe('Message Search', function () {
    beforeEach(function () {
        ($this->createTestMessage)('This is about Laravel development');
        ($this->createTestMessage)('React components are great', $this->otherUser->id);
        ($this->createTestMessage)('Database optimization tips');
    });

    it('can search messages by content', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/search?q=Laravel");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.content'))->toContain('Laravel');
    });

    it('search is case insensitive', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/search?q=react");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.content'))->toContain('React');
    });

    it('returns empty results for no matches', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/search?q=nonexistent");

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(0);
    });

    it('validates search query', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/v1/chat/conversations/{$this->conversation->id}/messages/search?q=ab");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    });
});

describe('Rate Limiting', function () {
    it('applies rate limiting to message creation', function () {
        $this->actingAs($this->user, 'api');

        // Send messages up to rate limit
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
                'content' => "Message {$i}",
                'type' => 'text',
            ]);

            if ($i < 59) {
                $response->assertStatus(201);
            } else {
                // 60th message should be rate limited
                $response->assertStatus(429);
            }
        }
    });
});

describe('Error Handling', function () {
    it('handles conversation not found', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson('/api/conversations/non-existent-id/messages', [
            'content' => 'Test message',
            'type' => 'text',
        ]);

        $response->assertStatus(404);
    });

    it('handles message not found', function () {
        $this->actingAs($this->user, 'api');

        $response = $this->putJson('/api/messages/non-existent-id', [
            'content' => 'Updated content',
        ]);

        $response->assertStatus(404);
    });

    it('handles database errors gracefully', function () {
        $this->actingAs($this->user, 'api');

        // Mock a database error
        $this->mock(Message::class, function ($mock) {
            $mock->shouldReceive('create')
                ->andThrow(new \Exception('Database error'));
        });

        $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/messages", [
            'content' => 'Test message',
            'type' => 'text',
        ]);

        $response->assertStatus(500);
    });
});
