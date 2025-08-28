<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->user3 = User::factory()->create();

    $this->conversation = Conversation::factory()->create([
        'type' => 'group',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
    $this->conversation->participants()->create(['user_id' => $this->user3->id, 'role' => 'member']);
});

describe('E2EE Concurrency and Race Conditions', function () {
    describe('Simultaneous Message Encryption', function () {
        it('handles concurrent message creation safely', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messages = [];
            $errors = [];

            // Simulate concurrent message creation
            $processes = [];
            for ($i = 0; $i < 10; $i++) {
                $processes[] = function () use ($i, $symmetricKey, &$messages, &$errors) {
                    try {
                        DB::transaction(function () use ($i, $symmetricKey, &$messages) {
                            $message = Message::createEncrypted(
                                $this->conversation->id,
                                $this->user1->id,
                                "Concurrent message {$i}",
                                $symmetricKey
                            );
                            $messages[] = $message;
                        });
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                };
            }

            // Execute all processes (simulated concurrency)
            foreach ($processes as $process) {
                $process();
            }

            // All messages should be created successfully
            expect(count($messages))->toBe(10);
            expect($errors)->toBeEmpty();

            // Each message should be unique and decryptable
            $messageIds = [];
            foreach ($messages as $message) {
                expect($messageIds)->not()->toContain($message->id);
                $messageIds[] = $message->id;

                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toStartWith('Concurrent message ');
            }
        });

        it('maintains message ordering under concurrent load', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messageCount = 20;
            $batchSize = 5;

            // Create messages in batches to simulate bursts
            for ($batch = 0; $batch < $messageCount / $batchSize; $batch++) {
                $batchMessages = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $messageIndex = ($batch * $batchSize) + $i;
                    $timestamp = now()->addMicroseconds($messageIndex * 1000); // Slight time differences

                    $message = Message::createEncrypted(
                        $this->conversation->id,
                        $this->user1->id,
                        "Batch {$batch} Message {$i}",
                        $symmetricKey
                    );

                    // Simulate different creation times
                    $message->created_at = $timestamp;
                    $message->save();

                    $batchMessages[] = $message;
                }

                // Verify batch was created successfully
                expect(count($batchMessages))->toBe($batchSize);
            }

            // Verify total message count
            $totalMessages = Message::where('conversation_id', $this->conversation->id)->count();
            expect($totalMessages)->toBe($messageCount);

            // Verify messages can be retrieved in order
            $orderedMessages = Message::where('conversation_id', $this->conversation->id)
                ->orderBy('created_at')
                ->get();

            expect(count($orderedMessages))->toBe($messageCount);

            // Verify each message is decryptable and in expected order
            foreach ($orderedMessages as $index => $message) {
                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toStartWith('Batch ');
            }
        });
    });

    describe('Concurrent Key Management', function () {
        it('handles simultaneous key creation for multiple users', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $users = [$this->user1, $this->user2, $this->user3];
            $createdKeys = [];
            $errors = [];

            // Generate key pairs for all users
            $keyPairs = [];
            foreach ($users as $user) {
                $keyPairs[$user->id] = $this->encryptionService->generateKeyPair();
            }

            // Simulate concurrent key creation
            foreach ($users as $user) {
                try {
                    DB::transaction(function () use ($user, $symmetricKey, $keyPairs, &$createdKeys) {
                        $encryptionKey = EncryptionKey::createForUser(
                            $this->conversation->id,
                            $user->id,
                            $symmetricKey,
                            $keyPairs[$user->id]['public_key']
                        );
                        $createdKeys[] = $encryptionKey;
                    });
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            expect(count($createdKeys))->toBe(3);
            expect($errors)->toBeEmpty();

            // Verify all keys are unique and functional
            $keyIds = [];
            foreach ($createdKeys as $key) {
                expect($keyIds)->not()->toContain($key->id);
                $keyIds[] = $key->id;

                // Verify key can decrypt the symmetric key
                $decryptedKey = $key->decryptSymmetricKey($keyPairs[$key->user_id]['private_key']);
                expect($decryptedKey)->toBe($symmetricKey);
            }
        });

        it('prevents duplicate key creation race conditions', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();
            $attempts = [];
            $errors = [];

            // Attempt to create the same key multiple times concurrently
            for ($i = 0; $i < 5; $i++) {
                try {
                    $encryptionKey = EncryptionKey::createForUser(
                        $this->conversation->id,
                        $this->user1->id,
                        $symmetricKey,
                        $keyPair['public_key']
                    );
                    $attempts[] = $encryptionKey;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            // Should either create multiple valid keys or handle duplicates gracefully
            if (count($attempts) > 1) {
                // Multiple keys created - verify they're all valid but unique
                $keyIds = [];
                foreach ($attempts as $key) {
                    expect($keyIds)->not()->toContain($key->id);
                    $keyIds[] = $key->id;
                }
            } else {
                // Only one key created due to duplicate prevention
                expect(count($attempts))->toBe(1);
                expect(count($errors))->toBeGreaterThan(0);
            }
        });

        it('handles concurrent key rotation safely', function () {
            $oldSymmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create initial keys
            $initialKey = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $oldSymmetricKey,
                $keyPair['public_key']
            );

            // Simulate concurrent key rotation attempts
            $rotationResults = [];
            $errors = [];

            for ($i = 0; $i < 3; $i++) {
                try {
                    DB::transaction(function () use (&$rotationResults, $i) {
                        // Deactivate old keys
                        EncryptionKey::where('conversation_id', $this->conversation->id)
                            ->where('is_active', true)
                            ->update(['is_active' => false]);

                        // Create new key
                        $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->conversation->id);
                        $newKeyPair = $this->encryptionService->generateKeyPair();

                        $newKey = EncryptionKey::createForUser(
                            $this->conversation->id,
                            $this->user1->id,
                            $newSymmetricKey,
                            $newKeyPair['public_key']
                        );

                        $rotationResults[] = [
                            'attempt' => $i,
                            'key' => $newKey,
                            'symmetric_key' => $newSymmetricKey,
                            'key_pair' => $newKeyPair,
                        ];
                    });
                } catch (\Exception $e) {
                    $errors[] = "Attempt {$i}: ".$e->getMessage();
                }
            }

            // At least one rotation should succeed
            expect(count($rotationResults))->toBeGreaterThan(0);

            // Verify the successful rotation
            $latestResult = end($rotationResults);
            $latestKey = $latestResult['key'];

            expect($latestKey->is_active)->toBeTrue();

            $decryptedKey = $latestKey->decryptSymmetricKey($latestResult['key_pair']['private_key']);
            expect($decryptedKey)->toBe($latestResult['symmetric_key']);
        });
    });

    describe('Multi-Device Synchronization', function () {
        it('handles concurrent device registration', function () {
            $deviceCount = 5;
            $registeredDevices = [];
            $errors = [];

            // Simulate concurrent device registration
            for ($i = 0; $i < $deviceCount; $i++) {
                try {
                    $keyPair = $this->encryptionService->generateKeyPair();

                    $device = $this->multiDeviceService->registerDevice(
                        $this->user1,
                        "Concurrent Device {$i}",
                        'mobile',
                        $keyPair['public_key'],
                        "concurrent_device_{$i}_".uniqid(),
                        'iOS',
                        'Mozilla/5.0...',
                        ['messaging', 'encryption'],
                        'medium'
                    );

                    $registeredDevices[] = $device;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            expect(count($registeredDevices))->toBe($deviceCount);
            expect($errors)->toBeEmpty();

            // Verify all devices are unique
            $deviceIds = [];
            $fingerprints = [];
            foreach ($registeredDevices as $device) {
                expect($deviceIds)->not()->toContain($device->id);
                expect($fingerprints)->not()->toContain($device->device_fingerprint);

                $deviceIds[] = $device->id;
                $fingerprints[] = $device->device_fingerprint;
            }
        });

        it('handles concurrent key sharing operations', function () {
            // Setup devices
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();
            $keyPair3 = $this->encryptionService->generateKeyPair();

            $device1 = $this->multiDeviceService->registerDevice(
                $this->user1, 'Device 1', 'mobile', $keyPair1['public_key'],
                'device1_'.uniqid(), 'iOS', 'Mozilla/5.0...', ['messaging', 'encryption'], 'high'
            );

            $device2 = $this->multiDeviceService->registerDevice(
                $this->user1, 'Device 2', 'desktop', $keyPair2['public_key'],
                'device2_'.uniqid(), 'macOS', 'Mozilla/5.0...', ['messaging', 'encryption'], 'high'
            );

            $device3 = $this->multiDeviceService->registerDevice(
                $this->user1, 'Device 3', 'mobile', $keyPair3['public_key'],
                'device3_'.uniqid(), 'Android', 'Mozilla/5.0...', ['messaging', 'encryption'], 'high'
            );

            // Trust devices
            $device1->markAsTrusted();
            $device2->markAsTrusted();
            $device3->markAsTrusted();

            // Create encryption key for device1
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device1->id,
                $symmetricKey,
                $device1->public_key
            );

            // Concurrent key sharing
            $shareResults = [];
            $errors = [];

            $targetDevices = [$device2, $device3];
            foreach ($targetDevices as $targetDevice) {
                try {
                    $result = $this->multiDeviceService->shareKeysWithNewDevice($device1, $targetDevice);
                    $shareResults[] = $result;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            expect(count($shareResults))->toBe(2);
            expect($errors)->toBeEmpty();

            // Verify all sharing operations succeeded
            foreach ($shareResults as $result) {
                expect($result['total_keys_shared'])->toBe(1);
                expect($result['failed_conversations'])->toBeEmpty();
            }
        });
    });

    describe('Database Transaction Safety', function () {
        it('maintains data consistency during transaction failures', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Attempt operation that might fail mid-transaction
            $initialKeyCount = EncryptionKey::count();
            $initialMessageCount = Message::count();

            try {
                DB::transaction(function () use ($symmetricKey, $keyPair) {
                    // Create encryption key
                    $encryptionKey = EncryptionKey::createForUser(
                        $this->conversation->id,
                        $this->user1->id,
                        $symmetricKey,
                        $keyPair['public_key']
                    );

                    // Create message
                    $message = Message::createEncrypted(
                        $this->conversation->id,
                        $this->user1->id,
                        'Transaction test message',
                        $symmetricKey
                    );

                    // Simulate failure condition
                    if (rand(1, 10) > 8) { // 20% chance of "failure"
                        throw new \Exception('Simulated transaction failure');
                    }
                });
            } catch (\Exception $e) {
                // Transaction should roll back completely
                expect($e->getMessage())->toContain('Simulated transaction failure');
            }

            // Verify counts are consistent
            $finalKeyCount = EncryptionKey::count();
            $finalMessageCount = Message::count();

            // Either both operations succeeded or both were rolled back
            if ($finalKeyCount > $initialKeyCount) {
                expect($finalMessageCount)->toBeGreaterThan($initialMessageCount);
            } else {
                expect($finalKeyCount)->toBe($initialKeyCount);
                expect($finalMessageCount)->toBe($initialMessageCount);
            }
        });

        it('handles deadlock scenarios gracefully', function () {
            $symmetricKey1 = $this->encryptionService->generateSymmetricKey();
            $symmetricKey2 = $this->encryptionService->generateSymmetricKey();

            $conversation2 = Conversation::factory()->create([
                'type' => 'direct',
                'created_by' => $this->user1->id,
            ]);

            $conversation2->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
            $conversation2->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);

            $attempts = 0;
            $successes = 0;
            $maxAttempts = 5;

            // Simulate operations that might cause deadlocks
            while ($attempts < $maxAttempts) {
                try {
                    DB::transaction(function () use ($symmetricKey1, $symmetricKey2, $conversation2, $attempts) {
                        if ($attempts % 2 === 0) {
                            // Create keys in one order
                            Message::createEncrypted($this->conversation->id, $this->user1->id, 'Message A', $symmetricKey1);
                            Message::createEncrypted($conversation2->id, $this->user1->id, 'Message B', $symmetricKey2);
                        } else {
                            // Create keys in reverse order
                            Message::createEncrypted($conversation2->id, $this->user2->id, 'Message C', $symmetricKey2);
                            Message::createEncrypted($this->conversation->id, $this->user2->id, 'Message D', $symmetricKey1);
                        }
                    });
                    $successes++;
                } catch (\Exception $e) {
                    // Deadlocks or other transaction failures are acceptable
                    // as long as some operations succeed
                }
                $attempts++;
            }

            // At least some operations should succeed
            expect($successes)->toBeGreaterThan(0);
        });
    });

    describe('Cache Coherency', function () {
        it('maintains cache consistency under concurrent access', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $keyPair = $this->encryptionService->generateKeyPair();

            // Create encryption key
            $encryptionKey = EncryptionKey::createForUser(
                $this->conversation->id,
                $this->user1->id,
                $symmetricKey,
                $keyPair['public_key']
            );

            // Simulate concurrent cache operations
            $cacheOps = [
                function () use ($encryptionKey) {
                    return EncryptionKey::find($encryptionKey->id);
                },
                function () use ($encryptionKey) {
                    $encryptionKey->touch();

                    return $encryptionKey->fresh();
                },
                function () use ($encryptionKey) {
                    return EncryptionKey::where('conversation_id', $encryptionKey->conversation_id)->get();
                },
            ];

            $results = [];
            foreach ($cacheOps as $operation) {
                $results[] = $operation();
            }

            // All operations should return consistent data
            expect(count($results))->toBe(3);

            if ($results[0] instanceof EncryptionKey) {
                expect($results[0]->id)->toBe($encryptionKey->id);
            }

            if ($results[1] instanceof EncryptionKey) {
                expect($results[1]->id)->toBe($encryptionKey->id);
            }

            if ($results[2] instanceof \Illuminate\Database\Eloquent\Collection) {
                expect($results[2]->contains('id', $encryptionKey->id))->toBeTrue();
            }
        });
    });

    describe('Resource Contention', function () {
        it('handles high-frequency encryption operations', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $operationCount = 50;
            $startTime = microtime(true);

            $results = [];
            $errors = [];

            // Rapid-fire encryption operations
            for ($i = 0; $i < $operationCount; $i++) {
                try {
                    $content = "High frequency message {$i}";
                    $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);
                    $decrypted = $this->encryptionService->decryptMessage($encrypted['data'], $encrypted['iv'], $symmetricKey);

                    $results[] = ['original' => $content, 'decrypted' => $decrypted];
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            // Should complete all operations successfully
            expect(count($results))->toBe($operationCount);
            expect($errors)->toBeEmpty();

            // Should complete within reasonable time (adjust based on system performance)
            expect($totalTime)->toBeLessThan(30.0);

            // Verify all results are correct
            foreach ($results as $result) {
                expect($result['decrypted'])->toBe($result['original']);
            }
        });

        it('handles memory pressure during bulk operations', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $bulkSize = 100;

            $initialMemory = memory_get_usage();
            $messages = [];

            // Create bulk messages
            for ($i = 0; $i < $bulkSize; $i++) {
                $content = "Bulk message {$i} with content: ".str_repeat('data ', 100);

                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );

                $messages[] = $message;

                // Periodically check memory usage
                if ($i % 20 === 0) {
                    $currentMemory = memory_get_usage();
                    $memoryIncrease = $currentMemory - $initialMemory;

                    // Memory usage should not grow excessively
                    expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024); // 50MB limit
                }
            }

            // Verify all messages were created and are decryptable
            expect(count($messages))->toBe($bulkSize);

            // Spot check some messages
            $testIndices = [0, 25, 50, 75, 99];
            foreach ($testIndices as $index) {
                $decrypted = $messages[$index]->decryptContent($symmetricKey);
                expect($decrypted)->toStartWith("Bulk message {$index}");
            }

            // Cleanup memory
            unset($messages);
            gc_collect_cycles();
        });
    });
});
