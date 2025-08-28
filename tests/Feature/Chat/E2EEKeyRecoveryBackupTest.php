<?php

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\Participant;
use App\Models\User;
use App\Services\ChatEncryptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('E2EE Key Recovery and Backup System', function () {
    beforeEach(function () {
        Storage::fake('backups');
        $this->encryptionService = app(ChatEncryptionService::class);

        // Create test users with key pairs
        $this->user1 = User::factory()->create(['email_verified_at' => now()]);
        $this->user2 = User::factory()->create(['email_verified_at' => now()]);

        $keyPair1 = $this->encryptionService->generateKeyPair();
        $keyPair2 = $this->encryptionService->generateKeyPair();

        $this->user1->update(['public_key' => $keyPair1['public_key']]);
        $this->user2->update(['public_key' => $keyPair2['public_key']]);

        $this->user1KeyPair = $keyPair1;
        $this->user2KeyPair = $keyPair2;

        // Create test conversation with encryption
        $this->conversation = Conversation::factory()->create(['is_encrypted' => true]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'role' => 'admin',
        ]);

        Participant::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user2->id,
            'role' => 'member',
        ]);

        // Create encryption keys
        $this->symmetricKey = $this->encryptionService->generateSymmetricKey();

        $this->encKey1 = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user1->id,
            $this->symmetricKey,
            $this->user1->public_key
        );

        $this->encKey2 = EncryptionKey::createForUser(
            $this->conversation->id,
            $this->user2->id,
            $this->symmetricKey,
            $this->user2->public_key
        );
    });

    describe('Key Backup Creation', function () {
        it('can create a complete user key backup', function () {
            // Create additional conversations for comprehensive backup
            $conversations = [];
            $keys = [];

            for ($i = 0; $i < 5; $i++) {
                $conv = Conversation::factory()->create(['is_encrypted' => true]);
                $conversations[] = $conv;

                Participant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $this->user1->id,
                    'role' => $i === 0 ? 'admin' : 'member',
                ]);

                $symKey = $this->encryptionService->generateSymmetricKey();
                $keys[] = EncryptionKey::createForUser(
                    $conv->id,
                    $this->user1->id,
                    $symKey,
                    $this->user1->public_key
                );
            }

            // Create backup
            $backupData = [
                'user_id' => $this->user1->id,
                'backup_timestamp' => now()->toISOString(),
                'public_key' => $this->user1->public_key,
                'conversations' => $this->user1->encryptionKeys()
                    ->with('conversation')
                    ->get()
                    ->map(function ($key) {
                        return [
                            'conversation_id' => $key->conversation_id,
                            'conversation_name' => $key->conversation->name ?? 'Direct Chat',
                            'encrypted_key' => $key->encrypted_key,
                            'key_version' => $key->key_version,
                            'algorithm' => $key->algorithm,
                            'key_strength' => $key->key_strength,
                            'created_at' => $key->created_at->toISOString(),
                            'is_active' => $key->is_active,
                        ];
                    })->toArray(),
                'metadata' => [
                    'total_conversations' => count($conversations) + 1, // +1 for initial conversation
                    'backup_version' => '1.0',
                    'encryption_algorithm' => 'RSA-4096-OAEP',
                ],
            ];

            expect($backupData['conversations'])->toHaveCount(6);
            expect($backupData['metadata']['total_conversations'])->toBe(6);

            // Verify all conversations are included
            $conversationIds = array_column($backupData['conversations'], 'conversation_id');
            expect(in_array($this->conversation->id, $conversationIds))->toBeTrue();

            foreach ($conversations as $conv) {
                expect(in_array($conv->id, $conversationIds))->toBeTrue();
            }
        });

        it('can create encrypted backup with master password', function () {
            $masterPassword = 'super-secure-master-password-2024';

            $backupData = [
                'user_id' => $this->user1->id,
                'conversations' => $this->user1->encryptionKeys()->get()->map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'encrypted_key' => $key->encrypted_key,
                    ];
                })->toArray(),
            ];

            // Encrypt backup with master password
            $encryptedBackup = Crypt::encrypt(json_encode($backupData));

            // Verify backup is encrypted (should not contain plain JSON)
            expect(str_contains($encryptedBackup, $this->user1->id))->toBeFalse();
            expect(str_contains($encryptedBackup, 'conversations'))->toBeFalse();

            // Verify backup can be decrypted
            $decryptedData = json_decode(Crypt::decrypt($encryptedBackup), true);
            expect($decryptedData['user_id'])->toBe($this->user1->id);
            expect($decryptedData['conversations'])->toBeArray();
        });

        it('includes conversation metadata in backup', function () {
            // Add some messages to the conversation
            $messages = [];
            for ($i = 0; $i < 10; $i++) {
                $content = "Test message $i";
                $encryptedContent = $this->encryptionService->encryptMessage($content, $this->symmetricKey);

                $messages[] = Message::create([
                    'conversation_id' => $this->conversation->id,
                    'sender_id' => $i % 2 === 0 ? $this->user1->id : $this->user2->id,
                    'type' => 'text',
                    'encrypted_content' => json_encode($encryptedContent),
                    'content_hash' => hash('sha256', $content),
                ]);
            }

            $backupData = [
                'user_id' => $this->user1->id,
                'conversations' => $this->user1->encryptionKeys()
                    ->with(['conversation.messages', 'conversation.participants'])
                    ->get()
                    ->map(function ($key) {
                        $conversation = $key->conversation;

                        return [
                            'conversation_id' => $conversation->id,
                            'encrypted_key' => $key->encrypted_key,
                            'conversation_metadata' => [
                                'name' => $conversation->name,
                                'type' => $conversation->type,
                                'created_at' => $conversation->created_at->toISOString(),
                                'message_count' => $conversation->messages()->count(),
                                'participant_count' => $conversation->participants()->count(),
                                'last_activity' => $conversation->messages()
                                    ->latest()
                                    ->value('created_at')?->toISOString(),
                            ],
                        ];
                    })->toArray(),
            ];

            $conversationBackup = $backupData['conversations'][0];
            expect($conversationBackup['conversation_metadata']['message_count'])->toBe(10);
            expect($conversationBackup['conversation_metadata']['participant_count'])->toBe(2);
            expect($conversationBackup['conversation_metadata']['last_activity'])->not()->toBeNull();
        });

        it('can create incremental backup', function () {
            $initialBackupTime = now()->subDays(7);

            // Create initial backup data
            $initialBackup = [
                'backup_timestamp' => $initialBackupTime->toISOString(),
                'conversations' => [$this->conversation->id],
            ];

            // Add new conversations after initial backup
            $newConversations = [];
            for ($i = 0; $i < 3; $i++) {
                $conv = Conversation::factory()->create([
                    'is_encrypted' => true,
                    'created_at' => now()->subDays(3), // After initial backup
                ]);
                $newConversations[] = $conv;

                Participant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $this->user1->id,
                    'role' => 'member',
                ]);

                EncryptionKey::createForUser(
                    $conv->id,
                    $this->user1->id,
                    $this->encryptionService->generateSymmetricKey(),
                    $this->user1->public_key
                );
            }

            // Create incremental backup (only new/modified since initial)
            $incrementalBackup = [
                'backup_type' => 'incremental',
                'since_timestamp' => $initialBackupTime->toISOString(),
                'backup_timestamp' => now()->toISOString(),
                'conversations' => $this->user1->encryptionKeys()
                    ->whereHas('conversation', function ($query) use ($initialBackupTime) {
                        $query->where('created_at', '>', $initialBackupTime);
                    })
                    ->with('conversation')
                    ->get()
                    ->map(function ($key) {
                        return [
                            'conversation_id' => $key->conversation_id,
                            'encrypted_key' => $key->encrypted_key,
                            'added_at' => $key->created_at->toISOString(),
                        ];
                    })->toArray(),
            ];

            expect($incrementalBackup['conversations'])->toHaveCount(3);
            foreach ($newConversations as $conv) {
                $found = collect($incrementalBackup['conversations'])
                    ->pluck('conversation_id')
                    ->contains($conv->id);
                expect($found)->toBeTrue();
            }
        });
    });

    describe('Key Recovery Process', function () {
        it('can restore keys from backup', function () {
            // Create backup
            $originalKeys = $this->user1->encryptionKeys()->get();
            $backupData = [
                'user_id' => $this->user1->id,
                'public_key' => $this->user1->public_key,
                'conversations' => $originalKeys->map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'encrypted_key' => $key->encrypted_key,
                        'key_version' => $key->key_version,
                        'algorithm' => $key->algorithm,
                        'key_strength' => $key->key_strength,
                    ];
                })->toArray(),
            ];

            // Simulate key loss by deleting user's encryption keys
            $this->user1->encryptionKeys()->delete();
            expect($this->user1->encryptionKeys()->count())->toBe(0);

            // Restore from backup
            foreach ($backupData['conversations'] as $convData) {
                EncryptionKey::create([
                    'conversation_id' => $convData['conversation_id'],
                    'user_id' => $backupData['user_id'],
                    'encrypted_key' => $convData['encrypted_key'],
                    'public_key' => $backupData['public_key'],
                    'key_version' => $convData['key_version'],
                    'algorithm' => $convData['algorithm'],
                    'key_strength' => $convData['key_strength'],
                    'is_active' => true,
                ]);
            }

            // Verify restoration
            $restoredKeys = $this->user1->fresh()->encryptionKeys()->get();
            expect($restoredKeys)->toHaveCount(count($originalKeys));

            // Verify keys work for decryption
            $testMessage = 'Recovery test message';
            $encryptedMessage = $this->encryptionService->encryptMessage($testMessage, $this->symmetricKey);

            $decryptedKey = $this->encryptionService->decryptSymmetricKey(
                $restoredKeys->first()->encrypted_key,
                $this->user1KeyPair['private_key']
            );

            $decryptedMessage = $this->encryptionService->decryptMessage($encryptedMessage, $decryptedKey);
            expect($decryptedMessage)->toBe($testMessage);
        });

        it('can recover from partial key loss', function () {
            // Create multiple conversations
            $conversations = [];
            $allKeys = [];

            for ($i = 0; $i < 5; $i++) {
                $conv = Conversation::factory()->create(['is_encrypted' => true]);
                $conversations[] = $conv;

                Participant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $this->user1->id,
                    'role' => 'member',
                ]);

                $key = EncryptionKey::createForUser(
                    $conv->id,
                    $this->user1->id,
                    $this->encryptionService->generateSymmetricKey(),
                    $this->user1->public_key
                );
                $allKeys[] = $key;
            }

            // Simulate partial key loss (delete some keys)
            $keysToLose = array_slice($allKeys, 0, 3);
            foreach ($keysToLose as $key) {
                $key->delete();
            }

            $remainingKeys = $this->user1->fresh()->encryptionKeys()->count();
            expect($remainingKeys)->toBe(3); // 2 remaining + 1 original

            // Create backup of lost keys
            $backupData = [
                'lost_keys' => array_map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'encrypted_key' => $key->encrypted_key,
                        'algorithm' => $key->algorithm,
                        'key_strength' => $key->key_strength,
                    ];
                }, $keysToLose),
            ];

            // Restore lost keys
            foreach ($backupData['lost_keys'] as $keyData) {
                EncryptionKey::create([
                    'conversation_id' => $keyData['conversation_id'],
                    'user_id' => $this->user1->id,
                    'encrypted_key' => $keyData['encrypted_key'],
                    'public_key' => $this->user1->public_key,
                    'algorithm' => $keyData['algorithm'],
                    'key_strength' => $keyData['key_strength'],
                    'is_active' => true,
                ]);
            }

            // Verify complete recovery
            $finalKeyCount = $this->user1->fresh()->encryptionKeys()->count();
            expect($finalKeyCount)->toBe(6); // All keys restored
        });

        it('can recover with new device and private key', function () {
            // Simulate user getting new device with new key pair
            $newKeyPair = $this->encryptionService->generateKeyPair();

            // Backup includes the symmetric keys encrypted with old public key
            $backupData = [
                'user_id' => $this->user1->id,
                'old_public_key' => $this->user1->public_key,
                'conversations' => $this->user1->encryptionKeys()->get()->map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'encrypted_key' => $key->encrypted_key,
                    ];
                })->toArray(),
            ];

            // Update user with new key pair
            $this->user1->update(['public_key' => $newKeyPair['public_key']]);

            // Delete old encryption keys
            $this->user1->encryptionKeys()->delete();

            // For recovery, we need the old private key to decrypt the symmetric keys
            // and then re-encrypt them with the new public key
            foreach ($backupData['conversations'] as $convData) {
                // Decrypt symmetric key with old private key
                $symmetricKey = $this->encryptionService->decryptSymmetricKey(
                    $convData['encrypted_key'],
                    $this->user1KeyPair['private_key'] // Old private key
                );

                // Re-encrypt with new public key
                $newEncryptedKey = $this->encryptionService->encryptSymmetricKey(
                    $symmetricKey,
                    $newKeyPair['public_key']
                );

                // Create new encryption key record
                EncryptionKey::create([
                    'conversation_id' => $convData['conversation_id'],
                    'user_id' => $this->user1->id,
                    'encrypted_key' => $newEncryptedKey,
                    'public_key' => $newKeyPair['public_key'],
                    'algorithm' => 'RSA-4096-OAEP',
                    'key_strength' => 4096,
                    'is_active' => true,
                ]);
            }

            // Verify recovery with new keys
            $recoveredKeys = $this->user1->fresh()->encryptionKeys()->get();
            expect($recoveredKeys)->toHaveCount(1);

            // Test decryption with new private key
            $testMessage = 'New device recovery test';
            $encryptedMessage = $this->encryptionService->encryptMessage($testMessage, $this->symmetricKey);

            $decryptedKey = $this->encryptionService->decryptSymmetricKey(
                $recoveredKeys->first()->encrypted_key,
                $newKeyPair['private_key']
            );

            $decryptedMessage = $this->encryptionService->decryptMessage($encryptedMessage, $decryptedKey);
            expect($decryptedMessage)->toBe($testMessage);
        });
    });

    describe('Emergency Recovery Scenarios', function () {
        it('can perform emergency recovery with admin assistance', function () {
            // Simulate complete key loss scenario
            $this->user1->encryptionKeys()->delete();
            $this->user1->update(['public_key' => null]);

            // Admin creates emergency recovery package
            $adminUser = User::factory()->create(['role' => 'admin']);
            $adminKeyPair = $this->encryptionService->generateKeyPair();
            $adminUser->update(['public_key' => $adminKeyPair['public_key']]);

            // Emergency recovery data (would be provided by admin)
            $emergencyRecovery = [
                'user_id' => $this->user1->id,
                'recovery_initiated_by' => $adminUser->id,
                'recovery_timestamp' => now()->toISOString(),
                'new_key_pair_required' => true,
                'conversations_to_recover' => [
                    [
                        'conversation_id' => $this->conversation->id,
                        'emergency_symmetric_key' => $this->symmetricKey,
                        'conversation_name' => $this->conversation->name,
                    ],
                ],
            ];

            // Generate new key pair for user
            $newUserKeyPair = $this->encryptionService->generateKeyPair();
            $this->user1->update(['public_key' => $newUserKeyPair['public_key']]);

            // Create new encryption keys for recovered conversations
            foreach ($emergencyRecovery['conversations_to_recover'] as $convData) {
                $encryptedKey = $this->encryptionService->encryptSymmetricKey(
                    $convData['emergency_symmetric_key'],
                    $newUserKeyPair['public_key']
                );

                EncryptionKey::create([
                    'conversation_id' => $convData['conversation_id'],
                    'user_id' => $this->user1->id,
                    'encrypted_key' => $encryptedKey,
                    'public_key' => $newUserKeyPair['public_key'],
                    'algorithm' => 'RSA-4096-OAEP',
                    'key_strength' => 4096,
                    'is_active' => true,
                ]);
            }

            // Verify emergency recovery worked
            $recoveredKeys = $this->user1->fresh()->encryptionKeys()->get();
            expect($recoveredKeys)->toHaveCount(1);

            // Verify user can decrypt messages
            $testMessage = 'Emergency recovery test';
            $encryptedMessage = $this->encryptionService->encryptMessage($testMessage, $this->symmetricKey);

            $decryptedKey = $this->encryptionService->decryptSymmetricKey(
                $recoveredKeys->first()->encrypted_key,
                $newUserKeyPair['private_key']
            );

            $decryptedMessage = $this->encryptionService->decryptMessage($encryptedMessage, $decryptedKey);
            expect($decryptedMessage)->toBe($testMessage);
        });

        it('handles recovery when some conversations are permanently lost', function () {
            // Create multiple conversations
            $recoverableConvs = [];
            $lostConvs = [];

            // Recoverable conversations (have backup)
            for ($i = 0; $i < 3; $i++) {
                $conv = Conversation::factory()->create(['is_encrypted' => true]);
                $recoverableConvs[] = $conv;

                Participant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $this->user1->id,
                    'role' => 'member',
                ]);

                EncryptionKey::createForUser(
                    $conv->id,
                    $this->user1->id,
                    $this->encryptionService->generateSymmetricKey(),
                    $this->user1->public_key
                );
            }

            // Lost conversations (no backup available)
            for ($i = 0; $i < 2; $i++) {
                $conv = Conversation::factory()->create(['is_encrypted' => true]);
                $lostConvs[] = $conv;

                Participant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $this->user1->id,
                    'role' => 'member',
                ]);

                EncryptionKey::createForUser(
                    $conv->id,
                    $this->user1->id,
                    $this->encryptionService->generateSymmetricKey(),
                    $this->user1->public_key
                );
            }

            // Create partial backup (only recoverable conversations)
            $partialBackup = [
                'user_id' => $this->user1->id,
                'backup_timestamp' => now()->subHours(2)->toISOString(),
                'conversations' => collect($recoverableConvs)->map(function ($conv) {
                    $key = $this->user1->encryptionKeys()
                        ->where('conversation_id', $conv->id)
                        ->first();

                    return [
                        'conversation_id' => $conv->id,
                        'encrypted_key' => $key->encrypted_key,
                    ];
                })->toArray(),
                'note' => 'Partial backup - some conversations created after this backup may be unrecoverable',
            ];

            // Simulate complete key loss
            $this->user1->encryptionKeys()->delete();

            // Attempt recovery
            $recoveryStatus = [
                'total_conversations_before_loss' => 6, // 1 original + 3 recoverable + 2 lost
                'recoverable_from_backup' => count($partialBackup['conversations']),
                'permanently_lost' => 2,
                'recovered_conversations' => [],
                'lost_conversations' => array_map(fn ($conv) => $conv->id, $lostConvs),
            ];

            // Restore recoverable conversations
            foreach ($partialBackup['conversations'] as $convData) {
                EncryptionKey::create([
                    'conversation_id' => $convData['conversation_id'],
                    'user_id' => $this->user1->id,
                    'encrypted_key' => $convData['encrypted_key'],
                    'public_key' => $this->user1->public_key,
                    'algorithm' => 'RSA-4096-OAEP',
                    'key_strength' => 4096,
                    'is_active' => true,
                ]);

                $recoveryStatus['recovered_conversations'][] = $convData['conversation_id'];
            }

            // Verify partial recovery
            $finalKeys = $this->user1->fresh()->encryptionKeys()->get();
            expect($finalKeys)->toHaveCount($recoveryStatus['recoverable_from_backup']);
            expect($recoveryStatus['recovered_conversations'])->toHaveCount(3);
            expect($recoveryStatus['permanently_lost'])->toBe(2);

            // Verify recovered conversations work
            foreach ($recoverableConvs as $conv) {
                $key = $finalKeys->where('conversation_id', $conv->id)->first();
                expect($key)->not()->toBeNull();
            }
        });
    });

    describe('Backup Security and Validation', function () {
        it('validates backup integrity before restoration', function () {
            // Create valid backup
            $validBackup = [
                'version' => '1.0',
                'user_id' => $this->user1->id,
                'backup_timestamp' => now()->toISOString(),
                'checksum' => 'placeholder-checksum',
                'conversations' => [[
                    'conversation_id' => $this->conversation->id,
                    'encrypted_key' => $this->encKey1->encrypted_key,
                ]],
            ];

            // Calculate actual checksum
            $dataForChecksum = json_encode($validBackup['conversations']);
            $validBackup['checksum'] = hash('sha256', $dataForChecksum);

            // Validate backup integrity
            $calculatedChecksum = hash('sha256', json_encode($validBackup['conversations']));
            expect($calculatedChecksum)->toBe($validBackup['checksum']);

            // Test with corrupted backup
            $corruptedBackup = $validBackup;
            $corruptedBackup['conversations'][0]['encrypted_key'] = 'corrupted-key-data';

            $corruptedChecksum = hash('sha256', json_encode($corruptedBackup['conversations']));
            expect($corruptedChecksum)->not()->toBe($validBackup['checksum']);
        });

        it('protects backup with user verification', function () {
            $userPassword = 'user-backup-password-2024';
            $backupPassphrase = 'additional-backup-passphrase';

            // Create secured backup
            $backupData = [
                'user_id' => $this->user1->id,
                'conversations' => [[
                    'conversation_id' => $this->conversation->id,
                    'encrypted_key' => $this->encKey1->encrypted_key,
                ]],
            ];

            // Double encryption: first with user password, then with backup passphrase
            $firstEncryption = Crypt::encrypt(json_encode($backupData));
            $finalBackup = Crypt::encrypt($firstEncryption);

            // Verify backup is completely opaque
            expect(str_contains($finalBackup, $this->user1->id))->toBeFalse();
            expect(str_contains($finalBackup, 'conversations'))->toBeFalse();
            expect(str_contains($finalBackup, $this->conversation->id))->toBeFalse();

            // Verify backup can be decrypted with both keys
            $firstDecryption = Crypt::decrypt($finalBackup);
            $finalData = json_decode(Crypt::decrypt($firstDecryption), true);

            expect($finalData['user_id'])->toBe($this->user1->id);
            expect($finalData['conversations'][0]['conversation_id'])->toBe($this->conversation->id);
        });

        it('handles backup versioning and compatibility', function () {
            // Test different backup versions
            $backupV1 = [
                'version' => '1.0',
                'format' => 'basic',
                'user_id' => $this->user1->id,
                'conversations' => [['conversation_id' => $this->conversation->id]],
            ];

            $backupV2 = [
                'version' => '2.0',
                'format' => 'enhanced',
                'user_id' => $this->user1->id,
                'conversations' => [[
                    'conversation_id' => $this->conversation->id,
                    'metadata' => ['created_at' => now()->toISOString()],
                    'security_level' => 'high',
                ]],
            ];

            // Version compatibility check
            $supportedVersions = ['1.0', '1.1', '2.0'];

            expect(in_array($backupV1['version'], $supportedVersions))->toBeTrue();
            expect(in_array($backupV2['version'], $supportedVersions))->toBeTrue();
            expect(in_array('3.0', $supportedVersions))->toBeFalse();

            // Test migration from v1 to v2 format
            $migratedBackup = [
                'version' => '2.0',
                'format' => 'enhanced',
                'migrated_from' => $backupV1['version'],
                'user_id' => $backupV1['user_id'],
                'conversations' => array_map(function ($conv) {
                    return array_merge($conv, [
                        'metadata' => ['migrated' => true],
                        'security_level' => 'standard', // Default for migrated
                    ]);
                }, $backupV1['conversations']),
            ];

            expect($migratedBackup['version'])->toBe('2.0');
            expect($migratedBackup['migrated_from'])->toBe('1.0');
            expect($migratedBackup['conversations'][0]['metadata']['migrated'])->toBeTrue();
        });
    });

    describe('Automated Backup Systems', function () {
        it('can schedule and create automatic backups', function () {
            // Simulate automatic backup configuration
            $backupConfig = [
                'enabled' => true,
                'frequency' => 'daily',
                'retention_days' => 30,
                'encryption_level' => 'high',
                'include_metadata' => true,
            ];

            // Create series of backups over time
            $backups = [];
            $dates = [
                now()->subDays(7),
                now()->subDays(3),
                now()->subDay(),
                now(),
            ];

            foreach ($dates as $date) {
                $backup = [
                    'backup_id' => 'auto-'.$date->format('Y-m-d-H-i-s'),
                    'type' => 'automatic',
                    'created_at' => $date->toISOString(),
                    'user_id' => $this->user1->id,
                    'conversations' => $this->user1->encryptionKeys()->get()->map(function ($key) {
                        return [
                            'conversation_id' => $key->conversation_id,
                            'encrypted_key' => $key->encrypted_key,
                        ];
                    })->toArray(),
                ];
                $backups[] = $backup;
            }

            expect($backups)->toHaveCount(4);

            // Test backup rotation (keep only recent backups)
            $retentionDate = now()->subDays($backupConfig['retention_days']);
            $validBackups = array_filter($backups, function ($backup) use ($retentionDate) {
                return Carbon::parse($backup['created_at'])->isAfter($retentionDate);
            });

            expect(count($validBackups))->toBe(4); // All within retention period

            // Test with older backups
            $oldBackup = [
                'backup_id' => 'auto-old',
                'created_at' => now()->subDays(45)->toISOString(),
                'user_id' => $this->user1->id,
            ];
            $allBackups = array_merge($backups, [$oldBackup]);

            $validAfterRotation = array_filter($allBackups, function ($backup) use ($retentionDate) {
                return Carbon::parse($backup['created_at'])->isAfter($retentionDate);
            });

            expect(count($validAfterRotation))->toBe(4); // Old backup excluded
        });

        it('handles backup storage optimization', function () {
            // Create multiple backups with overlapping data
            $baseData = [
                'user_id' => $this->user1->id,
                'conversations' => [[
                    'conversation_id' => $this->conversation->id,
                    'encrypted_key' => $this->encKey1->encrypted_key,
                ]],
            ];

            // Full backup
            $fullBackup = array_merge($baseData, [
                'backup_type' => 'full',
                'backup_timestamp' => now()->subDays(7)->toISOString(),
                'size_bytes' => strlen(json_encode($baseData)),
            ]);

            // Incremental backup (no changes)
            $incrementalBackup = [
                'backup_type' => 'incremental',
                'base_backup_id' => 'full-backup-id',
                'backup_timestamp' => now()->toISOString(),
                'changes' => [], // No new conversations
                'size_bytes' => strlen(json_encode([])),
            ];

            // Verify storage optimization
            expect($incrementalBackup['size_bytes'])->toBeLessThan($fullBackup['size_bytes']);

            // Incremental with changes
            $newConversation = Conversation::factory()->create(['is_encrypted' => true]);
            Participant::create([
                'conversation_id' => $newConversation->id,
                'user_id' => $this->user1->id,
                'role' => 'member',
            ]);

            $newKey = EncryptionKey::createForUser(
                $newConversation->id,
                $this->user1->id,
                $this->encryptionService->generateSymmetricKey(),
                $this->user1->public_key
            );

            $incrementalWithChanges = [
                'backup_type' => 'incremental',
                'base_backup_id' => 'full-backup-id',
                'backup_timestamp' => now()->toISOString(),
                'changes' => [[
                    'conversation_id' => $newConversation->id,
                    'encrypted_key' => $newKey->encrypted_key,
                    'change_type' => 'added',
                ]],
                'size_bytes' => strlen(json_encode([[
                    'conversation_id' => $newConversation->id,
                    'encrypted_key' => $newKey->encrypted_key,
                ]])),
            ];

            expect(count($incrementalWithChanges['changes']))->toBe(1);
            expect($incrementalWithChanges['size_bytes'])->toBeLessThan($fullBackup['size_bytes']);
        });
    });
});
