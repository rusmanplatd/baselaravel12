<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\DeviceKeyShare;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create devices for the user
    $keyPair1 = $this->encryptionService->generateKeyPair();
    $keyPair2 = $this->encryptionService->generateKeyPair();

    $this->trustedDevice = UserDevice::create([
        'user_id' => $this->user->id,
        'device_name' => 'iPhone 15',
        'device_type' => 'mobile',
        'public_key' => $keyPair1['public_key'],
        'device_fingerprint' => 'trusted_device_'.uniqid(),
        'platform' => 'iOS',
        'device_capabilities' => ['messaging', 'encryption'],
        'security_level' => 'high',
        'encryption_version' => 2,
        'is_trusted' => true,
        'is_active' => true,
        'verified_at' => now(),
    ]);

    $this->untrustedDevice = UserDevice::create([
        'user_id' => $this->user->id,
        'device_name' => 'MacBook Pro',
        'device_type' => 'desktop',
        'public_key' => $keyPair2['public_key'],
        'device_fingerprint' => 'untrusted_device_'.uniqid(),
        'platform' => 'macOS',
        'device_capabilities' => ['messaging', 'encryption'],
        'security_level' => 'medium',
        'encryption_version' => 2,
        'is_trusted' => false,
        'is_active' => true,
    ]);

    $this->conversation = Conversation::factory()->direct()->create();
    $this->conversation->participants()->create(['user_id' => $this->user->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->otherUser->id, 'role' => 'member']);
});

describe('Device Management API', function () {
    it('can list user devices', function () {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/chat/devices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'devices' => [
                    '*' => [
                        'id',
                        'device_name',
                        'device_type',
                        'platform',
                        'short_fingerprint',
                        'is_trusted',
                        'last_used_at',
                        'created_at',
                        'is_current',
                    ],
                ],
                'total',
            ]);

        $devices = $response->json('devices');
        expect(count($devices))->toBe(2);
    });

    it('can register new device', function () {
        $keyPair = $this->encryptionService->generateKeyPair();

        $deviceData = [
            'device_name' => 'Test Device',
            'device_type' => 'tablet',
            'public_key' => $keyPair['public_key'],
            'device_fingerprint' => 'test_device_'.uniqid(),
            'platform' => 'iPad',
            'user_agent' => 'Mozilla/5.0...',
            'device_capabilities' => ['messaging', 'encryption'],
            'security_level' => 'medium',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/chat/devices', $deviceData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Device registered successfully',
                'device' => [
                    'device_name' => 'Test Device',
                    'device_type' => 'tablet',
                    'is_trusted' => false,
                    'requires_verification' => true,
                    'security_level' => 'medium',
                    'device_capabilities' => ['messaging', 'encryption'],
                ],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'device_name' => 'Test Device',
            'device_type' => 'tablet',
            'device_fingerprint' => $deviceData['device_fingerprint'],
        ]);
    });

    it('can get device details', function () {
        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/chat/devices/{$this->trustedDevice->id}");

        $response->assertStatus(200)
            ->assertJson([
                'device' => [
                    'device_id' => $this->trustedDevice->id,
                    'device_name' => $this->trustedDevice->display_name,
                    'is_trusted' => true,
                    'device_fingerprint' => $this->trustedDevice->device_fingerprint,
                ],
            ]);
    });

    it('can update device', function () {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/chat/devices/{$this->trustedDevice->id}", [
                'device_name' => 'Updated iPhone',
                'platform' => 'iOS 17',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device updated successfully',
                'device' => [
                    'device_name' => 'Updated iPhone',
                    'platform' => 'iOS 17',
                ],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'id' => $this->trustedDevice->id,
            'device_name' => 'Updated iPhone',
            'platform' => 'iOS 17',
        ]);
    });

    it('can trust device', function () {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/devices/{$this->untrustedDevice->id}/trust");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device marked as trusted',
                'device' => [
                    'is_trusted' => true,
                ],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'id' => $this->untrustedDevice->id,
            'is_trusted' => true,
        ]);
    });

    it('can remove device', function () {
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/chat/devices/{$this->untrustedDevice->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device removed successfully',
            ]);

        $this->assertDatabaseHas('user_devices', [
            'id' => $this->untrustedDevice->id,
            'is_active' => false,
        ]);
    });

    it('cannot access other user devices', function () {
        $otherUserDevice = UserDevice::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/chat/devices/{$otherUserDevice->id}");

        $response->assertStatus(403);
    });
});

