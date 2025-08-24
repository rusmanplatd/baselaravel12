<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\DeviceKeyShare;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    // Create devices for user1
    $keyPair1_device1 = $this->encryptionService->generateKeyPair();
    $keyPair1_device2 = $this->encryptionService->generateKeyPair();

    $this->user1_device1 = UserDevice::create([
        'user_id' => $this->user1->id,
        'device_name' => 'iPhone 15',
        'device_type' => 'mobile',
        'public_key' => $keyPair1_device1['public_key'],
        'device_fingerprint' => 'device1_fingerprint_'.uniqid(),
        'platform' => 'iOS',
        'device_capabilities' => ['messaging', 'encryption'],
        'security_level' => 'high',
        'encryption_version' => 2,
        'is_trusted' => true,
        'is_active' => true,
        'verified_at' => now(),
    ]);

    $this->user1_device2 = UserDevice::create([
        'user_id' => $this->user1->id,
        'device_name' => 'MacBook Pro',
        'device_type' => 'desktop',
        'public_key' => $keyPair1_device2['public_key'],
        'device_fingerprint' => 'device2_fingerprint_'.uniqid(),
        'platform' => 'macOS',
        'device_capabilities' => ['messaging', 'encryption'],
        'security_level' => 'medium',
        'encryption_version' => 2,
        'is_trusted' => false,
        'is_active' => true,
    ]);

    // Create device for user2
    $keyPair2_device1 = $this->encryptionService->generateKeyPair();
    $this->user2_device1 = UserDevice::create([
        'user_id' => $this->user2->id,
        'device_name' => 'Android Phone',
        'device_type' => 'mobile',
        'public_key' => $keyPair2_device1['public_key'],
        'device_fingerprint' => 'user2_device1_fingerprint_'.uniqid(),
        'platform' => 'Android',
        'device_capabilities' => ['messaging', 'encryption'],
        'security_level' => 'high',
        'encryption_version' => 2,
        'is_trusted' => true,
        'is_active' => true,
        'verified_at' => now(),
    ]);

    $this->conversation = Conversation::factory()->direct()->create();
    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
});

describe('Multi-Device Registration and Management', function () {
    it('can register a new device for user', function () {
        $user = User::factory()->create();
        $keyPair = $this->encryptionService->generateKeyPair();

        $device = $this->multiDeviceService->registerDevice(
            $user,
            'Test Device',
            'desktop',
            $keyPair['public_key'],
            'test_fingerprint_'.uniqid(),
            'Windows',
            'Mozilla/5.0...',
            ['messaging', 'encryption'],
            'medium',
            ['os' => 'Windows 11']
        );

        expect($device)->toBeInstanceOf(UserDevice::class);
        expect($device->user_id)->toBe($user->id);
        expect($device->device_name)->toBe('Test Device');
        expect($device->device_type)->toBe('desktop');
        expect($device->is_trusted)->toBeFalse();
        expect($device->is_active)->toBeTrue();

        $this->assertDatabaseHas('user_devices', [
            'id' => $device->id,
            'user_id' => $user->id,
            'device_name' => 'Test Device',
        ]);
    });

    it('updates existing device when registering with same fingerprint', function () {
        $user = User::factory()->create();
        $keyPair1 = $this->encryptionService->generateKeyPair();
        $keyPair2 = $this->encryptionService->generateKeyPair();

        $fingerprint = 'same_fingerprint_'.uniqid();

        // Register device first time
        $device1 = $this->multiDeviceService->registerDevice(
            $user,
            'Old Name',
            'mobile',
            $keyPair1['public_key'],
            $fingerprint,
            'iOS',
            'Mozilla/5.0...',
            ['messaging', 'encryption'],
            'medium'
        );

        // Register again with same fingerprint
        $device2 = $this->multiDeviceService->registerDevice(
            $user,
            'New Name',
            'desktop',
            $keyPair2['public_key'],
            $fingerprint,
            'macOS',
            'Mozilla/5.0...',
            ['messaging', 'encryption', 'file_sharing'],
            'high'
        );

        expect($device1->id)->toBe($device2->id);
        expect($device2->device_name)->toBe('New Name');
        expect($device2->device_type)->toBe('desktop');
        expect($device2->public_key)->toBe($keyPair2['public_key']);

        // Should only have one device record
        expect($user->devices()->count())->toBe(1);
    });
});

