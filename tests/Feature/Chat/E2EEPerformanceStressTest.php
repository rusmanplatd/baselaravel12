<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Models\UserDevice;
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

    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
});

describe('E2EE Performance and Stress Testing', function () {
    describe('High Volume Message Processing', function () {
        it('handles bulk message encryption efficiently', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messageCount = 1000;
            $batchSize = 50;
            
            $totalStartTime = microtime(true);
            $encryptionTimes = [];
            $messageIds = [];

            // Process messages in batches for better memory management
            for ($batch = 0; $batch < $messageCount / $batchSize; $batch++) {
                $batchMessages = [];
                $batchStartTime = microtime(true);
                
                DB::transaction(function () use ($batch, $batchSize, $symmetricKey, &$batchMessages, &$messageIds) {
                    for ($i = 0; $i < $batchSize; $i++) {
                        $messageIndex = ($batch * $batchSize) + $i;
                        $content = "Bulk test message #{$messageIndex} with unique content: " . uniqid();
                        
                        $message = Message::createEncrypted(
                            $this->conversation->id,
                            $this->user1->id,
                            $content,
                            $symmetricKey
                        );
                        
                        $batchMessages[] = $message;
                        $messageIds[] = $message->id;
                    }
                });
                
                $batchTime = microtime(true) - $batchStartTime;
                $encryptionTimes[] = $batchTime;
                
                // Verify batch was processed correctly
                expect(count($batchMessages))->toBe($batchSize);
                
                // Clean up memory
                unset($batchMessages);
                if ($batch % 5 === 0) {
                    gc_collect_cycles();
                }
            }

            $totalTime = microtime(true) - $totalStartTime;

            // Performance assertions
            expect($totalTime)->toBeLessThan(60.0); // Should complete within 1 minute
            expect(count($messageIds))->toBe($messageCount);

            // Verify database integrity
            $dbMessageCount = Message::where('conversation_id', $this->conversation->id)->count();
            expect($dbMessageCount)->toBe($messageCount);

            // Check average batch processing time
            $avgBatchTime = array_sum($encryptionTimes) / count($encryptionTimes);
            expect($avgBatchTime)->toBeLessThan(5.0); // Each batch should process within 5 seconds

            // Spot check some messages for decryption integrity
            $sampleIds = array_slice($messageIds, 0, 10);
            foreach ($sampleIds as $messageId) {
                $message = Message::find($messageId);
                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toStartWith('Bulk test message #');
            }
        });

        it('maintains performance under concurrent user load', function () {
            $userCount = 10;
            $messagesPerUser = 20;
            $users = [];
            $conversations = [];
            $symmetricKeys = [];

            // Setup multiple users and conversations
            for ($i = 0; $i < $userCount; $i++) {
                $user = User::factory()->create();
                $users[] = $user;
                
                $conversation = Conversation::factory()->create([
                    'type' => 'direct',
                    'created_by' => $user->id,
                ]);
                
                $conversation->participants()->create(['user_id' => $user->id, 'role' => 'admin']);
                $conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'member']);
                
                $conversations[] = $conversation;
                $symmetricKeys[] = $this->encryptionService->generateSymmetricKey();
            }

            $totalStartTime = microtime(true);
            $allMessageIds = [];

            // Simulate concurrent message sending
            foreach ($users as $index => $user) {
                $userStartTime = microtime(true);
                $userMessages = [];
                
                for ($j = 0; $j < $messagesPerUser; $j++) {
                    $content = "User {$index} message {$j}: " . uniqid();
                    
                    $message = Message::createEncrypted(
                        $conversations[$index]->id,
                        $user->id,
                        $content,
                        $symmetricKeys[$index]
                    );
                    
                    $userMessages[] = $message->id;
                    $allMessageIds[] = $message->id;
                }
                
                $userTime = microtime(true) - $userStartTime;
                expect($userTime)->toBeLessThan(10.0); // Each user should complete within 10 seconds
                expect(count($userMessages))->toBe($messagesPerUser);
            }

            $totalTime = microtime(true) - $totalStartTime;
            
            // Overall performance checks
            expect($totalTime)->toBeLessThan(30.0);
            expect(count($allMessageIds))->toBe($userCount * $messagesPerUser);

            // Verify all messages are decryptable
            foreach ($users as $index => $user) {
                $userMessages = Message::where('conversation_id', $conversations[$index]->id)
                    ->where('sender_id', $user->id)
                    ->get();
                    
                expect($userMessages->count())->toBe($messagesPerUser);
                
                // Sample decrypt a few messages
                $samples = $userMessages->take(3);
                foreach ($samples as $message) {
                    $decrypted = $message->decryptContent($symmetricKeys[$index]);
                    expect($decrypted)->toStartWith("User {$index} message");
                }
            }
        });
    });

    describe('Large Message Content Processing', function () {
        it('handles very large encrypted messages efficiently', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messageSizes = [
                '1KB' => 1024,
                '10KB' => 10 * 1024,
                '100KB' => 100 * 1024,
                '1MB' => 1024 * 1024,
                '5MB' => 5 * 1024 * 1024,
            ];

            foreach ($messageSizes as $sizeName => $sizeBytes) {
                $startTime = microtime(true);
                $startMemory = memory_get_usage();
                
                // Create large content
                $largeContent = str_repeat('A', $sizeBytes);
                $content = "Large message test ({$sizeName}): " . $largeContent;
                
                // Encrypt
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );
                
                $encryptTime = microtime(true) - $startTime;
                
                // Decrypt
                $decryptStartTime = microtime(true);
                $decrypted = $message->decryptContent($symmetricKey);
                $decryptTime = microtime(true) - $decryptStartTime;
                
                $totalTime = microtime(true) - $startTime;
                $memoryUsed = memory_get_usage() - $startMemory;
                
                // Verify correctness
                expect($decrypted)->toBe($content);
                expect(strlen($decrypted))->toBe(strlen($content));
                
                // Performance assertions (adjust based on system capacity)
                expect($encryptTime)->toBeLessThan(10.0); // Encryption within 10 seconds
                expect($decryptTime)->toBeLessThan(10.0); // Decryption within 10 seconds
                expect($totalTime)->toBeLessThan(15.0); // Total within 15 seconds
                expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB memory overhead
                
                // Clean up
                $message->delete();
                unset($largeContent, $content, $decrypted);
                gc_collect_cycles();
            }
        });

        it('processes multiple large messages without memory leaks', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messageCount = 50;
            $messageSize = 100 * 1024; // 100KB each
            
            $initialMemory = memory_get_usage();
            $peakMemory = $initialMemory;
            $messageIds = [];

            for ($i = 0; $i < $messageCount; $i++) {
                $content = "Large message {$i}: " . str_repeat('X', $messageSize);
                
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );
                
                $messageIds[] = $message->id;
                
                // Monitor memory usage
                $currentMemory = memory_get_usage();
                $peakMemory = max($peakMemory, $currentMemory);
                
                // Periodic cleanup
                if ($i % 10 === 9) {
                    gc_collect_cycles();
                }
                
                // Memory shouldn't grow excessively
                $memoryIncrease = $currentMemory - $initialMemory;
                expect($memoryIncrease)->toBeLessThan(200 * 1024 * 1024); // Less than 200MB growth
            }

            // Verify all messages were created
            expect(count($messageIds))->toBe($messageCount);

            // Spot check decryption
            $samples = array_slice($messageIds, 0, 5);
            foreach ($samples as $messageId) {
                $message = Message::find($messageId);
                $decrypted = $message->decryptContent($symmetricKey);
                expect(strlen($decrypted))->toBeGreaterThan($messageSize);
            }

            // Final memory check
            gc_collect_cycles();
            $finalMemory = memory_get_usage();
            $totalMemoryIncrease = $finalMemory - $initialMemory;
            expect($totalMemoryIncrease)->toBeLessThan(100 * 1024 * 1024); // Less than 100MB after cleanup
        });
    });

    describe('Key Management Performance', function () {
        it('handles rapid key generation efficiently', function () {
            $keyCount = 100;
            $startTime = microtime(true);
            $keyPairs = [];
            $symmetricKeys = [];

            // Generate multiple key pairs
            for ($i = 0; $i < $keyCount; $i++) {
                $keyPair = $this->encryptionService->generateKeyPair();
                $symmetricKey = $this->encryptionService->generateSymmetricKey();
                
                $keyPairs[] = $keyPair;
                $symmetricKeys[] = $symmetricKey;
                
                // Verify key quality periodically
                if ($i % 20 === 19) {
                    expect(strlen($keyPair['public_key']))->toBeGreaterThan(400);
                    expect(strlen($keyPair['private_key']))->toBeGreaterThan(800);
                    expect(strlen($symmetricKey))->toBe(32);
                }
            }

            $totalTime = microtime(true) - $startTime;
            $avgTimePerKey = $totalTime / $keyCount;

            // Performance assertions
            expect($totalTime)->toBeLessThan(30.0); // All keys within 30 seconds
            expect($avgTimePerKey)->toBeLessThan(1.0); // Each key within 1 second on average

            // Verify all keys are unique
            $publicKeys = array_column($keyPairs, 'public_key');
            $privateKeys = array_column($keyPairs, 'private_key');
            
            expect(count(array_unique($publicKeys)))->toBe($keyCount);
            expect(count(array_unique($privateKeys)))->toBe($keyCount);
            expect(count(array_unique($symmetricKeys)))->toBe($keyCount);
        });

        it('manages large numbers of encryption keys efficiently', function () {
            $deviceCount = 50;
            $conversationCount = 20;
            $devices = [];
            $conversations = [];
            $encryptionKeys = [];

            // Setup multiple devices
            for ($i = 0; $i < $deviceCount; $i++) {
                $keyPair = $this->encryptionService->generateKeyPair();
                
                $device = $this->multiDeviceService->registerDevice(
                    $this->user1,
                    "Test Device {$i}",
                    'mobile',
                    $keyPair['public_key'],
                    "device_{$i}_" . uniqid(),
                    'iOS',
                    'Mozilla/5.0...',
                    ['messaging', 'encryption'],
                    'medium'
                );
                
                $devices[] = $device;
            }

            // Setup multiple conversations
            for ($i = 0; $i < $conversationCount; $i++) {
                $conversation = Conversation::factory()->create([
                    'type' => 'group',
                    'created_by' => $this->user1->id,
                ]);
                
                $conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
                $conversations[] = $conversation;
            }

            $startTime = microtime(true);

            // Create encryption keys for each device-conversation combination
            foreach ($conversations as $convIndex => $conversation) {
                $symmetricKey = $this->encryptionService->generateSymmetricKey();
                
                foreach ($devices as $devIndex => $device) {
                    $encryptionKey = EncryptionKey::createForDevice(
                        $conversation->id,
                        $this->user1->id,
                        $device->id,
                        $symmetricKey,
                        $device->public_key
                    );
                    
                    $encryptionKeys[] = $encryptionKey;
                }
            }

            $totalTime = microtime(true) - $startTime;
            $expectedKeyCount = $deviceCount * $conversationCount;

            // Performance and correctness checks
            expect($totalTime)->toBeLessThan(60.0); // Should complete within 1 minute
            expect(count($encryptionKeys))->toBe($expectedKeyCount);

            // Database verification
            $dbKeyCount = EncryptionKey::where('user_id', $this->user1->id)->count();
            expect($dbKeyCount)->toBe($expectedKeyCount);

            // Verify key retrieval performance
            $retrievalStartTime = microtime(true);
            
            foreach ($conversations as $conversation) {
                $conversationKeys = EncryptionKey::where('conversation_id', $conversation->id)->get();
                expect($conversationKeys->count())->toBe($deviceCount);
            }
            
            $retrievalTime = microtime(true) - $retrievalStartTime;
            expect($retrievalTime)->toBeLessThan(10.0); // Key retrieval should be fast
        });
    });

    describe('Database Performance Under Load', function () {
        it('handles concurrent database operations efficiently', function () {
            $operationCount = 100;
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $operationTimes = [];
            $successCount = 0;
            $errorCount = 0;

            // Simulate concurrent database operations
            for ($i = 0; $i < $operationCount; $i++) {
                $opStartTime = microtime(true);
                
                try {
                    DB::transaction(function () use ($i, $symmetricKey) {
                        // Create message
                        $message = Message::createEncrypted(
                            $this->conversation->id,
                            $this->user1->id,
                            "Concurrent message {$i}",
                            $symmetricKey
                        );

                        // Simulate some processing
                        $decrypted = $message->decryptContent($symmetricKey);
                        expect($decrypted)->toBe("Concurrent message {$i}");
                        
                        // Update conversation timestamp
                        $this->conversation->touch();
                    });
                    
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                }
                
                $opTime = microtime(true) - $opStartTime;
                $operationTimes[] = $opTime;
            }

            // Performance analysis
            $avgOpTime = array_sum($operationTimes) / count($operationTimes);
            $maxOpTime = max($operationTimes);
            $minOpTime = min($operationTimes);

            expect($successCount)->toBeGreaterThan(90); // At least 90% success rate
            expect($avgOpTime)->toBeLessThan(1.0); // Average operation under 1 second
            expect($maxOpTime)->toBeLessThan(5.0); // No operation should take more than 5 seconds

            // Verify data integrity
            $messageCount = Message::where('conversation_id', $this->conversation->id)->count();
            expect($messageCount)->toBe($successCount);
        });

        it('maintains query performance with large datasets', function () {
            $messageCount = 2000;
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create large dataset
            $createStartTime = microtime(true);
            
            for ($i = 0; $i < $messageCount; $i++) {
                Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Dataset message {$i}",
                    $symmetricKey
                );
                
                // Periodic progress check
                if ($i % 200 === 199) {
                    $elapsed = microtime(true) - $createStartTime;
                    expect($elapsed)->toBeLessThan(60.0); // Should stay under 1 minute per batch
                }
            }

            $createTime = microtime(true) - $createStartTime;
            expect($createTime)->toBeLessThan(120.0); // Total creation under 2 minutes

            // Test various query patterns
            $queryTests = [
                'count' => function () {
                    return Message::where('conversation_id', $this->conversation->id)->count();
                },
                'latest_10' => function () {
                    return Message::where('conversation_id', $this->conversation->id)
                        ->latest()
                        ->limit(10)
                        ->get();
                },
                'pagination' => function () {
                    return Message::where('conversation_id', $this->conversation->id)
                        ->orderBy('created_at', 'desc')
                        ->offset(100)
                        ->limit(50)
                        ->get();
                },
                'search_pattern' => function () {
                    return Message::where('conversation_id', $this->conversation->id)
                        ->where('content_hash', 'like', '%' . hash('sha256', 'Dataset message 100') . '%')
                        ->get();
                },
            ];

            foreach ($queryTests as $testName => $queryFunc) {
                $queryStartTime = microtime(true);
                $result = $queryFunc();
                $queryTime = microtime(true) - $queryStartTime;
                
                expect($queryTime)->toBeLessThan(5.0); // Each query under 5 seconds
                
                if ($testName === 'count') {
                    expect($result)->toBe($messageCount);
                } elseif (in_array($testName, ['latest_10', 'pagination'])) {
                    expect($result->count())->toBeGreaterThan(0);
                }
            }
        });
    });

    describe('Memory Management and Optimization', function () {
        it('handles memory-intensive operations efficiently', function () {
            $iterations = 100;
            $messageSize = 50 * 1024; // 50KB per message
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $initialMemory = memory_get_usage();
            $memoryReadings = [];

            for ($i = 0; $i < $iterations; $i++) {
                $content = "Memory test {$i}: " . str_repeat('M', $messageSize);
                
                // Encrypt
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );

                // Decrypt to verify
                $decrypted = $message->decryptContent($symmetricKey);
                expect(strlen($decrypted))->toBe(strlen($content));
                
                // Monitor memory
                $currentMemory = memory_get_usage();
                $memoryReadings[] = $currentMemory;
                
                // Cleanup every 10 iterations
                if ($i % 10 === 9) {
                    unset($content, $decrypted);
                    gc_collect_cycles();
                }
                
                // Check for memory leaks
                $memoryIncrease = $currentMemory - $initialMemory;
                expect($memoryIncrease)->toBeLessThan(200 * 1024 * 1024); // Less than 200MB growth
            }

            // Analyze memory usage pattern
            $finalMemory = memory_get_usage();
            $peakMemory = max($memoryReadings);
            $avgMemory = array_sum($memoryReadings) / count($memoryReadings);

            expect($finalMemory - $initialMemory)->toBeLessThan(100 * 1024 * 1024); // Final overhead under 100MB
            expect($peakMemory - $initialMemory)->toBeLessThan(300 * 1024 * 1024); // Peak under 300MB
        });

        it('optimizes garbage collection for large workloads', function () {
            $batchCount = 20;
            $messagesPerBatch = 50;
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $gcStats = [];
            
            for ($batch = 0; $batch < $batchCount; $batch++) {
                $batchStartMemory = memory_get_usage();
                $messages = [];
                
                // Create batch of messages
                for ($i = 0; $i < $messagesPerBatch; $i++) {
                    $content = "GC test batch {$batch} message {$i}";
                    $message = Message::createEncrypted(
                        $this->conversation->id,
                        $this->user1->id,
                        $content,
                        $symmetricKey
                    );
                    $messages[] = $message;
                }
                
                $beforeGcMemory = memory_get_usage();
                
                // Force garbage collection
                unset($messages);
                $collected = gc_collect_cycles();
                
                $afterGcMemory = memory_get_usage();
                
                $gcStats[] = [
                    'batch' => $batch,
                    'before_gc' => $beforeGcMemory,
                    'after_gc' => $afterGcMemory,
                    'collected' => $collected,
                    'freed' => $beforeGcMemory - $afterGcMemory,
                ];
                
                // Memory should be freed effectively
                $memoryFreed = $beforeGcMemory - $afterGcMemory;
                expect($memoryFreed)->toBeGreaterThanOrEqual(0); // Should free some memory
            }

            // Analyze GC effectiveness
            $totalFreed = array_sum(array_column($gcStats, 'freed'));
            $avgFreed = $totalFreed / count($gcStats);
            
            expect($avgFreed)->toBeGreaterThan(0); // Should free memory on average
        });
    });

    describe('Network Simulation and Load Testing', function () {
        it('simulates high network latency conditions', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messageCount = 50;
            $latencySimulation = 100; // milliseconds
            
            $messages = [];
            $totalTime = 0;

            for ($i = 0; $i < $messageCount; $i++) {
                $startTime = microtime(true);
                
                // Simulate network latency
                usleep($latencySimulation * 1000);
                
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Latency test message {$i}",
                    $symmetricKey
                );
                
                $messages[] = $message;
                
                $messageTime = microtime(true) - $startTime;
                $totalTime += $messageTime;
                
                // Even with latency, operation should complete reasonably
                expect($messageTime)->toBeLessThan(2.0);
            }

            expect(count($messages))->toBe($messageCount);
            expect($totalTime)->toBeLessThan($messageCount * 2.0);

            // Verify all messages are decryptable despite latency simulation
            foreach ($messages as $message) {
                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toStartWith('Latency test message');
            }
        });

        it('handles connection interruption scenarios', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $totalOperations = 100;
            $successCount = 0;
            $interruptionCount = 0;

            for ($i = 0; $i < $totalOperations; $i++) {
                try {
                    // Simulate occasional "connection interruption"
                    if (rand(1, 100) <= 10) { // 10% chance of interruption
                        $interruptionCount++;
                        throw new \Exception('Simulated connection interruption');
                    }

                    DB::transaction(function () use ($i, $symmetricKey) {
                        $message = Message::createEncrypted(
                            $this->conversation->id,
                            $this->user1->id,
                            "Interruption test {$i}",
                            $symmetricKey
                        );

                        // Verify immediately
                        $decrypted = $message->decryptContent($symmetricKey);
                        expect($decrypted)->toBe("Interruption test {$i}");
                    });

                    $successCount++;
                } catch (\Exception $e) {
                    // Handle interruption gracefully
                    if (!str_contains($e->getMessage(), 'Simulated connection interruption')) {
                        throw $e; // Re-throw unexpected exceptions
                    }
                }
            }

            // Most operations should succeed despite interruptions
            expect($successCount)->toBeGreaterThan(80);
            expect($interruptionCount)->toBeGreaterThan(5);
            expect($successCount + $interruptionCount)->toBe($totalOperations);

            // Verify database consistency
            $dbMessageCount = Message::where('conversation_id', $this->conversation->id)->count();
            expect($dbMessageCount)->toBe($successCount);
        });
    });
});