describe('Key Sharing API', function () {
    it('can initiate key sharing from trusted device to new device', function () {
        // Create encryption key for trusted device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user->id,
            $this->trustedDevice->id,
            $symmetricKey,
            $this->trustedDevice->public_key,
            $this->trustedDevice->device_fingerprint
        );

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/devices/{$this->untrustedDevice->id}/share-keys", [
                'from_device_fingerprint' => $this->trustedDevice->device_fingerprint,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Key sharing initiated',
                'total_keys_shared' => 1,
            ]);

        // Verify key share record was created
        $this->assertDatabaseHas('device_key_shares', [
            'from_device_id' => $this->trustedDevice->id,
            'to_device_id' => $this->untrustedDevice->id,
            'conversation_id' => $this->conversation->id,
            'is_accepted' => false,
        ]);
    });

    it('can get key shares for device', function () {
        // Create a key share
        $keyShare = DeviceKeyShare::create([
            'from_device_id' => $this->trustedDevice->id,
            'to_device_id' => $this->untrustedDevice->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'encrypted_symmetric_key' => 'encrypted_key_data',
            'from_device_public_key' => $this->trustedDevice->public_key,
            'to_device_public_key' => $this->untrustedDevice->public_key,
            'key_version' => 1,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/chat/devices/{$this->untrustedDevice->id}/key-shares");

        $response->assertStatus(200)
            ->assertJson([
                'key_shares' => [
                    [
                        'id' => $keyShare->id,
                        'conversation_id' => $this->conversation->id,
                        'from_device_name' => $this->trustedDevice->display_name,
                        'share_method' => 'device_to_device',
                    ],
                ],
                'total' => 1,
            ]);
    });

    it('cannot share keys from untrusted device', function () {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/devices/{$this->trustedDevice->id}/share-keys", [
                'from_device_fingerprint' => $this->untrustedDevice->device_fingerprint,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Source device is not trusted',
            ]);
    });
});

describe('Multi-Device Conversation Encryption API', function () {
    it('can setup conversation encryption for multiple devices', function () {
        $otherUserDevice = UserDevice::factory()->create([
            'user_id' => $this->otherUser->id,
            'is_trusted' => true,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption-multidevice", [
                'device_keys' => [
                    ['device_id' => $this->trustedDevice->id],
                    ['device_id' => $this->untrustedDevice->id],
                    ['device_id' => $otherUserDevice->id],
                ],
                'initiating_device_id' => $this->trustedDevice->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'key_version',
                'created_keys',
                'failed_keys',
                'setup_id',
                'timestamp',
            ])
            ->assertJson([
                'key_version' => 1,
            ]);

        $createdKeys = $response->json('created_keys');
        expect(count($createdKeys))->toBe(3);

        // Verify encryption keys were created
        foreach ($createdKeys as $keyData) {
            $this->assertDatabaseHas('chat_encryption_keys', [
                'device_id' => $keyData['device_id'],
                'conversation_id' => $this->conversation->id,
                'is_active' => true,
                'key_version' => 1,
            ]);
        }
    });

    it('can rotate conversation keys for multiple devices', function () {
        // Setup initial keys
        $deviceKeys = [
            ['device_id' => $this->trustedDevice->id],
            ['device_id' => $this->untrustedDevice->id],
        ];

        $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption-multidevice", [
                'device_keys' => $deviceKeys,
                'initiating_device_id' => $this->trustedDevice->id,
            ]);

        // Trust the second device
        $this->untrustedDevice->markAsTrusted();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/conversations/{$this->conversation->id}/rotate-key-multidevice", [
                'initiating_device_id' => $this->trustedDevice->id,
                'reason' => 'Security rotation test',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Multi-device conversation key rotation completed',
                'key_version' => 2,
            ]);

        $rotatedDevices = $response->json('rotated_devices');
        expect(count($rotatedDevices))->toBeGreaterThan(0);

        // Verify old keys are deactivated
        $oldKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('key_version', 1)
            ->get();

        foreach ($oldKeys as $key) {
            expect($key->is_active)->toBeFalse();
        }
    });

    it('can get device-specific conversation key', function () {
        // Create encryption key for device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user->id,
            $this->trustedDevice->id,
            $symmetricKey,
            $this->trustedDevice->public_key,
            $this->trustedDevice->device_fingerprint,
            1
        );

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/conversations/{$this->conversation->id}/device-key", [
                'device_id' => $this->trustedDevice->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'key_id',
                'encrypted_key',
                'public_key',
                'key_version',
                'device_fingerprint',
            ])
            ->assertJson([
                'key_version' => 1,
                'device_fingerprint' => $this->trustedDevice->device_fingerprint,
            ]);
    });

    it('can accept key share via API', function () {
        // Create key share
        $keyShare = DeviceKeyShare::create([
            'from_device_id' => $this->trustedDevice->id,
            'to_device_id' => $this->untrustedDevice->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'encrypted_symmetric_key' => 'encrypted_key_data',
            'from_device_public_key' => $this->trustedDevice->public_key,
            'to_device_public_key' => $this->untrustedDevice->public_key,
            'key_version' => 1,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/key-shares/{$keyShare->id}/accept", [
                'device_id' => $this->untrustedDevice->id,
                'decrypted_symmetric_key' => 'decrypted_symmetric_key_data',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Key share accepted successfully',
                'conversation_id' => $this->conversation->id,
                'key_version' => 1,
            ]);

        // Verify key share is marked as accepted
        $keyShare->refresh();
        expect($keyShare->is_accepted)->toBeTrue();

        // Verify encryption key was created
        $this->assertDatabaseHas('chat_encryption_keys', [
            'device_id' => $this->untrustedDevice->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true,
        ]);
    });

    it('can revoke device access', function () {
        // Create encryption key for device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user->id,
            $this->trustedDevice->id,
            $symmetricKey,
            $this->trustedDevice->public_key
        );

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/chat/devices/{$this->trustedDevice->id}/revoke-access", [
                'reason' => 'Security test',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Device access revoked successfully',
                'device_id' => $this->trustedDevice->id,
            ]);

        // Verify encryption keys are deactivated
        $keys = EncryptionKey::where('device_id', $this->trustedDevice->id)->get();
        foreach ($keys as $key) {
            expect($key->is_active)->toBeFalse();
        }
    });
});

