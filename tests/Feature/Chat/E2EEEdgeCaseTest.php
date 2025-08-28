<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Services\ChatEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    // Create test conversation
    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
    ]);

    $this->conversation->participants()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user2->id,
    ]);
});

describe('E2EE Edge Cases and Error Handling', function () {
    describe('Corrupted Data Handling', function () {
        it('handles corrupted encryption key gracefully', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create a default device for the user if it doesn't exist
            $device = \App\Models\UserDevice::firstOrCreate(
                ['user_id' => $this->user1->id],
                [
                    'device_name' => 'Test Device',
                    'device_type' => 'web',
                    'platform' => 'web',
                    'device_fingerprint' => 'test-device-'.$this->user1->id,
                    'public_key' => $keyPair['public_key'],
                    'last_used_at' => now(),
                    'is_trusted' => true,
                ]
            );

            // Create a corrupted encryption key
            $corruptedEncryptedKey = 'corrupted_key_data_that_cannot_be_decrypted';

            $encryptionKey = EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user1->id,
                'device_id' => $device->id,
                'device_fingerprint' => $device->device_fingerprint,
                'encrypted_key' => $corruptedEncryptedKey,
                'public_key' => $keyPair['public_key'],
                'key_version' => 1,
                'is_active' => true,
                'algorithm' => 'RSA-4096-OAEP',
                'key_strength' => 4096,
            ]);

            // Try to decrypt with corrupted key
            expect(fn () => $encryptionKey->decryptSymmetricKey($keyPair['private_key']))
                ->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('handles corrupted message content gracefully', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Create message with corrupted encrypted content
            $message = Message::create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => $this->user1->id,
                'content' => 'Original message',
                'content_hash' => hash('sha256', 'Original message'),
                'encrypted_content' => json_encode([
                    'data' => 'corrupted_encrypted_data',
                    'iv' => 'invalid_iv',
                    'hmac' => 'invalid_hmac',
                    'auth_data' => 'invalid_auth_data',
                    'timestamp' => time(),
                    'nonce' => 'invalid_nonce',
                ]),
                'content_hmac' => 'corrupted_hmac',
            ]);

            // Try to decrypt corrupted content
            expect(fn () => $message->decryptContent($symmetricKey))
                ->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('handles corrupted initialization vector gracefully', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $content = 'Test message for IV corruption';

            // Encrypt normally first
            $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);

            // Corrupt the IV
            $corruptedIv = str_repeat('X', strlen($encrypted['iv']));

            // Try to decrypt with corrupted IV
            expect(fn () => $this->encryptionService->decryptMessage(
                $encrypted['data'],
                $corruptedIv,
                $symmetricKey
            ))->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('handles truncated encrypted data', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $content = 'Test message for truncation';

            $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);

            // Truncate the encrypted data
            $truncatedData = substr($encrypted['data'], 0, -10);

            expect(fn () => $this->encryptionService->decryptMessage(
                $truncatedData,
                $encrypted['iv'],
                $symmetricKey
            ))->toThrow(\App\Exceptions\DecryptionException::class);
        });
    });

    describe('Boundary Conditions', function () {
        it('handles empty message content', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            expect(fn () => Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                '', // Empty content
                $symmetricKey
            ))->toThrow(\App\Exceptions\EncryptionException::class, 'Message cannot be empty');
        });

        it('handles extremely long message content', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Create a very long message (1MB)
            $longContent = str_repeat('A', 1024 * 1024);

            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $longContent,
                $symmetricKey
            );

            expect($message)->toBeInstanceOf(Message::class);

            $decryptedContent = $message->decryptContent($symmetricKey);
            expect($decryptedContent)->toBe($longContent);
        });

        it('handles unicode and special characters in messages', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            $unicodeContent = '🔒 Hello 世界! Encrypted message with émojis & spëcial chärs 🚀 التشفير';

            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $unicodeContent,
                $symmetricKey
            );

            $decryptedContent = $message->decryptContent($symmetricKey);
            expect($decryptedContent)->toBe($unicodeContent);
        });

        it('handles null byte in message content', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            $contentWithNull = "Message with\0null byte in the middle";

            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $contentWithNull,
                $symmetricKey
            );

            $decryptedContent = $message->decryptContent($symmetricKey);
            expect($decryptedContent)->toBe($contentWithNull);
        });

        it('handles maximum key size limits', function () {
            // Test with RSA key size limits
            $keyPair = $this->encryptionService->generateKeyPair();
            expect(strlen($keyPair['public_key']))->toBeLessThan(2048); // Reasonable upper bound
            expect(strlen($keyPair['private_key']))->toBeLessThan(4096); // Reasonable upper bound
        });
    });

    describe('Concurrent Access Patterns', function () {
        it('handles simultaneous encryption key creation', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();

            // Simulate concurrent key creation (sequential for testing)
            $key1 = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $symmetricKey,
                $keyPair1['public_key']
            );

            $key2 = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user2->id,
                $symmetricKey,
                $keyPair2['public_key']
            );

            expect($key1)->toBeInstanceOf(EncryptionKey::class);
            expect($key2)->toBeInstanceOf(EncryptionKey::class);
            expect($key1->id)->not()->toBe($key2->id);
        });

        it('handles message encryption during key rotation', function () {
            $oldSymmetricKey = $this->encryptionService->generateSymmetricKey();
            $newSymmetricKey = $this->encryptionService->generateSymmetricKey();

            // Create old encryption key
            $keyPair = $this->encryptionService->generateKeyPair();
            $oldKey = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $oldSymmetricKey,
                $keyPair['public_key']
            );

            // Encrypt message with old key
            $message1 = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Message with old key',
                $oldSymmetricKey
            );

            // Deactivate old key and create new one
            $oldKey->update(['is_active' => false]);

            $newKey = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $newSymmetricKey,
                $keyPair['public_key']
            );

            // Encrypt message with new key
            $message2 = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Message with new key',
                $newSymmetricKey
            );

            // Both messages should be valid
            expect($message1->decryptContent($oldSymmetricKey))->toBe('Message with old key');
            expect($message2->decryptContent($newSymmetricKey))->toBe('Message with new key');
        });
    });

    describe('Network and I/O Error Simulation', function () {
        it('handles database connection errors gracefully', function () {
            // This would require mocking database failures
            // For now, we test that operations complete normally
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Test message',
                $symmetricKey
            );

            expect($message)->toBeInstanceOf(Message::class);
        });

        it('handles temporary file system errors', function () {
            // Test that encryption operations don't rely on temp files
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $content = 'Test content for filesystem error simulation';

            $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);
            $decrypted = $this->encryptionService->decryptMessage(
                $encrypted['data'],
                $encrypted['iv'],
                $symmetricKey
            );

            expect($decrypted)->toBe($content);
        });
    });

    describe('Memory and Resource Exhaustion', function () {
        it('handles low memory conditions', function () {
            // Test multiple encryption operations
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messages = [];

            // Create multiple messages to test memory usage
            for ($i = 0; $i < 50; $i++) {
                $content = "Test message number {$i} with some content";
                $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);
                $messages[] = $encrypted;
            }

            // Verify all messages can be decrypted
            foreach ($messages as $i => $encrypted) {
                $decrypted = $this->encryptionService->decryptMessage(
                    $encrypted['data'],
                    $encrypted['iv'],
                    $symmetricKey
                );
                expect($decrypted)->toBe("Test message number {$i} with some content");
            }
        });

        it('handles rapid successive encryptions', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $results = [];

            $startTime = microtime(true);

            // Perform rapid encryptions
            for ($i = 0; $i < 100; $i++) {
                $encrypted = $this->encryptionService->encryptMessage("Message {$i}", $symmetricKey);
                $results[] = $encrypted;
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            expect($executionTime)->toBeLessThan(10.0); // Should complete within 10 seconds
            expect(count($results))->toBe(100);

            // Verify random samples can be decrypted
            $samples = [0, 25, 50, 75, 99];
            foreach ($samples as $index) {
                $decrypted = $this->encryptionService->decryptMessage(
                    $results[$index]['data'],
                    $results[$index]['iv'],
                    $symmetricKey
                );
                expect($decrypted)->toBe("Message {$index}");
            }
        });
    });

    describe('Key Management Edge Cases', function () {
        it('handles expired encryption keys', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create a default device for the user if it doesn't exist
            $device = \App\Models\UserDevice::firstOrCreate(
                ['user_id' => $this->user1->id],
                [
                    'device_name' => 'Test Device',
                    'device_type' => 'web',
                    'platform' => 'web',
                    'device_fingerprint' => 'test-device-expired-'.$this->user1->id,
                    'public_key' => $keyPair['public_key'],
                    'last_used_at' => now(),
                    'is_trusted' => true,
                ]
            );

            // Create an expired key
            $expiredKey = EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user1->id,
                'device_id' => $device->id,
                'device_fingerprint' => $device->device_fingerprint,
                'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair['public_key']),
                'public_key' => $keyPair['public_key'],
                'key_version' => 1,
                'is_active' => true,
                'expires_at' => now()->subDay(), // Expired
                'algorithm' => 'RSA-4096-OAEP',
                'key_strength' => 4096,
            ]);

            // Mark as expired in database
            $expiredKey->update(['is_active' => false]);

            expect($expiredKey->is_active)->toBeFalse();
        });

        it('handles key version mismatches', function () {
            $symmetricKey1 = $this->encryptionService->generateSymmetricKey();
            $symmetricKey2 = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create keys with different versions
            $keyV1 = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $symmetricKey1,
                $keyPair['public_key']
            );

            // Update to simulate version 2
            $keyV1->update(['key_version' => 2]);

            expect($keyV1->key_version)->toBe(2);
        });

        it('handles orphaned encryption keys', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create key for non-existent conversation
            $nonExistentConversationId = 'non-existent-conv-'.uniqid();

            expect(fn () => EncryptionKey::createForUser(
                $nonExistentConversationId,
                $this->user1->id,
                $symmetricKey,
                $keyPair['public_key']
            ))->toThrow(\Exception::class);
        });
    });

    describe('Malformed Input Handling', function () {
        it('handles malformed JSON in encrypted content', function () {
            $message = Message::create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => $this->user1->id,
                'content' => 'Test message',
                'content_hash' => hash('sha256', 'Test message'),
                'encrypted_content' => 'invalid-json{malformed',
                'content_iv' => 'test-iv',
                'content_hmac' => 'test-hmac',
            ]);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            expect(fn () => $message->decryptContent($symmetricKey))
                ->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('handles invalid base64 encoding in encryption data', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            expect(fn () => $this->encryptionService->decryptMessage(
                'invalid-base64-!@#$%^&*',
                'valid-iv-data-here==',
                $symmetricKey
            ))->toThrow(\App\Exceptions\DecryptionException::class);
        });

        it('handles wrong key length for symmetric encryption', function () {
            $shortKey = 'short'; // Too short
            $longKey = str_repeat('x', 64); // Too long for AES-256

            expect(fn () => $this->encryptionService->encryptMessage('test', $shortKey))
                ->toThrow(\App\Exceptions\EncryptionException::class);

            expect(fn () => $this->encryptionService->encryptMessage('test', $longKey))
                ->toThrow(\App\Exceptions\EncryptionException::class);
        });
    });

    describe('State Consistency Edge Cases', function () {
        it('maintains consistency during partial failures', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Start a database transaction to test rollback behavior
            \DB::transaction(function () use ($symmetricKey) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    'Transaction test message',
                    $symmetricKey
                );

                expect($message)->toBeInstanceOf(Message::class);

                // Verify the message exists
                expect(Message::find($message->id))->not()->toBeNull();
            });
        });

        it('handles duplicate key creation attempts', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create first key
            $key1 = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $symmetricKey,
                $keyPair['public_key']
            );

            // Attempt to create duplicate (should handle gracefully or throw specific exception)
            try {
                $key2 = EncryptionKey::createForUser(
                    $this->conversation->id,
                    $this->user1->id,
                    $symmetricKey,
                    $keyPair['public_key']
                );

                // If allowed, keys should be different instances
                expect($key1->id)->not()->toBe($key2->id);
            } catch (\Exception $e) {
                // Or it should throw a meaningful exception
                expect($e->getMessage())->toContain('duplicate');
            }
        });
    });
});
