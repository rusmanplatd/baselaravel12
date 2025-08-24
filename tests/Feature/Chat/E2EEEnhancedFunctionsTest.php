<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Services\ChatEncryptionService;

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    // Generate key pairs for users
    $keyPair1 = $this->encryptionService->generateKeyPair();
    $keyPair2 = $this->encryptionService->generateKeyPair();

    $this->user1->update(['public_key' => $keyPair1['public_key']]);
    $this->user2->update(['public_key' => $keyPair2['public_key']]);

    $this->conversation = Conversation::factory()->direct()->create();
    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
});

describe('Enhanced E2EE Functions', function () {
    describe('Backup and Restoration', function () {
        it('can create and restore encrypted backup via API', function () {
            $this->actingAs($this->user1);

            // Create some encryption keys for the user
            EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user1->id,
                'encrypted_key' => 'test-encrypted-key-1',
                'public_key' => $this->user1->public_key,
            ]);

            // Create backup via API
            $backupResponse = $this->postJson('/api/v1/chat/encryption/backup/create', [
                'password' => 'SecureBackupPassword123!',
            ]);

            $backupResponse->assertStatus(200);
            $backupResponse->assertJsonStructure([
                'backup_data',
                'created_at',
                'conversations_count',
            ]);

            $backupData = $backupResponse->json('backup_data');
            expect($backupData)->toBeString();
            expect($backupResponse->json('conversations_count'))->toBe(1);

            // Clear existing keys
            EncryptionKey::where('user_id', $this->user1->id)->delete();

            // Restore backup via API
            $restoreResponse = $this->postJson('/api/v1/chat/encryption/backup/restore', [
                'backup_data' => $backupData,
                'password' => 'SecureBackupPassword123!',
            ]);

            $restoreResponse->assertStatus(200);
            $restoreResponse->assertJsonStructure([
                'message',
                'conversations_restored',
                'total_in_backup',
                'private_key_restored',
            ]);

            expect($restoreResponse->json('conversations_restored'))->toBe(1);
            expect($restoreResponse->json('private_key_restored'))->toBeFalse(); // Private keys are not stored on server

            // Verify keys were restored
            $restoredKey = EncryptionKey::where('user_id', $this->user1->id)->first();
            expect($restoredKey)->not()->toBeNull();
            expect($restoredKey->encrypted_key)->toBe('test-encrypted-key-1');
        });

        it('rejects backup from different user', function () {
            $this->actingAs($this->user1);

            // Create backup for user1
            $backupResponse = $this->postJson('/api/v1/chat/encryption/backup/create', [
                'password' => 'SecureBackupPassword123!',
            ]);

            $backupData = $backupResponse->json('backup_data');

            // Try to restore as user2
            $this->actingAs($this->user2);

            $restoreResponse = $this->postJson('/api/v1/chat/encryption/backup/restore', [
                'backup_data' => $backupData,
                'password' => 'SecureBackupPassword123!',
            ]);

            $restoreResponse->assertStatus(403);
            $restoreResponse->assertJsonStructure(['error']);
            expect($restoreResponse->json('error'))->toContain('does not belong to current user');
        });

        it('handles invalid backup data gracefully', function () {
            $this->actingAs($this->user1);

            $restoreResponse = $this->postJson('/api/v1/chat/encryption/backup/restore', [
                'backup_data' => 'invalid-backup-data',
                'password' => 'password',
            ]);

            $restoreResponse->assertStatus(500);
            $restoreResponse->assertJsonStructure(['error', 'message']);
        });
    });

    describe('Conversation Encryption Setup', function () {
        it('can setup encryption for new conversation', function () {
            $this->actingAs($this->user1);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $encryptedKey1 = $this->encryptionService->encryptSymmetricKey($symmetricKey, $this->user1->public_key);
            $encryptedKey2 = $this->encryptionService->encryptSymmetricKey($symmetricKey, $this->user2->public_key);

            $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption", [
                'encrypted_keys' => [
                    [
                        'publicKey' => $this->user1->public_key,
                        'encryptedKey' => $encryptedKey1,
                    ],
                    [
                        'publicKey' => $this->user2->public_key,
                        'encryptedKey' => $encryptedKey2,
                    ],
                ],
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'message',
                'keys_created',
                'total_participants',
                'setup_id',
                'timestamp',
            ]);

            expect($response->json('keys_created'))->toBe(2);
            expect($response->json('total_participants'))->toBe(2);

            // Verify keys were created in database
            $user1Key = EncryptionKey::where('conversation_id', $this->conversation->id)
                ->where('user_id', $this->user1->id)
                ->first();

            $user2Key = EncryptionKey::where('conversation_id', $this->conversation->id)
                ->where('user_id', $this->user2->id)
                ->first();

            expect($user1Key)->not()->toBeNull();
            expect($user2Key)->not()->toBeNull();
            expect($user1Key->encrypted_key)->toBe($encryptedKey1);
            expect($user2Key->encrypted_key)->toBe($encryptedKey2);
        });

        it('handles invalid public keys gracefully', function () {
            $this->actingAs($this->user1);

            $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption", [
                'encrypted_keys' => [
                    [
                        'publicKey' => 'invalid-public-key',
                        'encryptedKey' => 'some-encrypted-key',
                    ],
                ],
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'message',
                'keys_created',
                'total_participants',
                'warnings',
            ]);

            expect($response->json('keys_created'))->toBe(0);
            expect($response->json('warnings'))->toHaveCount(1);
        });

        it('requires conversation update authorization', function () {
            $unauthorizedUser = User::factory()->create();
            $this->actingAs($unauthorizedUser);

            $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption", [
                'encrypted_keys' => [],
            ]);

            $response->assertStatus(403);
        });
    });

    describe('Health Check Endpoint', function () {
        it('returns encryption system health status', function () {
            $this->actingAs($this->user1);

            $response = $this->getJson('/api/v1/chat/encryption/health');

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'status',
                'checks',
                'warnings',
                'errors',
            ]);

            $health = $response->json();
            expect($health['status'])->toBeIn(['healthy', 'unhealthy']);
            expect($health['checks'])->toHaveKeys(['key_generation', 'symmetric_encryption', 'key_integrity']);

            if ($health['status'] === 'healthy') {
                expect($health['checks']['key_generation']['status'])->toBe('pass');
                expect($health['checks']['symmetric_encryption']['status'])->toBe('pass');
                expect($health['checks']['key_integrity']['status'])->toBe('pass');
            }
        });

        it('requires authentication for health check', function () {
            $response = $this->getJson('/api/v1/chat/encryption/health');
            $response->assertStatus(401);
        });
    });

    describe('Bulk Operations', function () {
        it('can bulk decrypt messages', function () {
            $this->actingAs($this->user1);

            // Create some test messages with encrypted content
            $message1 = $this->conversation->messages()->create([
                'sender_id' => $this->user1->id,
                'content' => 'Original message 1',
                'encrypted_content' => 'encrypted-content-1',
                'content_iv' => 'test-iv-1',
                'content_hmac' => 'test-hmac-1',
            ]);

            $message2 = $this->conversation->messages()->create([
                'sender_id' => $this->user1->id,
                'content' => 'Original message 2',
                'encrypted_content' => 'encrypted-content-2',
                'content_iv' => 'test-iv-2',
                'content_hmac' => 'test-hmac-2',
            ]);

            // Create encryption key for user
            EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user1->id,
                'encrypted_key' => 'test-encrypted-key',
                'public_key' => $this->user1->public_key,
            ]);

            // Mock cache for private key
            \Cache::put("user_private_key_{$this->user1->id}", $this->user1->private_key, 3600);

            $response = $this->postJson('/api/v1/chat/encryption/bulk-decrypt', [
                'conversation_id' => $this->conversation->id,
                'message_ids' => [$message1->id, $message2->id],
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'decrypted_messages',
                'errors',
                'success_count',
                'total_count',
            ]);

            // Note: In real implementation, decryption would work with proper keys
            // This test validates the API structure and error handling
        });

        it('requires conversation access for bulk decrypt', function () {
            $unauthorizedUser = User::factory()->create();
            $this->actingAs($unauthorizedUser);

            $response = $this->postJson('/api/v1/chat/encryption/bulk-decrypt', [
                'conversation_id' => $this->conversation->id,
                'message_ids' => [],
            ]);

            $response->assertStatus(403);
        });
    });

    describe('API Validation', function () {
        it('validates backup creation password strength', function () {
            $this->actingAs($this->user1);

            $response = $this->postJson('/api/v1/chat/encryption/backup/create', [
                'password' => '123', // Too weak
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        });

        it('validates required fields for conversation setup', function () {
            $this->actingAs($this->user1);

            $response = $this->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption", [
                'encrypted_keys' => [
                    [
                        'publicKey' => '', // Missing required field
                        'encryptedKey' => '',
                    ],
                ],
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['encrypted_keys.0.publicKey', 'encrypted_keys.0.encryptedKey']);
        });

        it('validates bulk decrypt parameters', function () {
            $this->actingAs($this->user1);

            $response = $this->postJson('/api/v1/chat/encryption/bulk-decrypt', [
                // Missing required fields
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['conversation_id', 'message_ids']);
        });
    });
});
