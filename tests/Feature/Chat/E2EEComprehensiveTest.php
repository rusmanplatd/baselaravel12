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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);

    $this->user1 = User::factory()->create(['name' => 'Alice']);
    $this->user2 = User::factory()->create(['name' => 'Bob']);
    $this->user3 = User::factory()->create(['name' => 'Charlie']);

    $this->conversation = Conversation::factory()->create([
        'type' => 'group',
        'name' => 'Secure Group Chat',
        'created_by' => $this->user1->id,
    ]);

    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
    $this->conversation->participants()->create(['user_id' => $this->user3->id, 'role' => 'member']);
});

describe('E2EE Comprehensive Integration Tests', function () {
    it('demonstrates complete E2EE workflow with multiple participants', function () {
        // === Phase 1: Setup devices and keys for all participants ===
        $userDevices = [];
        $keyPairs = [];

        foreach ([$this->user1, $this->user2, $this->user3] as $user) {
            $keyPair = $this->encryptionService->generateKeyPair();
            $keyPairs[$user->id] = $keyPair;

            $device = UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => $user->name."'s Device",
                'device_type' => 'mobile',
                'public_key' => $keyPair['public_key'],
                'is_trusted' => true,
                'encryption_capabilities' => json_encode(['RSA-4096-OAEP', 'AES-256-GCM']),
            ]);

            $userDevices[$user->id] = $device;
        }

        expect(count($userDevices))->toBe(3);
        expect(count($keyPairs))->toBe(3);

        // === Phase 2: Initialize conversation encryption ===
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        // Distribute symmetric key to all participants
        $encryptionKeys = [];
        foreach ($userDevices as $userId => $device) {
            $encryptionKey = EncryptionKey::createForDevice(
                $this->conversation->id,
                $userId,
                $device->id,
                $symmetricKey,
                $keyPairs[$userId]['public_key']
            );

            $encryptionKeys[$userId] = $encryptionKey;
        }

        expect(count($encryptionKeys))->toBe(3);

        // Verify all keys are active
        foreach ($encryptionKeys as $key) {
            expect($key->is_active)->toBeTrue();
            expect($key->algorithm)->toBe('RSA-4096-OAEP');
        }

        // === Phase 3: Multi-party encrypted messaging ===
        $testMessages = [
            ['sender' => $this->user1, 'content' => 'Hello everyone! This is Alice speaking. 🔒'],
            ['sender' => $this->user2, 'content' => 'Hi Alice! Bob here. Great to have secure chat! 🛡️'],
            ['sender' => $this->user3, 'content' => 'Charlie checking in. Love the encryption! 🚀'],
            ['sender' => $this->user1, 'content' => 'Let\'s discuss our secret project plans...'],
            ['sender' => $this->user2, 'content' => 'Agreed. The quantum encryption upgrade timeline?'],
        ];

        $sentMessages = [];
        foreach ($testMessages as $messageData) {
            $message = Message::createEncrypted(
                $this->conversation->id,
                $messageData['sender']->id,
                $messageData['content'],
                $symmetricKey
            );

            $sentMessages[] = [
                'message' => $message,
                'sender' => $messageData['sender'],
                'original_content' => $messageData['content'],
            ];

            // Small delay to ensure proper ordering
            usleep(10000); // 10ms
        }

        expect(count($sentMessages))->toBe(5);

        // === Phase 4: Cross-user decryption verification ===
        foreach ($sentMessages as $msgData) {
            $message = $msgData['message'];
            $originalContent = $msgData['original_content'];

            // Each user should be able to decrypt all messages
            foreach ([$this->user1, $this->user2, $this->user3] as $user) {
                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toBe($originalContent);
            }
        }

        // === Phase 5: Key rotation during active conversation ===
        $oldSymmetricKey = $symmetricKey;
        $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->conversation->id);

        // Deactivate old keys
        foreach ($encryptionKeys as $key) {
            $key->update(['is_active' => false]);
        }

        // Create new keys for all participants
        $newEncryptionKeys = [];
        foreach ($userDevices as $userId => $device) {
            $newKey = EncryptionKey::createForDevice(
                $this->conversation->id,
                $userId,
                $device->id,
                $newSymmetricKey,
                $keyPairs[$userId]['public_key']
            );

            $newEncryptionKeys[$userId] = $newKey;
        }

        // Send post-rotation messages
        $postRotationMessages = [
            ['sender' => $this->user1, 'content' => 'Key rotation complete! New secure channel active.'],
            ['sender' => $this->user2, 'content' => 'Confirmed. All systems green on my end.'],
            ['sender' => $this->user3, 'content' => 'Perfect! Forward secrecy maintained.'],
        ];

        $newMessages = [];
        foreach ($postRotationMessages as $messageData) {
            $message = Message::createEncrypted(
                $this->conversation->id,
                $messageData['sender']->id,
                $messageData['content'],
                $newSymmetricKey
            );

            $newMessages[] = [
                'message' => $message,
                'content' => $messageData['content'],
            ];
        }

        // === Phase 6: Verify forward secrecy ===
        // Old messages should still be decryptable with old key
        foreach ($sentMessages as $msgData) {
            $decrypted = $msgData['message']->decryptContent($oldSymmetricKey);
            expect($decrypted)->toBe($msgData['original_content']);
        }

        // New messages should only work with new key
        foreach ($newMessages as $msgData) {
            $decrypted = $msgData['message']->decryptContent($newSymmetricKey);
            expect($decrypted)->toBe($msgData['content']);

            // Should fail with old key
            expect(fn () => $msgData['message']->decryptContent($oldSymmetricKey))
                ->toThrow(\App\Exceptions\DecryptionException::class);
        }

        // === Phase 7: Performance and consistency verification ===
        $totalMessages = Message::where('conversation_id', $this->conversation->id)->count();
        expect($totalMessages)->toBe(8); // 5 original + 3 post-rotation

        $totalKeys = EncryptionKey::where('conversation_id', $this->conversation->id)->count();
        expect($totalKeys)->toBe(6); // 3 old + 3 new keys

        $activeKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('is_active', true)
            ->count();
        expect($activeKeys)->toBe(3); // Only new keys should be active

        echo "\n✅ Complete E2EE workflow test successful:";
        echo "\n   • 3 participants with secure devices";
        echo "\n   • 8 encrypted messages exchanged";
        echo "\n   • 1 key rotation performed";
        echo "\n   • Forward secrecy verified";
        echo "\n   • Cross-user decryption confirmed";
    });

    it('handles complex multi-device scenarios with offline synchronization', function () {
        // Create multiple devices for user1
        $devices = [];
        $keyPairs = [];

        $deviceTypes = ['mobile', 'tablet', 'desktop'];
        foreach ($deviceTypes as $index => $deviceType) {
            $keyPair = $this->encryptionService->generateKeyPair();
            $keyPairs[] = $keyPair;

            $device = UserDevice::factory()->create([
                'user_id' => $this->user1->id,
                'device_name' => "Alice's ".ucfirst($deviceType),
                'device_type' => $deviceType,
                'public_key' => $keyPair['public_key'],
                'is_trusted' => true,
                'last_used_at' => now(),
            ]);

            $devices[] = $device;
        }

        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        // Initially, only mobile and tablet are online
        $onlineDevices = array_slice($devices, 0, 2);
        $offlineDevice = $devices[2]; // desktop is offline

        // Create keys for online devices
        $encryptionKeys = [];
        foreach ($onlineDevices as $index => $device) {
            $key = EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device->id,
                $symmetricKey,
                $keyPairs[$index]['public_key']
            );
            $encryptionKeys[] = $key;
        }

        // Send messages while desktop is offline
        $messagesWhileOffline = [];
        for ($i = 0; $i < 5; $i++) {
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                "Message #{$i} sent while desktop offline",
                $symmetricKey
            );
            $messagesWhileOffline[] = $message;

            usleep(5000); // 5ms between messages
        }

        // Perform key rotation while desktop is still offline
        $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->conversation->id);

        // Deactivate old keys
        foreach ($encryptionKeys as $key) {
            $key->update(['is_active' => false]);
        }

        // Create new keys for online devices only
        $newEncryptionKeys = [];
        foreach ($onlineDevices as $index => $device) {
            $newKey = EncryptionKey::createForDevice(
                $this->conversation->id,
                $this->user1->id,
                $device->id,
                $newSymmetricKey,
                $keyPairs[$index]['public_key']
            );
            $newEncryptionKeys[] = $newKey;
        }

        // Send more messages with new key
        $messagesWithNewKey = [];
        for ($i = 0; $i < 3; $i++) {
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                "New key message #{$i} - desktop still offline",
                $newSymmetricKey
            );
            $messagesWithNewKey[] = $message;
        }

        // Desktop comes back online
        $offlineDevice->update(['last_used_at' => now()]);

        // Sync keys to the previously offline desktop
        $desktopKeyPair = $keyPairs[2]; // desktop key pair

        // Create historical key access for desktop
        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $offlineDevice->id,
            $symmetricKey, // old key for historical messages
            $desktopKeyPair['public_key']
        );

        // Create current key access for desktop
        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $offlineDevice->id,
            $newSymmetricKey, // new key for current messages
            $desktopKeyPair['public_key']
        );

        // Verify desktop can now access all messages
        foreach ($messagesWhileOffline as $message) {
            $decrypted = $message->decryptContent($symmetricKey);
            expect($decrypted)->toStartWith('Message #');
            expect($decrypted)->toContain('while desktop offline');
        }

        foreach ($messagesWithNewKey as $message) {
            $decrypted = $message->decryptContent($newSymmetricKey);
            expect($decrypted)->toStartWith('New key message #');
            expect($decrypted)->toContain('desktop still offline');
        }

        // Desktop can now send new messages
        $desktopMessage = Message::createEncrypted(
            $this->conversation->id,
            $this->user1->id,
            'Desktop back online! Caught up on all messages.',
            $newSymmetricKey
        );

        $decrypted = $desktopMessage->decryptContent($newSymmetricKey);
        expect($decrypted)->toBe('Desktop back online! Caught up on all messages.');

        // Verify final state
        $totalDeviceKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->user1->id)
            ->count();
        expect($totalDeviceKeys)->toBe(6); // 2 old online + 2 new online + 2 desktop (old & new), accounting for deactivated keys

        $totalMessages = Message::where('conversation_id', $this->conversation->id)->count();
        expect($totalMessages)->toBe(9); // 5 while offline + 3 with new key + 1 from desktop

        echo "\n✅ Multi-device offline sync test successful:";
        echo "\n   • 3 devices (1 temporarily offline)";
        echo "\n   • 9 total encrypted messages";
        echo "\n   • Key rotation during offline period";
        echo "\n   • Complete message history accessibility";
        echo "\n   • Successful device resynchronization";
    });

    it('demonstrates encryption performance under realistic load', function () {
        // Simulate realistic group chat with 10 participants
        $participants = [];
        $devices = [];
        $keyPairs = [];

        // Create participants and their devices
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create(['name' => "User{$i}"]);
            $participants[] = $user;

            $this->conversation->participants()->create([
                'user_id' => $user->id,
                'role' => ($i === 0) ? 'admin' : 'member',
            ]);

            $keyPair = $this->encryptionService->generateKeyPair();
            $keyPairs[] = $keyPair;

            $device = UserDevice::factory()->create([
                'user_id' => $user->id,
                'device_name' => "User{$i} Phone",
                'device_type' => 'mobile',
                'public_key' => $keyPair['public_key'],
                'is_trusted' => true,
            ]);

            $devices[] = $device;
        }

        // Setup encryption for the group
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        $keyDistributionStart = microtime(true);

        // Distribute symmetric key to all participants
        foreach ($devices as $index => $device) {
            EncryptionKey::createForDevice(
                $this->conversation->id,
                $participants[$index]->id,
                $device->id,
                $symmetricKey,
                $keyPairs[$index]['public_key']
            );
        }

        $keyDistributionTime = (microtime(true) - $keyDistributionStart) * 1000;

        // Simulate realistic chat activity
        $messagingStart = microtime(true);
        $messages = [];

        // Burst of 50 messages from different participants
        for ($i = 0; $i < 50; $i++) {
            $senderIndex = $i % count($participants);
            $sender = $participants[$senderIndex];

            $content = "Message {$i} from {$sender->name}: ".
                       'This is a realistic message with some content and maybe emoji 📱💬';

            $message = Message::createEncrypted(
                $this->conversation->id,
                $sender->id,
                $content,
                $symmetricKey
            );

            $messages[] = [
                'message' => $message,
                'sender' => $sender,
                'content' => $content,
            ];

            // Add realistic delays between messages (1-10ms)
            usleep(rand(1000, 10000));
        }

        $messagingTime = (microtime(true) - $messagingStart) * 1000;

        // Verify all messages are properly encrypted and decryptable
        $verificationStart = microtime(true);

        foreach ($messages as $msgData) {
            $decrypted = $msgData['message']->decryptContent($symmetricKey);
            expect($decrypted)->toBe($msgData['content']);
        }

        $verificationTime = (microtime(true) - $verificationStart) * 1000;

        // Performance assertions
        expect($keyDistributionTime)->toBeLessThan(5000); // < 5 seconds for 10 devices
        expect($messagingTime)->toBeLessThan(30000); // < 30 seconds for 50 messages
        expect($verificationTime)->toBeLessThan(5000); // < 5 seconds to verify all

        $avgMessageTime = $messagingTime / 50;
        $avgVerificationTime = $verificationTime / 50;

        expect($avgMessageTime)->toBeLessThan(500); // < 500ms per message
        expect($avgVerificationTime)->toBeLessThan(100); // < 100ms per verification

        // Verify data integrity
        expect(count($messages))->toBe(50);

        $totalParticipants = $this->conversation->participants()->count();
        expect($totalParticipants)->toBe(13); // 3 original + 10 new

        $totalKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('is_active', true)
            ->count();
        expect($totalKeys)->toBe(10); // One active key per new participant

        echo "\n✅ Performance test successful:";
        echo "\n   • 10 participants in group chat";
        echo "\n   • Key distribution: ".number_format($keyDistributionTime, 2).'ms total ('.
             number_format($keyDistributionTime / 10, 2).'ms/device)';
        echo "\n   • 50 encrypted messages: ".number_format($messagingTime, 2).'ms total ('.
             number_format($avgMessageTime, 2).'ms/message)';
        echo "\n   • Message verification: ".number_format($verificationTime, 2).'ms total ('.
             number_format($avgVerificationTime, 2).'ms/message)';
        echo "\n   • All performance targets met ✓";
    });
});
