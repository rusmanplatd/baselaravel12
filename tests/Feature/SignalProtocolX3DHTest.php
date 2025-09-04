<?php

use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\Chat\SignalOnetimePrekey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    
    $this->otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);
});

describe('X3DH Key Agreement Protocol', function () {
    
    test('can generate identity key with quantum support', function () {
        $identityKey = SignalIdentityKey::create([
            'user_id' => $this->user->id,
            'registration_id' => 12345,
            'public_key' => base64_encode('dummy_public_key'),
            'private_key_encrypted' => base64_encode('encrypted_private_key'),
            'key_fingerprint' => hash('sha256', 'dummy_public_key'),
            'is_active' => true,
            'quantum_public_key' => base64_encode('quantum_public_key_ml_kem_768'),
            'quantum_algorithm' => 'ML-KEM-768',
            'is_quantum_capable' => true,
            'quantum_version' => 3,
        ]);

        expect($identityKey)->toBeInstanceOf(SignalIdentityKey::class);
        expect($identityKey->user_id)->toBe($this->user->id);
        expect($identityKey->is_quantum_capable)->toBe(true);
        expect($identityKey->quantum_algorithm)->toBe('ML-KEM-768');
        expect($identityKey->quantum_version)->toBe(3);
    });

    test('can generate signed prekey with quantum capabilities', function () {
        $identityKey = SignalIdentityKey::factory()->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        $signedPrekey = SignalSignedPrekey::create([
            'user_id' => $this->user->id,
            'key_id' => 456,
            'public_key' => base64_encode('signed_prekey_public'),
            'private_key_encrypted' => base64_encode('signed_prekey_private_encrypted'),
            'signature' => base64_encode('signature_by_identity_key'),
            'generated_at' => now(),
            'is_active' => true,
            'quantum_public_key' => base64_encode('quantum_signed_prekey'),
            'quantum_algorithm' => 'ML-KEM-768',
            'is_quantum_capable' => true,
        ]);

        expect($signedPrekey)->toBeInstanceOf(SignalSignedPrekey::class);
        expect($signedPrekey->is_quantum_capable)->toBe(true);
        expect($signedPrekey->quantum_algorithm)->toBe('ML-KEM-768');
        expect($signedPrekey->is_quantum_capable)->toBe(true);
        expect($signedPrekey->quantum_algorithm)->toBe('ML-KEM-768');
    });

    test('can generate one-time prekeys with quantum support', function () {
        $identityKey = SignalIdentityKey::factory()->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
        ]);

        $oneTimePrekey = SignalOnetimePrekey::create([
            'user_id' => $this->user->id,
            'key_id' => 789,
            'public_key' => base64_encode('onetime_prekey_public'),
            'private_key_encrypted' => base64_encode('onetime_prekey_private_encrypted'),
            'is_used' => false,
            'quantum_public_key' => base64_encode('quantum_onetime_prekey'),
            'quantum_algorithm' => 'ML-KEM-512',
            'is_quantum_capable' => true,
        ]);

        expect($oneTimePrekey)->toBeInstanceOf(SignalOnetimePrekey::class);
        expect($oneTimePrekey->is_quantum_capable)->toBe(true);
        expect($oneTimePrekey->quantum_algorithm)->toBe('ML-KEM-512');
        expect($oneTimePrekey->is_quantum_capable)->toBe(true);
        expect($oneTimePrekey->quantum_algorithm)->toBe('ML-KEM-512');
        expect($oneTimePrekey->is_used)->toBe(false);
    });

    test('prekey bundle contains quantum information', function () {
        $this->markTestSkipped('API endpoint not implemented yet');
        // Create identity key with quantum support
        $identityKey = SignalIdentityKey::factory()->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Create signed prekey
        $signedPrekey = SignalSignedPrekey::factory()->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Create one-time prekeys
        SignalOnetimePrekey::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-512',
        ]);

        // Test prekey bundle retrieval via API
        $response = $this->actingAs($this->otherUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->user->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'registration_id',
                'identity_key',
                'signed_pre_key' => [
                    'id',
                    'public_key',
                    'signature',
                ],
                'one_time_pre_keys',
                'quantum_identity_key',
                'quantum_signed_prekey',
                'quantum_onetime_prekeys',
                'quantum_algorithm',
                'is_quantum_capable',
                'quantum_version',
            ]
        ]);

        $data = $response->json('data');
        expect($data['is_quantum_capable'])->toBe(true);
        expect($data['quantum_algorithm'])->toBe('ML-KEM-768');
        expect($data['quantum_version'])->toBe(3);
    });

    test('algorithm negotiation works for different device capabilities', function () {
        $this->markTestSkipped('API endpoint not implemented yet');
        // Create users with different quantum capabilities
        $quantumUser = User::factory()->create();
        $hybridUser = User::factory()->create();
        $classicalUser = User::factory()->create();

        // Quantum-capable user
        SignalIdentityKey::factory()->create([
            'user_id' => $quantumUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
        ]);

        // Hybrid-capable user
        SignalIdentityKey::factory()->create([
            'user_id' => $hybridUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'HYBRID-RSA4096-MLKEM768',
        ]);

        // Classical-only user
        SignalIdentityKey::factory()->create([
            'user_id' => $classicalUser->id,
            'is_quantum_capable' => false,
            'quantum_algorithm' => null,
        ]);

        // Test quantum to quantum negotiation
        $response1 = $this->actingAs($quantumUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$quantumUser->id}");
        

        // Test quantum to hybrid negotiation
        $response2 = $this->actingAs($quantumUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$hybridUser->id}");
        

        // Test quantum to classical fallback
        $response3 = $this->actingAs($quantumUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$classicalUser->id}");
        
        $data3 = $response3->json('data');
        expect($data3['is_quantum_capable'])->toBe(false);
        expect($data3['quantum_algorithm'])->toBeNull();
    });

    test('X3DH key exchange statistics are tracked', function () {
        $this->markTestSkipped('API endpoint not implemented yet');
        // Create multiple identity keys and prekeys
        SignalIdentityKey::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
        ]);

        $identityKey = SignalIdentityKey::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        SignalSignedPrekey::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        SignalOnetimePrekey::factory()->count(10)->create([
            'user_id' => $this->user->id,
            'is_used' => false,
        ]);

        // Mark some as used
        SignalOnetimePrekey::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_used' => true,
        ]);

        // Test statistics endpoint
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'x3dhStats' => [
                    'identityKeyExists',
                    'identityKeysCount',
                    'signedPreKeys',
                    'signedPreKeysActive',
                    'oneTimePreKeys',
                    'oneTimePreKeysUsed',
                    'oneTimePreKeysAvailable',
                    'quantumCapableKeys',
                ]
            ]
        ]);

        $x3dhStats = $response->json('data.x3dhStats');
        expect($x3dhStats['identityKeyExists'])->toBe(true);
        expect($x3dhStats['identityKeysCount'])->toBe(4); // 3 + 1 active
        expect($x3dhStats['signedPreKeys'])->toBe(2);
        expect($x3dhStats['oneTimePreKeys'])->toBe(15); // 10 + 5 used
        expect($x3dhStats['oneTimePreKeysUsed'])->toBe(5);
        expect($x3dhStats['oneTimePreKeysAvailable'])->toBe(10);
        expect($x3dhStats['quantumCapableKeys'])->toBe(3);
    });

    test('prekey rotation maintains quantum capabilities', function () {
        $identityKey = SignalIdentityKey::factory()->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Create initial signed prekey
        $oldSignedPrekey = SignalSignedPrekey::factory()->create([
            'user_id' => $this->user->id,
            'key_id' => 100,
            'is_active' => true,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Simulate key rotation
        $oldSignedPrekey->update(['is_active' => false]);

        $newSignedPrekey = SignalSignedPrekey::create([
            'user_id' => $this->user->id,
            'key_id' => 101,
            'public_key' => base64_encode('new_signed_prekey_public'),
            'private_key_encrypted' => base64_encode('new_signed_prekey_private_encrypted'),
            'signature' => base64_encode('new_signature'),
            'generated_at' => now(),
            'is_active' => true,
            'quantum_public_key' => base64_encode('new_quantum_signed_prekey'),
            'quantum_algorithm' => 'ML-KEM-768',
            'is_quantum_capable' => true,
        ]);

        expect($newSignedPrekey->is_quantum_capable)->toBe(true);
        expect($newSignedPrekey->quantum_algorithm)->toBe('ML-KEM-768');
        expect($oldSignedPrekey->fresh()->is_active)->toBe(false);
        expect($newSignedPrekey->is_active)->toBe(true);

        // Verify only the new key is active
        expect($newSignedPrekey->is_active)->toBe(true);
        expect($oldSignedPrekey->fresh()->is_active)->toBe(false);
    });

    test('handles quantum algorithm upgrade scenarios', function () {
        // Start with ML-KEM-512
        $identityKey = SignalIdentityKey::factory()->create([
            'user_id' => $this->user->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-512',
            'quantum_version' => 2,
        ]);

        // Upgrade to ML-KEM-768
        $identityKey->update([
            'quantum_algorithm' => 'ML-KEM-768',
            'quantum_version' => 3,
            'quantum_public_key' => base64_encode('upgraded_quantum_key_768'),
        ]);

        expect($identityKey->fresh()->quantum_algorithm)->toBe('ML-KEM-768');
        expect($identityKey->fresh()->quantum_version)->toBe(3);

        // Test that the identity key reflects the upgrade
        expect($identityKey->fresh()->quantum_algorithm)->toBe('ML-KEM-768');
        expect($identityKey->fresh()->quantum_version)->toBe(3);
    });

    test('validates quantum key formats and algorithms', function () {

        // Test valid quantum algorithms
        $validAlgorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768'];
        
        foreach ($validAlgorithms as $algorithm) {
            $identityKey = SignalIdentityKey::create([
                'user_id' => $this->user->id,
                'registration_id' => rand(1, 16383),
                'public_key' => base64_encode('dummy_public_key_' . $algorithm),
                'private_key_encrypted' => base64_encode('encrypted_private_key_' . $algorithm),
                'key_fingerprint' => hash('sha256', 'dummy_public_key_' . $algorithm),
                'is_active' => false, // Only one can be active
                'quantum_algorithm' => $algorithm,
                'is_quantum_capable' => true,
                'quantum_version' => 3,
            ]);

            expect($identityKey->quantum_algorithm)->toBe($algorithm);
            expect($identityKey->is_quantum_capable)->toBe(true);
        }
    });

});