describe('Multi-Device Health Check API', function () {
    it('can get multi-device health status', function () {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/chat/encryption/multidevice-health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'key_generation',
                    'symmetric_encryption',
                    'key_integrity',
                ],
                'multi_device' => [
                    'total_devices',
                    'trusted_devices',
                    'untrusted_devices',
                ],
            ]);

        $health = $response->json();
        expect($health['multi_device']['total_devices'])->toBe(2);
        expect($health['multi_device']['trusted_devices'])->toBe(1);
        expect($health['multi_device']['untrusted_devices'])->toBe(1);
    });

    it('can get device-specific health status', function () {
        // Create encryption key for device
        $symmetricKey = $this->encryptionService->generateSymmetricKey();

        EncryptionKey::createForDevice(
            $this->conversation->id,
            $this->user->id,
            $this->trustedDevice->id,
            $symmetricKey,
            $this->trustedDevice->public_key
        );

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/chat/encryption/multidevice-health?device_id={$this->trustedDevice->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'multi_device',
                'device_status' => [
                    'device_id',
                    'device_name',
                    'is_trusted',
                    'active_conversation_keys',
                    'pending_key_shares',
                ],
            ]);

        $health = $response->json();
        expect($health['device_status']['device_id'])->toBe($this->trustedDevice->id);
        expect($health['device_status']['active_conversation_keys'])->toBe(1);
    });
});

describe('API Validation and Error Handling', function () {
    it('validates device registration input', function () {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/chat/devices', [
                'device_name' => '', // Required
                'device_type' => 'invalid_type', // Must be in allowed values
                'public_key' => '', // Required
                'device_fingerprint' => '', // Required
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'device_name',
                'device_type',
                'public_key',
                'device_fingerprint',
            ]);
    });

    it('validates conversation encryption setup input', function () {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/conversations/{$this->conversation->id}/setup-encryption-multidevice", [
                'device_keys' => [], // Required and must not be empty
                // initiating_device_id missing
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'device_keys',
                'initiating_device_id',
            ]);
    });

    it('validates key share acceptance input', function () {
        $keyShare = DeviceKeyShare::factory()->create([
            'to_device_id' => $this->untrustedDevice->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/key-shares/{$keyShare->id}/accept", [
                'device_id' => $this->untrustedDevice->id, // Valid device ID to pass auth check
                // decrypted_symmetric_key missing
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'decrypted_symmetric_key',
            ]);
    });

    it('handles unauthorized access to other user key shares', function () {
        $otherUserDevice = UserDevice::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $keyShare = DeviceKeyShare::factory()->create([
            'to_device_id' => $otherUserDevice->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/chat/key-shares/{$keyShare->id}/accept", [
                'device_id' => $otherUserDevice->id,
                'decrypted_symmetric_key' => 'test_key',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Key share does not belong to current user',
            ]);
    });
});
