<?php

use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\Chat\SignalOnetimePrekey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create users representing different device types and capabilities
    $this->modernUser = User::factory()->create(['name' => 'Modern User']);
    $this->hybridUser = User::factory()->create(['name' => 'Hybrid User']);
    $this->legacyUser = User::factory()->create(['name' => 'Legacy User']);
    $this->upgradeUser = User::factory()->create(['name' => 'Upgrading User']);
});

describe('Quantum Algorithm Negotiation', function () {

    test('negotiates ML-KEM-768 between modern quantum devices', function () {
        // Modern Device A: Supports latest quantum algorithms
        $modernIdentityA = SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'device_capabilities' => json_encode([
                'ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512',
                'HYBRID-RSA4096-MLKEM768', 'Curve25519', 'P-256'
            ]),
            'quantum_version' => 3,
        ]);

        // Modern Device B: Also quantum-capable but with slightly different preferences
        $modernIdentityB = SignalIdentityKey::factory()->create([
            'user_id' => $this->hybridUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'device_capabilities' => json_encode([
                'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 
                'Curve25519', 'RSA-4096-OAEP'
            ]),
            'quantum_version' => 3,
        ]);

        // Test negotiation via API
        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->hybridUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should negotiate ML-KEM-768 (highest common algorithm)
        expect($data['quantum_algorithm'])->toBe('ML-KEM-768');
        expect($data['is_quantum_capable'])->toBe(true);
        expect($data['device_capabilities'])->toContain('ML-KEM-768');
        expect($data['negotiated_algorithm'])->toBe('ML-KEM-768');
        expect($data['negotiation_type'])->toBe('quantum');
    });

    test('falls back to hybrid mode for mixed capability devices', function () {
        // Quantum device
        SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'device_capabilities' => json_encode([
                'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 'Curve25519'
            ]),
        ]);

        // Hybrid-capable device (supports some quantum but not full)
        SignalIdentityKey::factory()->create([
            'user_id' => $this->hybridUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'device_capabilities' => json_encode([
                'HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP', 'Curve25519'
            ]),
        ]);

        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->hybridUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should negotiate hybrid algorithm
        expect($data['quantum_algorithm'])->toBe('HYBRID-RSA4096-MLKEM768');
        expect($data['negotiated_algorithm'])->toBe('HYBRID-RSA4096-MLKEM768');
        expect($data['negotiation_type'])->toBe('hybrid');
        expect($data['fallback_reason'])->toBe('device_compatibility');
    });

    test('gracefully degrades to classical algorithms for legacy devices', function () {
        // Modern quantum device
        SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'device_capabilities' => json_encode([
                'ML-KEM-1024', 'ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 
                'Curve25519', 'RSA-4096-OAEP'
            ]),
        ]);

        // Legacy device (no quantum support)
        SignalIdentityKey::factory()->create([
            'user_id' => $this->legacyUser->id,
            'is_quantum_capable' => false,
            'quantum_algorithm' => null,
            'device_capabilities' => json_encode([
                'Curve25519', 'P-256', 'RSA-4096-OAEP', 'RSA-2048-OAEP'
            ]),
        ]);

        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->legacyUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should fall back to classical algorithm
        expect($data['is_quantum_capable'])->toBe(false);
        expect($data['quantum_algorithm'])->toBeNull();
        expect($data['negotiated_algorithm'])->toBe('Curve25519'); // Best classical option
        expect($data['negotiation_type'])->toBe('classical');
        expect($data['fallback_reason'])->toBe('legacy_device');
    });

    test('handles algorithm priority ordering correctly', function () {
        // Test different algorithm priority scenarios
        $testCases = [
            [
                'deviceA' => ['ML-KEM-1024', 'ML-KEM-768', 'Curve25519'],
                'deviceB' => ['ML-KEM-768', 'ML-KEM-512', 'RSA-4096-OAEP'],
                'expected' => 'ML-KEM-768',
                'type' => 'quantum'
            ],
            [
                'deviceA' => ['HYBRID-RSA4096-MLKEM768', 'RSA-4096-OAEP', 'Curve25519'],
                'deviceB' => ['ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 'P-256'],
                'expected' => 'HYBRID-RSA4096-MLKEM768',
                'type' => 'hybrid'
            ],
            [
                'deviceA' => ['Curve25519', 'P-256', 'RSA-4096-OAEP'],
                'deviceB' => ['RSA-4096-OAEP', 'RSA-2048-OAEP'],
                'expected' => 'RSA-4096-OAEP',
                'type' => 'classical'
            ],
        ];

        foreach ($testCases as $index => $testCase) {
            // Create temporary users for this test case
            $userA = User::factory()->create(['name' => "Test User A-{$index}"]);
            $userB = User::factory()->create(['name' => "Test User B-{$index}"]);

            // Create identity keys with specific capabilities
            SignalIdentityKey::factory()->create([
                'user_id' => $userA->id,
                'device_capabilities' => json_encode($testCase['deviceA']),
                'is_quantum_capable' => $testCase['type'] !== 'classical',
                'quantum_algorithm' => $testCase['type'] === 'quantum' ? $testCase['expected'] : 
                                     ($testCase['type'] === 'hybrid' ? $testCase['expected'] : null),
            ]);

            SignalIdentityKey::factory()->create([
                'user_id' => $userB->id,
                'device_capabilities' => json_encode($testCase['deviceB']),
                'is_quantum_capable' => $testCase['type'] !== 'classical',
                'quantum_algorithm' => $testCase['type'] === 'quantum' ? $testCase['expected'] : 
                                     ($testCase['type'] === 'hybrid' ? $testCase['expected'] : null),
            ]);

            // Test negotiation
            $response = $this->actingAs($userA, 'api')
                ->getJson("/api/v1/chat/signal/prekey-bundle/{$userB->id}");

            $response->assertStatus(200);
            $data = $response->json('data');

            expect($data['negotiated_algorithm'])
                ->toBe($testCase['expected'])
                ->and($data['negotiation_type'])
                ->toBe($testCase['type']);
        }
    });

    test('handles quantum algorithm version compatibility', function () {
        // Device with newer quantum version
        SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'quantum_version' => 4, // Future version
            'device_capabilities' => json_encode(['ML-KEM-768', 'ML-KEM-512']),
        ]);

        // Device with current quantum version
        SignalIdentityKey::factory()->create([
            'user_id' => $this->hybridUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'quantum_version' => 3, // Current version
            'device_capabilities' => json_encode(['ML-KEM-768', 'ML-KEM-512']),
        ]);

        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->hybridUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should use compatible version
        expect($data['quantum_version'])->toBe(3); // Should downgrade to compatible version
        expect($data['negotiated_algorithm'])->toBe('ML-KEM-768');
        expect($data['version_compatibility'])->toBe('downgraded');
    });

    test('supports algorithm upgrade negotiations', function () {
        // Start with lower capability device
        $identity = SignalIdentityKey::factory()->create([
            'user_id' => $this->upgradeUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-512',
            'quantum_version' => 2,
            'device_capabilities' => json_encode(['ML-KEM-512', 'Curve25519']),
        ]);

        // Simulate device upgrade
        $identity->update([
            'quantum_algorithm' => 'ML-KEM-768',
            'quantum_version' => 3,
            'device_capabilities' => json_encode([
                'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 'Curve25519'
            ]),
        ]);

        // Create a modern device to negotiate with
        SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'device_capabilities' => json_encode(['ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512']),
        ]);

        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->upgradeUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should negotiate higher algorithm after upgrade
        expect($data['negotiated_algorithm'])->toBe('ML-KEM-768');
        expect($data['quantum_version'])->toBe(3);
        expect($data['upgrade_detected'])->toBe(true);
    });

    test('handles negotiation failure scenarios gracefully', function () {
        // Device with only unsupported algorithms
        SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'device_capabilities' => json_encode(['ML-KEM-1024', 'ML-KEM-768']),
            'is_quantum_capable' => true,
        ]);

        // Device with completely incompatible algorithms
        SignalIdentityKey::factory()->create([
            'user_id' => $this->legacyUser->id,
            'device_capabilities' => json_encode(['RSA-1024-OAEP']), // Weak, unsupported
            'is_quantum_capable' => false,
        ]);

        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->legacyUser->id}");

        // Should still work but with warnings
        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['negotiation_warning'])->toBe('weak_algorithms_detected');
        expect($data['negotiated_algorithm'])->toBeNull();
        expect($data['fallback_to_insecure'])->toBe(true);
    });

    test('tracks negotiation statistics and metrics', function () {
        // Create multiple negotiation scenarios
        $scenarios = [
            ['quantum', 'ML-KEM-768'],
            ['hybrid', 'HYBRID-RSA4096-MLKEM768'],
            ['classical', 'Curve25519'],
            ['quantum', 'ML-KEM-1024'],
            ['quantum', 'ML-KEM-512'],
        ];

        foreach ($scenarios as $index => [$type, $algorithm]) {
            $userA = User::factory()->create(["name" => "User A-{$index}"]);
            $userB = User::factory()->create(["name" => "User B-{$index}"]);

            SignalIdentityKey::factory()->create([
                'user_id' => $userA->id,
                'is_quantum_capable' => $type !== 'classical',
                'quantum_algorithm' => $type !== 'classical' ? $algorithm : null,
            ]);

            SignalIdentityKey::factory()->create([
                'user_id' => $userB->id,
                'is_quantum_capable' => $type !== 'classical',
                'quantum_algorithm' => $type !== 'classical' ? $algorithm : null,
            ]);

            // Perform negotiation
            $this->actingAs($userA, 'api')
                ->getJson("/api/v1/chat/signal/prekey-bundle/{$userB->id}");
        }

        // Check statistics
        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $stats = $response->json('data');

        expect($stats['negotiationStats']['totalNegotiations'])->toBeGreaterThanOrEqual(5);
        expect($stats['negotiationStats']['quantumNegotiations'])->toBeGreaterThanOrEqual(3);
        expect($stats['negotiationStats']['hybridNegotiations'])->toBeGreaterThanOrEqual(1);
        expect($stats['negotiationStats']['classicalFallbacks'])->toBeGreaterThanOrEqual(1);
        
        // Algorithm usage statistics
        expect($stats['algorithmStats']['mostUsedQuantum'])->toBeIn([
            'ML-KEM-768', 'ML-KEM-1024', 'ML-KEM-512'
        ]);
        expect($stats['algorithmStats']['quantumAdoptionRate'])->toBeGreaterThan(0);
    });

    test('supports real-time algorithm negotiation', function () {
        // Create two users for real-time negotiation test
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        // Alice has latest capabilities
        SignalIdentityKey::factory()->create([
            'user_id' => $alice->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'device_capabilities' => json_encode([
                'ML-KEM-1024', 'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768'
            ]),
            'quantum_version' => 3,
        ]);

        // Bob has medium capabilities
        SignalIdentityKey::factory()->create([
            'user_id' => $bob->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'device_capabilities' => json_encode([
                'ML-KEM-768', 'ML-KEM-512', 'HYBRID-RSA4096-MLKEM768', 'Curve25519'
            ]),
            'quantum_version' => 3,
        ]);

        // Test negotiation from Alice's perspective
        $aliceResponse = $this->actingAs($alice, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$bob->id}");

        // Test negotiation from Bob's perspective
        $bobResponse = $this->actingAs($bob, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$alice->id}");

        // Both should agree on the same algorithm
        expect($aliceResponse->json('data.negotiated_algorithm'))
            ->toBe($bobResponse->json('data.negotiated_algorithm'))
            ->toBe('ML-KEM-768'); // Highest common algorithm

        // Both should recognize the negotiation type
        expect($aliceResponse->json('data.negotiation_type'))->toBe('quantum');
        expect($bobResponse->json('data.negotiation_type'))->toBe('quantum');
    });

    test('validates negotiation security requirements', function () {
        // Test minimum security level enforcement
        SignalIdentityKey::factory()->create([
            'user_id' => $this->modernUser->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'device_capabilities' => json_encode(['ML-KEM-768', 'ML-KEM-512']),
            'security_policy' => json_encode([
                'minimum_algorithm' => 'ML-KEM-512',
                'require_quantum' => false,
                'allow_classical_fallback' => true,
            ]),
        ]);

        // Device that doesn't meet minimum requirements
        SignalIdentityKey::factory()->create([
            'user_id' => $this->legacyUser->id,
            'is_quantum_capable' => false,
            'device_capabilities' => json_encode(['RSA-2048-OAEP']), // Below minimum
        ]);

        $response = $this->actingAs($this->modernUser, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->legacyUser->id}");

        $response->assertStatus(200); // Should still work but with warnings
        $data = $response->json('data');

        expect($data['security_warning'])->toBe('below_minimum_requirements');
        expect($data['policy_violation'])->toBe(true);
        expect($data['recommended_action'])->toBe('upgrade_device_capabilities');
    });

});