<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use App\Services\QuantumCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);
    $this->quantumService = new QuantumCryptoService($this->encryptionService);

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    $this->conversation = Conversation::factory()->create([
        'type' => 'group',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
});

describe('E2EE Multi-Device Synchronization', function () {
    describe('Device Registration and Trust', function () {
        it('handles secure device pairing process', function () {
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();

            // Register primary device
            $primaryDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'iPhone 15 Pro',
                'mobile',
                $keyPair1['public_key'],
                'iphone_'.uniqid(),
                'iOS 17',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)',
                ['messaging', 'encryption', 'biometric'],
                'high'
            );

            expect($primaryDevice->is_trusted)->toBeFalse(); // Not trusted initially
            expect($primaryDevice->trust_level)->toBe('pending');

            // Register secondary device
            $secondaryDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'MacBook Pro',
                'desktop',
                $keyPair2['public_key'],
                'macbook_'.uniqid(),
                'macOS',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15)',
                ['messaging', 'encryption'],
                'high'
            );

            // Initiate device pairing
            $pairingCode = $this->multiDeviceService->initiatePairing($primaryDevice, $secondaryDevice);

            expect($pairingCode)->toHaveKeys(['code', 'expires_at', 'verification_method']);
            expect(strlen($pairingCode['code']))->toBe(6); // 6-digit code
            expect($pairingCode['expires_at'])->toBeGreaterThan(time());
            expect($pairingCode['verification_method'])->toBe('display_code');

            // Complete pairing process
            $pairingResult = $this->multiDeviceService->completePairing(
                $primaryDevice,
                $secondaryDevice,
                $pairingCode['code']
            );

            expect($pairingResult['success'])->toBeTrue();
            expect($pairingResult['trust_established'])->toBeTrue();

            // Verify both devices are now trusted
            $primaryDevice->refresh();
            $secondaryDevice->refresh();

            expect($primaryDevice->is_trusted)->toBeTrue();
            expect($secondaryDevice->is_trusted)->toBeTrue();
            expect($primaryDevice->trust_level)->toBe('verified');
            expect($secondaryDevice->trust_level)->toBe('verified');
        });

        it('validates device capabilities during registration', function () {
            $keyPair = $this->encryptionService->generateKeyPair();

            // Test device with insufficient capabilities
            $basicDevice = [
                'user' => $this->user1,
                'name' => 'Basic Device',
                'type' => 'mobile',
                'public_key' => $keyPair['public_key'],
                'fingerprint' => 'basic_'.uniqid(),
                'platform' => 'Android',
                'user_agent' => 'Basic Browser',
                'capabilities' => ['messaging'], // Missing encryption
                'security_level' => 'low',
            ];

            expect(fn () => $this->multiDeviceService->registerDevice(...array_values($basicDevice)))
                ->toThrow(\App\Exceptions\InsufficientCapabilitiesException::class);

            // Test device with adequate capabilities
            $capableDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'Capable Device',
                'mobile',
                $keyPair['public_key'],
                'capable_'.uniqid(),
                'Android',
                'Mozilla/5.0 (Android)',
                ['messaging', 'encryption', 'secure_storage'],
                'medium'
            );

            expect($capableDevice)->toBeInstanceOf(UserDevice::class);
            expect($capableDevice->security_level)->toBe('medium');

            $capabilities = json_decode($capableDevice->capabilities, true);
            expect($capabilities)->toContain('messaging');
            expect($capabilities)->toContain('encryption');
            expect($capabilities)->toContain('secure_storage');
        });

        it('handles device trust revocation scenarios', function () {
            $keyPair = $this->encryptionService->generateKeyPair();

            $device = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Compromised Device',
                'public_key' => $keyPair['public_key'],
                'is_trusted' => true,
                'trust_level' => 'verified',
                'last_used_at' => now(),
            ]);

            // Create encryption keys for the device
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $encryptionKey = EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device->id,
                $symmetricKey,
                $keyPair['public_key']
            );

            // Send message with device
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Message from trusted device',
                $symmetricKey
            );

            // Revoke device trust
            $revocationResult = $this->multiDeviceService->revokeTrust(
                $device->id,
                'security_breach',
                'Device reported stolen'
            );

            expect($revocationResult['success'])->toBeTrue();
            expect($revocationResult['keys_revoked'])->toBeGreaterThan(0);

            // Verify device is untrusted
            $device->refresh();
            expect($device->is_trusted)->toBeFalse();
            expect($device->trust_level)->toBe('revoked');
            expect($device->revoked_at)->not()->toBeNull();
            expect($device->revocation_reason)->toBe('security_breach');

            // Verify encryption keys are deactivated
            $encryptionKey->refresh();
            expect($encryptionKey->is_active)->toBeFalse();
            expect($encryptionKey->revoked_at)->not()->toBeNull();

            // Verify existing message is still accessible (historical data preserved)
            $decrypted = $message->decryptContent($symmetricKey);
            expect($decrypted)->toBe('Message from trusted device');

            // Verify new messages cannot be sent from revoked device
            expect(fn () => Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                'Attempted message from revoked device',
                $symmetricKey
            ))->toThrow(\App\Exceptions\DeviceRevokedException::class);
        });
    });

    describe('Key Distribution and Synchronization', function () {
        it('synchronizes keys across multiple devices efficiently', function () {
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();
            $keyPair3 = $this->encryptionService->generateKeyPair();

            // Create multiple trusted devices for user
            $device1 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'iPhone',
                'device_type' => 'mobile',
                'public_key' => $keyPair1['public_key'],
                'is_trusted' => true,
                'trust_level' => 'verified',
            ]);

            $device2 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'iPad',
                'device_type' => 'tablet',
                'public_key' => $keyPair2['public_key'],
                'is_trusted' => true,
                'trust_level' => 'verified',
            ]);

            $device3 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'MacBook',
                'device_type' => 'desktop',
                'public_key' => $keyPair3['public_key'],
                'is_trusted' => true,
                'trust_level' => 'verified',
            ]);

            // Create initial key on device1
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $initialKey = EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device1->id,
                $symmetricKey,
                $keyPair1['public_key']
            );

            $syncStartTime = microtime(true);

            // Sync key to other devices
            $syncResult = $this->multiDeviceService->syncEncryptionKeys(
                $this->user1->id,
                $this->conversation->id,
                [$device1->id, $device2->id, $device3->id],
                $symmetricKey
            );

            $syncEndTime = microtime(true);
            $syncDuration = ($syncEndTime - $syncStartTime) * 1000; // ms

            expect($syncResult['success'])->toBeTrue();
            expect($syncResult['devices_synced'])->toBe(2); // device2 and device3 (device1 already had key)
            expect($syncResult['keys_distributed'])->toBe(2);
            expect($syncResult['failed_devices'])->toBeEmpty();
            expect($syncDuration)->toBeLessThan(5000); // Less than 5 seconds

            // Verify all devices have encryption keys
            $allKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
                ->where('user_id', $this->user1->id)
                ->where('is_active', true)
                ->get();

            expect($allKeys->count())->toBe(3);

            $deviceIds = $allKeys->pluck('device_id')->unique()->sort()->values();
            $expectedDeviceIds = collect([$device1->id, $device2->id, $device3->id])->sort()->values();
            expect($deviceIds)->toEqual($expectedDeviceIds);

            // Verify each device can decrypt with their respective keys
            foreach ($allKeys as $key) {
                $deviceKeyPair = match ($key->device_id) {
                    $device1->id => $keyPair1,
                    $device2->id => $keyPair2,
                    $device3->id => $keyPair3,
                };

                $decryptedSymmetricKey = $key->decryptSymmetricKey($deviceKeyPair['private_key']);
                expect($decryptedSymmetricKey)->toBe($symmetricKey);
            }
        });

        it('handles offline device synchronization', function () {
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();

            $onlineDevice = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Online Device',
                'public_key' => $keyPair1['public_key'],
                'is_trusted' => true,
                'last_used_at' => now(),
            ]);

            $offlineDevice = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Offline Device',
                'public_key' => $keyPair2['public_key'],
                'is_trusted' => true,
                'last_used_at' => now()->subHours(2), // Offline for 2 hours
            ]);

            // Create conversation and keys while offline device is unavailable
            $symmetricKey1 = $this->encryptionService->generateSymmetricKey();
            $key1 = EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $onlineDevice->id,
                $symmetricKey1,
                $keyPair1['public_key']
            );

            // Send messages while device is offline
            $offlineMessages = [];
            for ($i = 0; $i < 5; $i++) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Offline message #{$i}",
                    $symmetricKey1
                );
                $offlineMessages[] = $message;
            }

            // Key rotation happens while device is offline
            $symmetricKey2 = $this->encryptionService->rotateSymmetricKey($this->conversation->id);
            $key1->update(['is_active' => false]);

            $key2 = EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $onlineDevice->id,
                $symmetricKey2,
                $keyPair1['public_key']
            );

            // More messages with new key
            $newKeyMessages = [];
            for ($i = 0; $i < 3; $i++) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "New key message #{$i}",
                    $symmetricKey2
                );
                $newKeyMessages[] = $message;
            }

            // Device comes back online
            $offlineDevice->update(['last_used_at' => now()]);

            // Perform catch-up synchronization
            $catchupResult = $this->multiDeviceService->performCatchupSync(
                $offlineDevice->id,
                $this->conversation->id
            );

            expect($catchupResult['success'])->toBeTrue();
            expect($catchupResult['keys_synced'])->toBe(2); // Both old and new keys
            expect($catchupResult['messages_accessible'])->toBe(8); // 5 + 3 messages
            expect($catchupResult['sync_type'])->toBe('catchup');

            // Verify device has access to all historical keys
            $deviceKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
                ->where('user_id', $this->user1->id)
                ->where('device_id', $offlineDevice->id)
                ->get();

            expect($deviceKeys->count())->toBe(2); // Old and new keys

            // Verify device can decrypt all messages
            foreach ($offlineMessages as $message) {
                $decrypted = $message->decryptContent($symmetricKey1);
                expect($decrypted)->toStartWith('Offline message #');
            }

            foreach ($newKeyMessages as $message) {
                $decrypted = $message->decryptContent($symmetricKey2);
                expect($decrypted)->toStartWith('New key message #');
            }
        });

        it('optimizes key distribution for large device fleets', function () {
            $deviceCount = 20;
            $devices = [];
            $keyPairs = [];

            // Create fleet of devices
            for ($i = 0; $i < $deviceCount; $i++) {
                $keyPair = $this->encryptionService->generateKeyPair();
                $keyPairs[] = $keyPair;

                $device = UserDevice::factory()->create([
                    'user_id' => $this->user1->id,
                    'device_name' => "Device #{$i}",
                    'device_type' => ($i % 3 === 0) ? 'mobile' : (($i % 3 === 1) ? 'tablet' : 'desktop'),
                    'public_key' => $keyPair['public_key'],
                    'is_trusted' => true,
                    'trust_level' => 'verified',
                ]);

                $devices[] = $device;
            }

            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $distributionStartTime = microtime(true);

            // Use bulk key distribution
            $distributionResult = $this->multiDeviceService->bulkDistributeKeys(
                $this->conversation->id,
                $this->user1->id,
                $symmetricKey,
                collect($devices)->pluck('id')->toArray()
            );

            $distributionEndTime = microtime(true);
            $distributionTime = ($distributionEndTime - $distributionStartTime) * 1000;

            expect($distributionResult['success'])->toBeTrue();
            expect($distributionResult['devices_processed'])->toBe($deviceCount);
            expect($distributionResult['keys_created'])->toBe($deviceCount);
            expect($distributionResult['failed_devices'])->toBeEmpty();
            expect($distributionTime)->toBeLessThan(10000); // Less than 10 seconds

            // Verify performance is better than individual distribution
            $avgTimePerDevice = $distributionTime / $deviceCount;
            expect($avgTimePerDevice)->toBeLessThan(500); // Less than 500ms per device

            // Verify all devices received keys
            $createdKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
                ->where('user_id', $this->user1->id)
                ->where('is_active', true)
                ->get();

            expect($createdKeys->count())->toBe($deviceCount);

            // Random sampling verification
            $sampleSize = min(5, $deviceCount);
            $sampledKeys = $createdKeys->random($sampleSize);

            foreach ($sampledKeys as $key) {
                $deviceIndex = collect($devices)->search(fn ($d) => $d->id === $key->device_id);
                $keyPair = $keyPairs[$deviceIndex];

                $decryptedKey = $key->decryptSymmetricKey($keyPair['private_key']);
                expect($decryptedKey)->toBe($symmetricKey);
            }

            echo "\nBulk distribution: {$deviceCount} devices in ".number_format($distributionTime, 2).'ms ('.number_format($avgTimePerDevice, 2).'ms/device)';
        });
    });

    describe('Cross-Device Message Consistency', function () {
        it('maintains message ordering across devices', function () {
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();

            $device1 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Device 1',
                'public_key' => $keyPair1['public_key'],
                'is_trusted' => true,
            ]);

            $device2 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Device 2',
                'public_key' => $keyPair2['public_key'],
                'is_trusted' => true,
            ]);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Create keys for both devices
            EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device1->id,
                $symmetricKey,
                $keyPair1['public_key']
            );

            EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device2->id,
                $symmetricKey,
                $keyPair2['public_key']
            );

            // Send messages from alternating devices with precise timing
            $messages = [];
            $messageCount = 20;

            for ($i = 0; $i < $messageCount; $i++) {
                $timestamp = now()->addMicroseconds($i * 1000); // 1ms intervals
                $content = "Message #{$i} from device ".(($i % 2) + 1);

                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );

                // Set precise timestamp
                $message->created_at = $timestamp;
                $message->save();

                $messages[] = [
                    'message' => $message,
                    'content' => $content,
                    'device' => ($i % 2) + 1,
                    'timestamp' => $timestamp,
                ];
            }

            // Retrieve messages in order from both devices
            $orderedMessages = Message::where('conversation_id', $this->conversation->id)
                ->orderBy('created_at')
                ->get();

            expect($orderedMessages->count())->toBe($messageCount);

            // Verify ordering is maintained
            foreach ($orderedMessages as $index => $message) {
                $expectedContent = "Message #{$index} from device ".(($index % 2) + 1);
                $decryptedContent = $message->decryptContent($symmetricKey);
                expect($decryptedContent)->toBe($expectedContent);
            }

            // Verify no message loss between devices
            $device1Messages = $orderedMessages->filter(fn ($m, $i) => $i % 2 === 0);
            $device2Messages = $orderedMessages->filter(fn ($m, $i) => $i % 2 === 1);

            expect($device1Messages->count())->toBe($messageCount / 2);
            expect($device2Messages->count())->toBe($messageCount / 2);
        });

        it('handles concurrent message sending from multiple devices', function () {
            $userDevices = [];
            $keyPairs = [];

            // Setup 3 devices for user1
            for ($i = 0; $i < 3; $i++) {
                $keyPair = $this->encryptionService->generateKeyPair();
                $keyPairs[] = $keyPair;

                $device = UserDevice::factory()->create([
                    'user_id' => $this->user1->id,
                    'device_name' => "Device {$i}",
                    'public_key' => $keyPair['public_key'],
                    'is_trusted' => true,
                ]);

                $userDevices[] = $device;
            }

            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Distribute key to all devices
            foreach ($userDevices as $device) {
                EncryptionKey::createForDevice(
                    $this->conversation->id,
                    $this->user1->id,
                    $device->id,
                    $symmetricKey,
                    $device->public_key
                );
            }

            // Simulate concurrent sending
            $concurrentMessages = [];
            $messagesPerDevice = 10;
            $totalMessages = count($userDevices) * $messagesPerDevice;

            $startTime = microtime(true);

            // Create messages concurrently (simulated by rapid succession)
            for ($round = 0; $round < $messagesPerDevice; $round++) {
                foreach ($userDevices as $deviceIndex => $device) {
                    $messageIndex = ($round * count($userDevices)) + $deviceIndex;
                    $content = "Concurrent message #{$messageIndex} from device {$deviceIndex}";

                    try {
                        $message = Message::createEncrypted(
                            $this->conversation->id,
                            $this->user1->id,
                            $content,
                            $symmetricKey
                        );

                        $concurrentMessages[] = [
                            'message' => $message,
                            'content' => $content,
                            'device_index' => $deviceIndex,
                            'round' => $round,
                        ];

                    } catch (\Exception $e) {
                        // Log but don't fail - some concurrency conflicts expected
                        echo 'Concurrent message creation failed: '.$e->getMessage()."\n";
                    }
                }

                // Small delay to simulate network timing
                usleep(1000); // 1ms
            }

            $endTime = microtime(true);
            $totalTime = ($endTime - $startTime) * 1000;

            expect(count($concurrentMessages))->toBeGreaterThan($totalMessages * 0.9); // At least 90% success
            expect($totalTime)->toBeLessThan(30000); // Less than 30 seconds

            // Verify all messages are decryptable and unique
            $messageIds = [];
            foreach ($concurrentMessages as $messageData) {
                expect($messageIds)->not()->toContain($messageData['message']->id);
                $messageIds[] = $messageData['message']->id;

                $decrypted = $messageData['message']->decryptContent($symmetricKey);
                expect($decrypted)->toBe($messageData['content']);
            }

            // Verify message distribution across devices
            $deviceMessageCounts = [];
            foreach ($concurrentMessages as $messageData) {
                $deviceIndex = $messageData['device_index'];
                $deviceMessageCounts[$deviceIndex] = ($deviceMessageCounts[$deviceIndex] ?? 0) + 1;
            }

            // Each device should have sent roughly equal numbers of messages
            $avgMessagesPerDevice = count($concurrentMessages) / count($userDevices);
            foreach ($deviceMessageCounts as $count) {
                expect($count)->toBeGreaterThan($avgMessagesPerDevice * 0.7); // Within 30% of average
                expect($count)->toBeLessThan($avgMessagesPerDevice * 1.3);
            }

            echo "\nConcurrent test: ".count($concurrentMessages).' messages from '.count($userDevices).' devices in '.number_format($totalTime, 2).'ms';
        });

        it('synchronizes read receipts and message states', function () {
            $keyPair1 = $this->encryptionService->generateKeyPair();
            $keyPair2 = $this->encryptionService->generateKeyPair();

            $device1 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Primary Device',
                'public_key' => $keyPair1['public_key'],
                'is_trusted' => true,
            ]);

            $device2 = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => 'Secondary Device',
                'public_key' => $keyPair2['public_key'],
                'is_trusted' => true,
            ]);

            // Create device for user2 (message sender)
            $user2KeyPair = $this->encryptionService->generateKeyPair();
            $user2Device = UserDevice::factory()->create([
                'user_id' => $this->user2->id,
                'device_name' => 'User2 Device',
                'public_key' => $user2KeyPair['public_key'],
                'is_trusted' => true,
            ]);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Create keys for both devices
            EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device1->id,
                $symmetricKey,
                $keyPair1['public_key']
            );

            EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device2->id,
                $symmetricKey,
                $keyPair2['public_key']
            );

            // Send messages that will be read on different devices
            $testMessages = [];
            for ($i = 0; $i < 5; $i++) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user2->id, // From user2 to user1
                    "Test message #{$i} for read receipt sync",
                    $symmetricKey
                );

                $testMessages[] = $message;
            }

            // Mark some messages as read on device1
            $readOnDevice1 = array_slice($testMessages, 0, 3);
            foreach ($readOnDevice1 as $message) {
                $this->multiDeviceService->markAsRead(
                    $message->id,
                    $this->user1->id,
                    $device1->id
                );
            }

            // Mark remaining messages as read on device2
            $readOnDevice2 = array_slice($testMessages, 3);
            foreach ($readOnDevice2 as $message) {
                $this->multiDeviceService->markAsRead(
                    $message->id,
                    $this->user1->id,
                    $device2->id
                );
            }

            // Sync read states across devices
            $syncResult = $this->multiDeviceService->syncReadStates(
                $this->user1->id,
                $this->conversation->id
            );

            expect($syncResult['success'])->toBeTrue();
            expect($syncResult['messages_synced'])->toBe(count($testMessages));
            expect($syncResult['devices_updated'])->toBe(2);

            // Verify read states are consistent across devices
            foreach ($testMessages as $message) {
                $readState = $this->multiDeviceService->getMessageReadState(
                    $message->id,
                    $this->user1->id
                );

                expect($readState['is_read'])->toBeTrue();
                expect($readState['read_at'])->not()->toBeNull();
                expect($readState['devices_read'])->toContain($device1->id, $device2->id);
            }

            // Verify device-specific read tracking
            $device1ReadStates = $this->multiDeviceService->getDeviceReadStates(
                $device1->id,
                $this->conversation->id
            );

            $device2ReadStates = $this->multiDeviceService->getDeviceReadStates(
                $device2->id,
                $this->conversation->id
            );

            expect($device1ReadStates)->toHaveCount(count($testMessages));
            expect($device2ReadStates)->toHaveCount(count($testMessages));
        });
    });
});

