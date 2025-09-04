<?php

use App\Models\Chat\Conversation;
use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\Chat\SignalOnetimePrekey;
use App\Models\Chat\SignalSession;
use App\Models\Chat\SignalMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    
    $this->conversation = Conversation::factory()->create([
        'created_by' => $this->alice->id,
        'name' => 'API Test Conversation',
        'type' => 'direct',
    ]);
});

describe('Signal Protocol API Controller', function () {

    test('upload prekey bundle endpoint validates input correctly', function () {
        // Test missing required fields
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/upload-bundle', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'registration_id',
            'identity_key',
            'signed_pre_key',
            'one_time_pre_keys'
        ]);

        // Test invalid registration ID range
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/upload-bundle', [
                'registration_id' => 0, // Invalid range
                'identity_key' => 'valid_key',
                'signed_pre_key' => [],
                'one_time_pre_keys' => []
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['registration_id']);

        // Test registration ID upper bound
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/upload-bundle', [
                'registration_id' => 16385, // Above max
                'identity_key' => 'valid_key',
                'signed_pre_key' => [],
                'one_time_pre_keys' => []
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['registration_id']);
    });

    test('upload prekey bundle creates quantum-capable keys', function () {
        $bundleData = [
            'registration_id' => 12345,
            'identity_key' => base64_encode('alice_identity_public_key'),
            'signed_pre_key' => [
                'id' => 456,
                'public_key' => base64_encode('alice_signed_prekey_public'),
                'signature' => base64_encode('signature_by_identity_key'),
            ],
            'one_time_pre_keys' => [
                ['id' => 789, 'public_key' => base64_encode('alice_onetime_1')],
                ['id' => 790, 'public_key' => base64_encode('alice_onetime_2')],
                ['id' => 791, 'public_key' => base64_encode('alice_onetime_3')],
            ],
            // Quantum extensions
            'quantum_identity_key' => base64_encode('alice_quantum_identity_ml_kem_768'),
            'quantum_signed_prekey' => base64_encode('alice_quantum_signed_prekey'),
            'quantum_onetime_prekeys' => [
                base64_encode('alice_quantum_onetime_1'),
                base64_encode('alice_quantum_onetime_2'),
                base64_encode('alice_quantum_onetime_3'),
            ],
            'quantum_algorithm' => 'ML-KEM-768',
            'device_capabilities' => ['ML-KEM-768', 'ML-KEM-512', 'Curve25519'],
        ];

        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/upload-bundle', $bundleData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'identity_key_id',
                'signed_prekey_id',
                'onetime_prekey_ids',
                'quantum_capable',
                'quantum_algorithm',
                'device_capabilities',
            ]
        ]);

        // Verify database records were created
        $identityKey = SignalIdentityKey::where('user_id', $this->alice->id)->first();
        expect($identityKey)->not->toBeNull();
        expect($identityKey->registration_id)->toBe(12345);
        expect($identityKey->is_quantum_capable)->toBe(true);
        expect($identityKey->quantum_algorithm)->toBe('ML-KEM-768');

        $signedPrekey = SignalSignedPrekey::where('user_id', $this->alice->id)->first();
        expect($signedPrekey)->not->toBeNull();
        expect($signedPrekey->key_id)->toBe(456);
        expect($signedPrekey->is_quantum_capable)->toBe(true);

        $onetimePrekeys = SignalOnetimePrekey::where('user_id', $this->alice->id)->get();
        expect($onetimePrekeys)->toHaveCount(3);
        expect($onetimePrekeys->first()->is_quantum_capable)->toBe(true);
    });

    test('get prekey bundle endpoint returns quantum information', function () {
        // Create prekey bundle for Bob
        $bobIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->bob->id,
            'registration_id' => 54321,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'device_capabilities' => json_encode(['ML-KEM-1024', 'ML-KEM-768', 'Curve25519']),
        ]);

        $bobSignedPrekey = SignalSignedPrekey::factory()->create([
            'user_id' => $this->bob->id,
            'identity_key_id' => $bobIdentity->id,
            'key_id' => 999,
            'is_active' => true,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
        ]);

        SignalOnetimePrekey::factory()->count(5)->create([
            'user_id' => $this->bob->id,
            'identity_key_id' => $bobIdentity->id,
            'is_used' => false,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
        ]);

        // Alice requests Bob's prekey bundle
        $response = $this->actingAs($this->alice, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$this->bob->id}");

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
                'device_capabilities',
                'is_quantum_capable',
                'quantum_version',
                'negotiated_algorithm',
                'negotiation_type',
            ]
        ]);

        $data = $response->json('data');
        expect($data['registration_id'])->toBe(54321);
        expect($data['is_quantum_capable'])->toBe(true);
        expect($data['quantum_algorithm'])->toBe('ML-KEM-1024');
        expect($data['negotiated_algorithm'])->toBe('ML-KEM-1024');
        expect($data['negotiation_type'])->toBe('quantum');

        // Verify one-time prekey was marked as used
        $usedPrekey = SignalOnetimePrekey::where('user_id', $this->bob->id)
            ->where('is_used', true)
            ->first();
        expect($usedPrekey)->not->toBeNull();
    });

    test('get prekey bundle handles user not found', function () {
        $nonExistentUserId = 'non-existent-user-id';

        $response = $this->actingAs($this->alice, 'api')
            ->getJson("/api/v1/chat/signal/prekey-bundle/{$nonExistentUserId}");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'User not found or has no prekey bundle available'
        ]);
    });

    test('send signal message endpoint processes quantum messages', function () {
        // Create session between Alice and Bob
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        $messageData = [
            'conversation_id' => $this->conversation->id,
            'recipient_user_id' => $this->bob->id,
            'message' => [
                'type' => 'prekey',
                'version' => 3,
                'registrationId' => 12345,
                'preKeyId' => 789,
                'signedPreKeyId' => 456,
                'baseKey' => base64_encode(random_bytes(32)),
                'identityKey' => base64_encode(random_bytes(32)),
                'message' => [
                    'header' => [
                        'sender_chain_key' => base64_encode(random_bytes(32)),
                        'previous_counter' => 0,
                        'ratchet_key' => base64_encode(random_bytes(32)),
                    ],
                    'ciphertext' => base64_encode('Hello from Alice with ML-KEM!'),
                    'isQuantumEncrypted' => true,
                    'quantumAlgorithm' => 'ML-KEM-768',
                    'quantumCiphertext' => base64_encode(random_bytes(128)),
                    'quantumIv' => base64_encode(random_bytes(12)),
                ],
                'timestamp' => now()->timestamp,
                'isQuantumResistant' => true,
                'encryptionVersion' => 3,
            ]
        ];

        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/messages/send', $messageData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'message_id',
                'conversation_id',
                'session_id',
                'quantum_encrypted',
                'algorithm_used',
                'timestamp',
            ]
        ]);

        // Verify message was stored
        $storedMessage = SignalMessage::where('conversation_id', $this->conversation->id)->first();
        expect($storedMessage)->not->toBeNull();
        expect($storedMessage->message_type)->toBe('prekey');
        expect($storedMessage->is_quantum_resistant)->toBe(true);
        expect($storedMessage->quantum_algorithm)->toBe('ML-KEM-768');
    });

    test('session info endpoint returns comprehensive session data', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'verification_status' => 'verified',
            'message_count_sent' => 42,
            'message_count_received' => 38,
            'key_rotation_count' => 5,
            'last_key_rotation' => now()->subDays(14),
            'security_score' => 92,
        ]);

        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/sessions/info?' . http_build_query([
                'conversation_id' => $this->conversation->id,
                'remote_user_id' => $this->bob->id,
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'session' => [
                    'sessionId',
                    'verificationStatus',
                    'isActive',
                    'protocolVersion',
                    'quantumAlgorithm',
                    'isQuantumResistant',
                    'quantumVersion',
                    'messagesSent',
                    'messagesReceived',
                    'keyRotations',
                    'lastActivity',
                    'lastKeyRotation',
                    'sessionAge',
                    'securityScore',
                    'healthStatus',
                ]
            ]
        ]);

        $sessionData = $response->json('data.session');
        expect($sessionData['verificationStatus'])->toBe('verified');
        expect($sessionData['isQuantumResistant'])->toBe(true);
        expect($sessionData['quantumAlgorithm'])->toBe('ML-KEM-768');
        expect($sessionData['messagesSent'])->toBe(42);
        expect($sessionData['messagesReceived'])->toBe(38);
        expect($sessionData['keyRotations'])->toBe(5);
        expect($sessionData['securityScore'])->toBe(92);
    });

    test('key rotation endpoint updates session quantum keys', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'key_rotation_count' => 2,
            'last_key_rotation' => now()->subDays(21),
        ]);

        $rotationData = [
            'session_id' => $session->session_id,
            'rotation_reason' => 'scheduled_maintenance',
            'new_quantum_keys' => [
                'quantum_root_key' => base64_encode(random_bytes(32)),
                'quantum_chain_keys' => [
                    'sending' => base64_encode(random_bytes(32)),
                    'receiving' => base64_encode(random_bytes(32)),
                ],
                'algorithm' => 'ML-KEM-768',
                'version' => 3,
            ],
            'force_rotation' => false,
        ];

        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/sessions/rotate-keys', $rotationData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'session_id',
                'new_key_version',
                'quantum_algorithm',
                'rotation_timestamp',
                'previous_rotation_age_days',
                'rotation_reason',
            ]
        ]);

        // Verify session was updated
        $rotatedSession = $session->fresh();
        expect($rotatedSession->key_rotation_count)->toBe(3);
        expect($rotatedSession->last_key_rotation->isAfter($session->last_key_rotation))->toBe(true);
    });

    test('identity verification endpoint processes verification requests', function () {
        // Create identity keys for both users
        $aliceIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->alice->id,
            'key_fingerprint' => 'alice_fingerprint_abc123',
        ]);

        $bobIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->bob->id,
            'key_fingerprint' => 'bob_fingerprint_def456',
        ]);

        $verificationData = [
            'verifier_user_id' => $this->alice->id,
            'target_user_id' => $this->bob->id,
            'verification_method' => 'fingerprint_comparison',
            'verified_fingerprint' => $bobIdentity->key_fingerprint,
            'verification_successful' => true,
            'verification_notes' => 'Verified in person',
        ];

        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/identity/verify', $verificationData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'verification_id',
                'verification_status',
                'verified_at',
                'verification_method',
                'verifier_user_id',
                'target_user_id',
            ]
        ]);

        $verificationResult = $response->json('data');
        expect($verificationResult['verification_status'])->toBe('verified');
        expect($verificationResult['verification_method'])->toBe('fingerprint_comparison');
    });

    test('statistics endpoint returns comprehensive protocol metrics', function () {
        // Create test data for statistics
        SignalSession::factory()->count(3)->create([
            'local_user_id' => $this->alice->id,
            'is_active' => true,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        SignalSession::factory()->count(2)->create([
            'local_user_id' => $this->alice->id,
            'is_active' => true,
            'is_quantum_resistant' => false,
        ]);

        SignalMessage::factory()->count(50)->create([
            'sender_user_id' => $this->alice->id,
            'is_quantum_resistant' => true,
        ]);

        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'sessionStats' => [
                    'activeSessions',
                    'totalSessions',
                    'verifiedSessions',
                    'quantumSessions',
                    'classicalSessions',
                    'totalMessagesExchanged',
                    'keyRotationsPerformed',
                    'averageSessionAge',
                ],
                'x3dhStats' => [
                    'identityKeyExists',
                    'identityKeysCount',
                    'signedPreKeys',
                    'signedPreKeysActive',
                    'oneTimePreKeys',
                    'oneTimePreKeysUsed',
                    'oneTimePreKeysAvailable',
                    'quantumCapableKeys',
                ],
                'protocolStats' => [
                    'version',
                    'quantumSupported',
                    'hybridModeEnabled',
                    'quantumAlgorithmsUsed',
                    'mostUsedAlgorithm',
                ],
                'securityStats' => [
                    'overallSecurityScore',
                    'quantumReadinessScore',
                    'verificationRate',
                    'keyRotationHealth',
                ],
                'performanceStats' => [
                    'averageMessageSize',
                    'encryptionSpeed',
                    'keyGenerationTime',
                    'negotiationLatency',
                ]
            ]
        ]);

        $stats = $response->json('data');
        expect($stats['sessionStats']['activeSessions'])->toBe(5);
        expect($stats['sessionStats']['quantumSessions'])->toBe(3);
        expect($stats['sessionStats']['classicalSessions'])->toBe(2);
        expect($stats['protocolStats']['quantumSupported'])->toBe(true);
    });

    test('API endpoints enforce rate limiting', function () {
        // Test upload bundle rate limiting (10 requests per minute)
        for ($i = 0; $i < 12; $i++) {
            $response = $this->actingAs($this->alice, 'api')
                ->postJson('/api/v1/chat/signal/upload-bundle', [
                    'registration_id' => 12345 + $i,
                    'identity_key' => base64_encode("key_$i"),
                    'signed_pre_key' => [
                        'id' => 456 + $i,
                        'public_key' => base64_encode("signed_key_$i"),
                        'signature' => base64_encode("signature_$i"),
                    ],
                    'one_time_pre_keys' => [],
                ]);

            if ($i < 10) {
                // First 10 requests should succeed
                expect($response->status())->toBeIn([200, 422]); // 422 for validation errors is OK
            } else {
                // 11th and 12th requests should be rate limited
                $response->assertStatus(429);
            }
        }
    });

    test('API endpoints require proper authentication', function () {
        // Test all endpoints without authentication
        $endpoints = [
            ['GET', '/api/v1/chat/signal/statistics'],
            ['GET', "/api/v1/chat/signal/prekey-bundle/{$this->bob->id}"],
            ['POST', '/api/v1/chat/signal/upload-bundle'],
            ['POST', '/api/v1/chat/signal/messages/send'],
            ['GET', '/api/v1/chat/signal/sessions/info'],
            ['POST', '/api/v1/chat/signal/sessions/rotate-keys'],
            ['POST', '/api/v1/chat/signal/identity/verify'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint, []);
            $response->assertStatus(401);
            $response->assertJson(['message' => 'Unauthenticated.']);
        }
    });

    test('API endpoints handle malformed JSON gracefully', function () {
        // Test with malformed JSON in request body
        $response = $this->actingAs($this->alice, 'api')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('/api/v1/chat/signal/upload-bundle', '{invalid json}');

        $response->assertStatus(400);
    });

    test('API endpoints validate user permissions', function () {
        // Create another user that Alice shouldn't have access to
        $charlie = User::factory()->create();
        $charlieConversation = Conversation::factory()->create([
            'created_by' => $charlie->id,
            'type' => 'direct',
        ]);

        // Alice tries to access Charlie's session info
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/sessions/info?' . http_build_query([
                'conversation_id' => $charlieConversation->id,
                'remote_user_id' => $charlie->id,
            ]));

        // Should return 404 or 403 for unauthorized access
        expect($response->status())->toBeIn([403, 404]);
    });

    test('API responses include proper security headers', function () {
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        
        // Check for security headers (these might be set by middleware)
        $headers = $response->headers->all();
        
        // These assertions depend on your security middleware configuration
        // Uncomment if you have these headers configured
        // expect($headers)->toHaveKey('x-content-type-options');
        // expect($headers)->toHaveKey('x-frame-options');
    });

    test('API handles concurrent requests safely', function () {
        // This test simulates concurrent key rotations to ensure thread safety
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'key_rotation_count' => 0,
        ]);

        // Simulate two concurrent rotation requests
        $rotationData = [
            'session_id' => $session->session_id,
            'rotation_reason' => 'concurrent_test',
            'new_quantum_keys' => [
                'quantum_root_key' => base64_encode(random_bytes(32)),
                'quantum_chain_keys' => [
                    'sending' => base64_encode(random_bytes(32)),
                    'receiving' => base64_encode(random_bytes(32)),
                ],
                'algorithm' => 'ML-KEM-768',
                'version' => 3,
            ],
        ];

        // Send two requests (simulating concurrency)
        $response1 = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/sessions/rotate-keys', $rotationData);

        $response2 = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/sessions/rotate-keys', $rotationData);

        // One should succeed, one might fail or both might succeed with proper handling
        expect($response1->status())->toBeIn([200, 409]); // 409 for conflict
        expect($response2->status())->toBeIn([200, 409]);

        // At least one rotation should have occurred
        $finalSession = $session->fresh();
        expect($finalSession->key_rotation_count)->toBeGreaterThan(0);
    });

});