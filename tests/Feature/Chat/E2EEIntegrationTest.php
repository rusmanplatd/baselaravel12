<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\Participant;
use App\Models\User;
use App\Services\ChatEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;

    // Create test users
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    // Create test conversation
    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user1->id,
    ]);

    // Add participants
    Participant::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
    ]);

    Participant::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user2->id,
    ]);
});

describe('E2EE Message Integration', function () {
    it('creates encrypted messages with enhanced format', function () {
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        $content = 'This is a test encrypted message';

        $message = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            $content,
            $symmetricKey
        );

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->content_hmac)->not->toBeNull();

        // Verify encrypted content structure
        $encryptedData = json_decode($message->encrypted_content, true);
        expect($encryptedData)->toHaveKeys(['data', 'iv', 'hmac', 'auth_data', 'timestamp', 'nonce']);
    });

    it('decrypts messages correctly', function () {
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        $content = 'Test message for decryption';

        $message = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            $content,
            $symmetricKey
        );

        $decryptedContent = $message->decryptContent($symmetricKey);

        expect($decryptedContent)->toBe($content);
    });

    it('completes full e2ee message flow between users', function () {
        // Generate key pairs for both users
        $user1KeyPair = $this->encryptionService->generateKeyPair();
        $user2KeyPair = $this->encryptionService->generateKeyPair();

        // Generate conversation symmetric key
        $conversationKey = $this->encryptionService->generateSymmetricKey();

        // Create encryption keys for both users
        $user1EncKey = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user1->id,
            $conversationKey,
            $user1KeyPair['public_key']
        );

        $user2EncKey = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user2->id,
            $conversationKey,
            $user2KeyPair['public_key']
        );

        // User 1 sends a message
        $messageContent = 'Hello from User 1 with E2EE!';
        $message = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            $messageContent,
            $conversationKey
        );

        // User 2 decrypts their conversation key
        $user2ConversationKey = $user2EncKey->decryptSymmetricKey($user2KeyPair['private_key']);

        // User 2 reads the message
        $decryptedMessage = $message->decryptContent($user2ConversationKey);

        expect($decryptedMessage)->toBe($messageContent);
        expect($user2ConversationKey)->toBe($conversationKey);
    });

    it('handles voice message encryption', function () {
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        $content = 'Voice message content';
        $transcript = 'Hello, this is a voice message';
        $waveformData = '0,1,2,3,4,5,4,3,2,1,0';

        $message = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            $content,
            $symmetricKey,
            [
                'type' => 'voice',
                'voice_duration_seconds' => 5,
                'voice_transcript' => $transcript,
                'voice_waveform_data' => $waveformData,
            ]
        );

        expect($message->type)->toBe('voice');
        expect($message->voice_duration_seconds)->toBe(5);
        expect($message->encrypted_voice_transcript)->not->toBeNull();
        expect($message->encrypted_voice_waveform_data)->not->toBeNull();

        // Test decryption
        $decryptedTranscript = $message->decryptVoiceTranscript($symmetricKey);
        $decryptedWaveform = $message->decryptVoiceWaveformData($symmetricKey);

        expect($decryptedTranscript)->toBe($transcript);
        expect($decryptedWaveform)->toBe($waveformData);
    });

    it('handles file encryption through service', function () {
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        $fileContent = 'This is test file content with some binary data';

        $encrypted = $this->encryptionService->encryptFile($fileContent, $symmetricKey);
        $decrypted = $this->encryptionService->decryptFile(
            $encrypted['data'],
            $encrypted['iv'],
            $symmetricKey
        );

        expect($decrypted)->toBe($fileContent);
        expect($encrypted)->toHaveKeys(['data', 'iv', 'hash']);
    });
});

describe('E2EE Privacy Controls', function () {
    it('supports anonymous reactions', function () {
        // Create a simple encrypted message first
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        $message = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            'Test message',
            $symmetricKey
        );

        $reaction = \App\Models\Chat\MessageReaction::factory()->create([
            'message_id' => $message->id,
            'user_id' => $this->user1->id,
            'emoji' => '👍',
            'is_anonymous' => true,
        ]);

        expect($reaction->is_anonymous)->toBeTrue();
        expect($reaction->getDisplayUser())->toBeNull();
    });

    it('supports private read receipts', function () {
        // Create a simple encrypted message first
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        $message = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            'Test message',
            $symmetricKey
        );

        $receipt = \App\Models\Chat\MessageReadReceipt::factory()->create([
            'message_id' => $message->id,
            'user_id' => $this->user1->id,
            'is_private' => true,
        ]);

        expect($receipt->is_private)->toBeTrue();
        expect($receipt->getDisplayUser())->toBeNull();
        expect($receipt->canBeViewedBy($this->user1->id))->toBeTrue();
        expect($receipt->canBeViewedBy($this->user2->id))->toBeFalse();
    });
});

describe('E2EE Key Management', function () {
    it('verifies key integrity', function () {
        $keyPair = $this->encryptionService->generateKeyPair();

        $integrity = $this->encryptionService->verifyKeyIntegrity(
            $keyPair['public_key'],
            $keyPair['private_key']
        );

        expect($integrity)->toBeTrue();
    });

    it('handles key rotation workflow', function () {
        // Setup initial keys
        $oldSymmetricKey = $this->encryptionService->generateSymmetricKey();
        $user1KeyPair = $this->encryptionService->generateKeyPair();
        $user2KeyPair = $this->encryptionService->generateKeyPair();

        $oldKey1 = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user1->id,
            $oldSymmetricKey,
            $user1KeyPair['public_key']
        );

        $oldKey2 = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user2->id,
            $oldSymmetricKey,
            $user2KeyPair['public_key']
        );

        // Rotate keys - first deactivate old keys
        EncryptionKey::where('conversation_id', $this->conversation->id)
            ->update(['is_active' => false]);

        // Delete old keys to avoid unique constraint violation
        EncryptionKey::where('conversation_id', $this->conversation->id)->delete();

        $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->conversation->id);

        // Create new keys with fresh UUIDs
        $newKey1 = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user1->id,
            $newSymmetricKey,
            $user1KeyPair['public_key']
        );

        $newKey2 = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user2->id,
            $newSymmetricKey,
            $user2KeyPair['public_key']
        );

        expect($newSymmetricKey)->not->toBe($oldSymmetricKey);
        expect($newKey1->is_active)->toBeTrue();
        expect($newKey2->is_active)->toBeTrue();

        // Verify we have fresh keys
        expect($newKey1->id)->not->toBe($oldKey1->id);
        expect($newKey2->id)->not->toBe($oldKey2->id);
    });
});