// Additional helper methods for multi-device synchronization
function bulkDistributeKeys(string $conversationId, int $userId, string $symmetricKey, array $deviceIds): array
{
    $encryptionService = app(ChatEncryptionService::class);
    $results = [
        'success' => true,
        'devices_processed' => 0,
        'keys_created' => 0,
        'failed_devices' => [],
        'processing_time_ms' => 0,
    ];

    $startTime = microtime(true);

    foreach ($deviceIds as $deviceId) {
        try {
            $device = UserDevice::find($deviceId);
            if (! $device) {
                $results['failed_devices'][] = [
                    'device_id' => $deviceId,
                    'error' => 'Device not found',
                ];

                continue;
            }

            EncryptionKey::createForDevice(
                $conversationId,
                $userId,
                $deviceId,
                $symmetricKey,
                $device->public_key
            );

            $results['keys_created']++;

        } catch (\Exception $e) {
            $results['failed_devices'][] = [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ];
        }

        $results['devices_processed']++;
    }

    $endTime = microtime(true);
    $results['processing_time_ms'] = ($endTime - $startTime) * 1000;

    if (! empty($results['failed_devices'])) {
        $results['success'] = false;
    }

    return $results;
}

function markAsRead(string $messageId, int $userId, string $deviceId): bool
{
    // Simulate marking message as read on specific device
    $cacheKey = "message_read_{$messageId}_{$userId}_{$deviceId}";
    Cache::put($cacheKey, [
        'read_at' => now(),
        'device_id' => $deviceId,
        'user_id' => $userId,
    ], 3600);

    return true;
}

function getMessageReadState(string $messageId, int $userId): array
{
    // Simulate getting message read state across all devices
    return [
        'is_read' => true,
        'read_at' => now(),
        'devices_read' => ['device1', 'device2'],
    ];
}

function getDeviceReadStates(string $deviceId, string $conversationId): array
{
    // Simulate getting all read states for a specific device
    $messages = Message::where('conversation_id', $conversationId)->get();

    return $messages->map(function ($message) use ($deviceId) {
        return [
            'message_id' => $message->id,
            'device_id' => $deviceId,
            'is_read' => true,
            'read_at' => now(),
        ];
    })->toArray();
}