describe('Multi-Device Key Sharing', function () {
    it('can share keys from trusted device to new device', function () {
        // Create encryption keys for trusted device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey,
            $this->user1_device1->public_key,
            $this->user1_device1->device_fingerprint
        );

        $results = $this->multiDeviceService->shareKeysWithNewDevice(
            $this->user1_device1,
            $this->user1_device2
        );

        expect($results['total_keys_shared'])->toBe(1);
        expect($results['shared_conversations'])->toHaveCount(1);
        expect($results['failed_conversations'])->toBeEmpty();

        // Verify key share record was created
        $keyShare = DeviceKeyShare::where('from_device_id', $this->user1_device1->id)
            ->where('to_device_id', $this->user1_device2->id)
            ->first();

        expect($keyShare)->not()->toBeNull();
        expect($keyShare->conversation_id)->toBe($this->conversation->id);
        expect($keyShare->is_accepted)->toBeFalse();
        expect($keyShare->is_active)->toBeTrue();
    });

    it('cannot share keys between devices of different users', function () {
        expect(fn () => $this->multiDeviceService->shareKeysWithNewDevice(
            $this->user1_device1,
            $this->user2_device1
        ))->toThrow(\InvalidArgumentException::class, 'Devices must belong to the same user');
    });

    it('can accept key share and create encryption key', function () {
        // Create a key share with future expiration
        $keyShare = DeviceKeyShare::create([
            'from_device_id' => $this->user1_device1->id,
            'to_device_id' => $this->user1_device2->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'encrypted_symmetric_key' => 'encrypted_key_data',
            'from_device_public_key' => $this->user1_device1->public_key,
            'to_device_public_key' => $this->user1_device2->public_key,
            'key_version' => 1,
            'share_method' => 'device_to_device',
            'is_accepted' => false,
            'is_active' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        $encryptionKey = $this->multiDeviceService->acceptKeyShare(
            $this->user1_device2,
            $keyShare,
            $symmetricKey
        );

        expect($encryptionKey)->toBeInstanceOf(EncryptionKey::class);
        expect($encryptionKey->device_id)->toBe($this->user1_device2->id);
        expect($encryptionKey->conversation_id)->toBe($this->conversation->id);
        expect($encryptionKey->is_active)->toBeTrue();

        // Verify key share is marked as accepted
        $keyShare->refresh();
        expect($keyShare->is_accepted)->toBeTrue();
        expect($keyShare->accepted_at)->not()->toBeNull();
    });
});

describe('Multi-Device Conversation Key Management', function () {
    it('can setup conversation encryption for multiple devices', function () {
        $deviceKeys = [
            ['device_id' => $this->user1_device1->id],
            ['device_id' => $this->user1_device2->id],
            ['device_id' => $this->user2_device1->id],
        ];

        $results = $this->multiDeviceService->setupConversationEncryptionForDevices(
            $this->conversation,
            $deviceKeys,
            $this->user1_device1
        );

        expect($results['created_keys'])->toHaveCount(3);
        expect($results['failed_keys'])->toBeEmpty();
        expect($results['key_version'])->toBe(1);

        // Verify encryption keys were created for all devices
        expect(EncryptionKey::where('conversation_id', $this->conversation->id)->count())->toBe(3);

        foreach ($deviceKeys as $deviceData) {
            $key = EncryptionKey::where('conversation_id', $this->conversation->id)
                ->where('device_id', $deviceData['device_id'])
                ->first();

            expect($key)->not()->toBeNull();
            expect($key->is_active)->toBeTrue();
            expect($key->key_version)->toBe(1);
            expect($key->created_by_device_id)->toBe($this->user1_device1->id);
        }
    });

    it('can rotate conversation keys for all participant devices', function () {
        // Setup initial encryption keys for all three devices
        $deviceKeys = [
            ['device_id' => $this->user1_device1->id],
            ['device_id' => $this->user1_device2->id], // Include second device initially
            ['device_id' => $this->user2_device1->id],
        ];

        // Trust user1's second device first
        $this->user1_device2->markAsTrusted();
        $this->user1_device2->refresh(); // Refresh to get updated timestamp

        $this->multiDeviceService->setupConversationEncryptionForDevices(
            $this->conversation,
            $deviceKeys,
            $this->user1_device1
        );

        $results = $this->multiDeviceService->rotateConversationKeys(
            $this->conversation,
            $this->user1_device1
        );

        expect($results['key_version'])->toBe(2);
        expect($results['rotated_devices'])->toHaveCount(3); // All trusted devices should get new keys
        expect($results['failed_devices'])->toBeEmpty();

        // Verify old keys are deactivated
        $oldKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('key_version', 1)
            ->get();

        foreach ($oldKeys as $key) {
            expect($key->is_active)->toBeFalse();
        }

        // Verify new keys are active
        $newKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('key_version', 2)
            ->get();

        expect($newKeys->count())->toBe(3);
        foreach ($newKeys as $key) {
            expect($key->is_active)->toBeTrue();
        }
    });
});

describe('Device Access Management', function () {
    it('can revoke device access to all conversations', function () {
        // Create encryption keys for device
        $symmetricKey1 = $this->encryptionService->generateSymmetricKey();
        $symmetricKey2 = $this->encryptionService->generateSymmetricKey();

        $conversation2 = Conversation::factory()->direct()->create();
        $conversation2->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey1,
            $this->user1_device1->public_key
        );

        EncryptionKey::createForDevice(
            $conversation2->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey2,
            $this->user1_device1->public_key
        );

        $results = $this->multiDeviceService->revokeDeviceAccess($this->user1_device1);

        expect($results['revoked_keys'])->toBe(2);

        // Verify all keys for device are deactivated
        $keys = EncryptionKey::where('device_id', $this->user1_device1->id)->get();
        foreach ($keys as $key) {
            expect($key->is_active)->toBeFalse();
        }
    });

    it('can revoke device access to specific conversation only', function () {
        // Create encryption keys for device in multiple conversations
        $symmetricKey1 = $this->encryptionService->generateSymmetricKey();
        $symmetricKey2 = $this->encryptionService->generateSymmetricKey();

        $conversation2 = Conversation::factory()->direct()->create();
        $conversation2->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey1,
            $this->user1_device1->public_key
        );

        EncryptionKey::createForDevice(
            $conversation2->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey2,
            $this->user1_device1->public_key
        );

        $results = $this->multiDeviceService->revokeDeviceAccess(
            $this->user1_device1,
            $this->conversation->id
        );

        expect($results['revoked_keys'])->toBe(1);

        // Verify only the specific conversation key is deactivated
        $key1 = EncryptionKey::where('device_id', $this->user1_device1->id)
            ->where('conversation_id', $this->conversation->id)
            ->first();
        expect($key1->is_active)->toBeFalse();

        $key2 = EncryptionKey::where('device_id', $this->user1_device1->id)
            ->where('conversation_id', $conversation2->id)
            ->first();
        expect($key2->is_active)->toBeTrue();
    });

    it('deactivates device and all related keys when device is deactivated', function () {
        // Create encryption key for device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey,
            $this->user1_device1->public_key
        );

        // Create pending key share
        DeviceKeyShare::create([
            'from_device_id' => $this->user1_device1->id,
            'to_device_id' => $this->user1_device2->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'encrypted_symmetric_key' => 'encrypted_key_data',
            'from_device_public_key' => $this->user1_device1->public_key,
            'to_device_public_key' => $this->user1_device2->public_key,
            'key_version' => 1,
        ]);

        $this->user1_device1->deactivate();

        // Verify device is deactivated
        $this->user1_device1->refresh();
        expect($this->user1_device1->is_active)->toBeFalse();

        // Verify encryption keys are deactivated
        $keys = EncryptionKey::where('device_id', $this->user1_device1->id)->get();
        foreach ($keys as $key) {
            expect($key->is_active)->toBeFalse();
        }

        // Verify pending key shares are cancelled
        $keyShares = DeviceKeyShare::where('from_device_id', $this->user1_device1->id)->get();
        foreach ($keyShares as $share) {
            expect($share->is_active)->toBeFalse();
        }
    });
});

