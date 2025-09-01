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

describe('E2EE Stress Testing', function () {
    describe('High Volume Message Processing', function () {
        it('handles burst message encryption efficiently', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $burstSize = 500;
            $maxExecutionTime = 60; // 60 seconds

            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            $messages = [];
            $errors = [];

            // Create burst of messages
            for ($i = 0; $i < $burstSize; $i++) {
                try {
                    $content = "Burst message #{$i} - ".str_repeat('data', rand(10, 100));

                    $message = Message::createEncrypted(
                        $this->conversation->id,
                        $this->user1->id,
                        $content,
                        $symmetricKey
                    );

                    $messages[] = [
                        'id' => $message->id,
                        'original_content' => $content,
                    ];

                    // Check memory usage periodically
                    if ($i % 100 === 0) {
                        $currentMemory = memory_get_usage();
                        $memoryUsed = ($currentMemory - $startMemory) / 1024 / 1024; // MB
                        expect($memoryUsed)->toBeLessThan(100); // Less than 100MB
                    }

                } catch (\Exception $e) {
                    $errors[] = "Message {$i}: ".$e->getMessage();
                }
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $finalMemory = memory_get_usage();
            $memoryUsed = ($finalMemory - $startMemory) / 1024 / 1024;

            // Performance assertions
            expect($executionTime)->toBeLessThan($maxExecutionTime);
            expect(count($messages))->toBe($burstSize);
            expect($errors)->toBeEmpty();
            expect($memoryUsed)->toBeLessThan(150); // Less than 150MB total

            // Verify message integrity with random sampling
            $sampleIndices = array_rand($messages, min(10, count($messages)));
            if (! is_array($sampleIndices)) {
                $sampleIndices = [$sampleIndices];
            }

            foreach ($sampleIndices as $index) {
                $messageData = $messages[$index];
                $message = Message::find($messageData['id']);

                $decryptedContent = $message->decryptContent($symmetricKey);
                expect($decryptedContent)->toBe($messageData['original_content']);
            }

            echo "\nBurst test completed: {$burstSize} messages in ".number_format($executionTime, 2).'s using '.number_format($memoryUsed, 2).'MB';
        });

        it('maintains performance under sustained load', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $batchSize = 50;
            $numberOfBatches = 10;
            $totalMessages = $batchSize * $numberOfBatches;

            $batchTimes = [];
            $totalStartTime = microtime(true);

            for ($batch = 0; $batch < $numberOfBatches; $batch++) {
                $batchStartTime = microtime(true);
                $batchMessages = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $messageIndex = ($batch * $batchSize) + $i;
                    $content = "Sustained load message #{$messageIndex}";

                    $message = Message::createEncrypted(
                        $this->conversation->id,
                        $this->user1->id,
                        $content,
                        $symmetricKey
                    );

                    $batchMessages[] = $message;
                }

                $batchEndTime = microtime(true);
                $batchTime = $batchEndTime - $batchStartTime;
                $batchTimes[] = $batchTime;

                // Verify batch integrity
                expect(count($batchMessages))->toBe($batchSize);

                // Small delay between batches to simulate real usage
                usleep(100000); // 100ms
            }

            $totalEndTime = microtime(true);
            $totalTime = $totalEndTime - $totalStartTime;

            // Performance analysis
            $avgBatchTime = array_sum($batchTimes) / count($batchTimes);
            $maxBatchTime = max($batchTimes);
            $minBatchTime = min($batchTimes);
            $timeVariation = $maxBatchTime - $minBatchTime;

            expect($totalTime)->toBeLessThan(120); // 2 minutes total
            expect($avgBatchTime)->toBeLessThan(10); // 10 seconds per batch average
            expect($timeVariation)->toBeLessThan($avgBatchTime * 2); // Consistent performance

            // Verify total message count
            $messageCount = Message::where('conversation_id', $this->conversation->id)->count();
            expect($messageCount)->toBe($totalMessages);

            echo "\nSustained load test: {$totalMessages} messages in ".number_format($totalTime, 2).'s, avg batch time '.number_format($avgBatchTime, 2).'s';
        });
    });

    describe('Concurrent User Scenarios', function () {
        it('handles multiple users sending simultaneously', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $messagesPerUser = 20;
            $users = [$this->user1, $this->user2, $this->user3];

            $allMessages = [];
            $userMessages = [];
            $errors = [];

            $startTime = microtime(true);

            // Simulate concurrent sending by interleaving user messages
            for ($round = 0; $round < $messagesPerUser; $round++) {
                foreach ($users as $userIndex => $user) {
                    try {
                        $content = "User {$userIndex} message #{$round} - concurrent test";

                        $message = Message::createEncrypted(
                            $this->conversation->id,
                            $user->id,
                            $content,
                            $symmetricKey
                        );

                        $allMessages[] = $message;
                        $userMessages[$user->id][] = [
                            'message' => $message,
                            'content' => $content,
                        ];

                    } catch (\Exception $e) {
                        $errors[] = "User {$userIndex}, Round {$round}: ".$e->getMessage();
                    }
                }

                // Small delay to simulate network timing
                if ($round % 5 === 0) {
                    usleep(10000); // 10ms every 5 rounds
                }
            }

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            expect(count($allMessages))->toBe($messagesPerUser * count($users));
            expect($errors)->toBeEmpty();
            expect($totalTime)->toBeLessThan(30); // 30 seconds

            // Verify each user's messages
            foreach ($users as $user) {
                expect($userMessages)->toHaveKey($user->id);
                expect(count($userMessages[$user->id]))->toBe($messagesPerUser);

                // Verify decryption for sample messages
                $sampleSize = min(5, count($userMessages[$user->id]));
                $samples = array_slice($userMessages[$user->id], 0, $sampleSize);

                foreach ($samples as $messageData) {
                    $decrypted = $messageData['message']->decryptContent($symmetricKey);
                    expect($decrypted)->toBe($messageData['content']);
                }
            }

            echo "\nConcurrent users test: ".count($allMessages).' messages from '.count($users).' users in '.number_format($totalTime, 2).'s';
        });

        it('handles rapid key rotation under load', function () {
            $initialKey = $this->encryptionService->generateSymmetricKey();
            $rotationInterval = 10; // Rotate every 10 messages
            $totalMessages = 100;

            $currentKey = $initialKey;
            $messages = [];
            $keyRotations = [];
            $errors = [];

            $startTime = microtime(true);

            for ($i = 0; $i < $totalMessages; $i++) {
                try {
                    // Rotate key periodically
                    if ($i > 0 && $i % $rotationInterval === 0) {
                        $newKey = $this->encryptionService->rotateSymmetricKey($this->conversation->id);
                        $keyRotations[] = [
                            'message_index' => $i,
                            'old_key' => $currentKey,
                            'new_key' => $newKey,
                        ];
                        $currentKey = $newKey;
                    }

                    $content = "Message #{$i} with rotation test";

                    $message = Message::createEncrypted(
                        $this->conversation->id,
                        $this->user1->id,
                        $content,
                        $currentKey
                    );

                    $messages[] = [
                        'message' => $message,
                        'content' => $content,
                        'key' => $currentKey,
                        'message_index' => $i,
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Message {$i}: ".$e->getMessage();
                }
            }

            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;

            expect(count($messages))->toBe($totalMessages);
            expect($errors)->toBeEmpty();
            expect($totalTime)->toBeLessThan(60); // 1 minute
            expect(count($keyRotations))->toBe(intval($totalMessages / $rotationInterval));

            // Verify messages can be decrypted with their respective keys
            foreach ($messages as $messageData) {
                $decrypted = $messageData['message']->decryptContent($messageData['key']);
                expect($decrypted)->toBe($messageData['content']);
            }

            // Verify key rotation worked (old keys can't decrypt new messages)
            if (count($keyRotations) > 0) {
                $firstRotation = $keyRotations[0];
                $messageAfterRotation = $messages[$firstRotation['message_index']];

                expect(fn () => $messageAfterRotation['message']->decryptContent($firstRotation['old_key']))
                    ->toThrow(\App\Exceptions\DecryptionException::class);
            }

            echo "\nKey rotation test: {$totalMessages} messages with ".count($keyRotations).' rotations in '.number_format($totalTime, 2).'s';
        });
    });

    describe('Resource Exhaustion Recovery', function () {
        it('recovers gracefully from memory pressure', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $largeContentSize = 1024 * 1024; // 1MB per message
            $messageCount = 10;

            $startMemory = memory_get_usage();
            $messages = [];
            $memoryReadings = [];

            for ($i = 0; $i < $messageCount; $i++) {
                $largeContent = "Message #{$i} - ".str_repeat('X', $largeContentSize);

                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $largeContent,
                    $symmetricKey
                );

                $messages[] = [
                    'id' => $message->id,
                    'size' => strlen($largeContent),
                ];

                $currentMemory = memory_get_usage();
                $memoryReadings[] = $currentMemory;

                // Force garbage collection every few messages
                if ($i % 3 === 0) {
                    unset($largeContent);
                    gc_collect_cycles();
                }
            }

            $endMemory = memory_get_usage();
            $memoryIncrease = ($endMemory - $startMemory) / 1024 / 1024; // MB

            // Memory should not grow excessively
            expect($memoryIncrease)->toBeLessThan(200); // Less than 200MB increase

            // Verify all messages were created and are retrievable
            expect(count($messages))->toBe($messageCount);

            // Spot check message integrity
            $testMessage = Message::find($messages[0]['id']);
            $decrypted = $testMessage->decryptContent($symmetricKey);
            expect(strlen($decrypted))->toBeGreaterThan($largeContentSize);
            expect($decrypted)->toStartWith('Message #0');

            echo "\nMemory pressure test: {$messageCount} large messages, memory increase: ".number_format($memoryIncrease, 2).'MB';
        });

        it('handles database connection pool exhaustion', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $concurrentOperations = 20;
            $operationsPerBatch = 10;

            $results = [];
            $errors = [];

            // Simulate high database load
            for ($batch = 0; $batch < $concurrentOperations; $batch++) {
                $batchResults = [];

                DB::transaction(function () use ($symmetricKey, $operationsPerBatch, &$batchResults, $batch) {
                    for ($op = 0; $op < $operationsPerBatch; $op++) {
                        $content = "DB stress test - Batch {$batch}, Op {$op}";

                        $message = Message::createEncrypted(
                            $this->conversation->id,
                            $this->user1->id,
                            $content,
                            $symmetricKey
                        );

                        $batchResults[] = [
                            'message_id' => $message->id,
                            'content' => $content,
                        ];

                        // Create additional database load
                        EncryptionKey::where('conversation_id', $this->conversation->id)->count();
                    }
                });

                $results = array_merge($results, $batchResults);

                // Brief pause to simulate real-world timing
                usleep(5000); // 5ms
            }

            $totalOperations = $concurrentOperations * $operationsPerBatch;
            expect(count($results))->toBe($totalOperations);

            // Verify data integrity
            $sampleIndices = array_rand($results, min(5, count($results)));
            if (! is_array($sampleIndices)) {
                $sampleIndices = [$sampleIndices];
            }

            foreach ($sampleIndices as $index) {
                $result = $results[$index];
                $message = Message::find($result['message_id']);

                expect($message)->not()->toBeNull();

                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toBe($result['content']);
            }

            echo "\nDB stress test: {$totalOperations} operations completed successfully";
        });
    });

    describe('Long-Running Operations', function () {
        it('maintains encryption quality over extended periods', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $testDuration = 30; // 30 seconds
            $messageInterval = 0.5; // 500ms between messages

            $startTime = time();
            $messages = [];
            $qualityMetrics = [];

            $messageCounter = 0;
            while ((time() - $startTime) < $testDuration) {
                $content = "Extended test message #{$messageCounter} at ".date('H:i:s');

                $encryptionStart = microtime(true);
                $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);
                $encryptionTime = (microtime(true) - $encryptionStart) * 1000; // ms

                $decryptionStart = microtime(true);
                $decrypted = $this->encryptionService->decryptMessage(
                    $encrypted['data'], $encrypted['iv'], $symmetricKey
                );
                $decryptionTime = (microtime(true) - $decryptionStart) * 1000; // ms

                expect($decrypted)->toBe($content);

                $qualityMetrics[] = [
                    'encryption_time_ms' => $encryptionTime,
                    'decryption_time_ms' => $decryptionTime,
                    'iv_uniqueness' => $encrypted['iv'],
                    'data_size' => strlen($encrypted['data']),
                ];

                $messageCounter++;
                usleep($messageInterval * 1000000); // Convert to microseconds
            }

            $actualDuration = time() - $startTime;
            expect($actualDuration)->toBeGreaterThanOrEqual($testDuration - 2); // Allow 2s variance
            expect(count($qualityMetrics))->toBeGreaterThan(0);

            // Analyze performance consistency
            $encryptionTimes = array_column($qualityMetrics, 'encryption_time_ms');
            $decryptionTimes = array_column($qualityMetrics, 'decryption_time_ms');
            $ivs = array_column($qualityMetrics, 'iv_uniqueness');

            $avgEncryptionTime = array_sum($encryptionTimes) / count($encryptionTimes);
            $avgDecryptionTime = array_sum($decryptionTimes) / count($decryptionTimes);

            expect($avgEncryptionTime)->toBeLessThan(100); // Less than 100ms average
            expect($avgDecryptionTime)->toBeLessThan(50);  // Less than 50ms average

            // Verify IV uniqueness (no repeats)
            $uniqueIVs = array_unique($ivs);
            expect(count($uniqueIVs))->toBe(count($ivs));

            echo "\nExtended test: {$messageCounter} messages over {$actualDuration}s, avg encrypt: ".number_format($avgEncryptionTime, 2).'ms, decrypt: '.number_format($avgDecryptionTime, 2).'ms';
        });
    });
});