describe('Multi-Device Utility Functions', function () {
    it('can get device encryption summary', function () {
        // Create encryption keys for device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey,
            $this->user1_device1->public_key
        );

        // Create pending key share
        DeviceKeyShare::create([
            'from_device_id' => $this->user1_device2->id,
            'to_device_id' => $this->user1_device1->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'encrypted_symmetric_key' => 'encrypted_key_data',
            'from_device_public_key' => $this->user1_device2->public_key,
            'to_device_public_key' => $this->user1_device1->public_key,
            'key_version' => 1,
        ]);

        $summary = $this->multiDeviceService->getDeviceEncryptionSummary($this->user1_device1);

        expect($summary['device_id'])->toBe($this->user1_device1->id);
        expect($summary['device_name'])->toBe($this->user1_device1->display_name);
        expect($summary['is_trusted'])->toBe($this->user1_device1->is_trusted);
        expect($summary['active_conversation_keys'])->toBe(1);
        expect($summary['pending_key_shares'])->toBe(1);
        expect($summary['last_used'])->toBe($this->user1_device1->last_used_at);
    });

    it('can cleanup expired key shares', function () {
        // Create expired key share
        DeviceKeyShare::create([
            'from_device_id' => $this->user1_device1->id,
            'to_device_id' => $this->user1_device2->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'encrypted_symmetric_key' => 'encrypted_key_data',
            'from_device_public_key' => $this->user1_device1->public_key,
            'to_device_public_key' => $this->user1_device2->public_key,
            'key_version' => 1,
            'expires_at' => now()->subDay(),
            'is_accepted' => false,
        ]);

        // Create non-expired key share
        DeviceKeyShare::create([
            'from_device_id' => $this->user1_device1->id,
            'to_device_id' => $this->user1_device2->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'encrypted_symmetric_key' => 'encrypted_key_data2',
            'from_device_public_key' => $this->user1_device1->public_key,
            'to_device_public_key' => $this->user1_device2->public_key,
            'key_version' => 1,
            'expires_at' => now()->addDay(),
            'is_accepted' => false,
        ]);

        $cleaned = $this->multiDeviceService->cleanupExpiredKeyShares();

        expect($cleaned)->toBe(1);

        // Verify only expired share was deactivated
        $expiredShare = DeviceKeyShare::where('expires_at', '<', now())->first();
        expect($expiredShare->is_active)->toBeFalse();

        $activeShare = DeviceKeyShare::where('expires_at', '>', now())->first();
        expect($activeShare->is_active)->toBeTrue();
    });
});

describe('User Model Multi-Device Methods', function () {
    it('can get device by fingerprint', function () {
        $device = $this->user1->getDeviceByFingerprint($this->user1_device1->device_fingerprint);

        expect($device)->not()->toBeNull();
        expect($device->id)->toBe($this->user1_device1->id);
    });

    it('can get active encryption key for conversation and device', function () {
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey,
            $this->user1_device1->public_key
        );

        $key = $this->user1->getActiveEncryptionKeyForConversationAndDevice(
            $this->conversation->id,
            $this->user1_device1->id
        );

        expect($key)->not()->toBeNull();
        expect($key->conversation_id)->toBe($this->conversation->id);
        expect($key->device_id)->toBe($this->user1_device1->id);
        expect($key->is_active)->toBeTrue();
    });

    it('can check if device has access to conversation', function () {
        expect($this->user1->hasDeviceAccessToConversation(
            $this->conversation->id,
            $this->user1_device1->id
        ))->toBeFalse();

        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user1->id,
            $this->user1_device1->id,
            $symmetricKey,
            $this->user1_device1->public_key
        );

        expect($this->user1->hasDeviceAccessToConversation(
            $this->conversation->id,
            $this->user1_device1->id
        ))->toBeTrue();
    });
});
