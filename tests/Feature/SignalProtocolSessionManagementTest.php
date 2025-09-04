<?php

use App\Models\Chat\Conversation;
use App\Models\Chat\SignalSession;
use App\Models\Chat\SignalMessage;
use App\Models\Chat\SignalIdentityKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    $this->charlie = User::factory()->create(['name' => 'Charlie']);
    
    $this->conversation = Conversation::factory()->create([
        'created_by' => $this->alice->id,
        'name' => 'Test Signal Session',
        'type' => 'direct',
    ]);
    
    $this->groupConversation = Conversation::factory()->create([
        'created_by' => $this->alice->id,
        'name' => 'Group Signal Session',
        'type' => 'group',
    ]);
});

describe('Signal Protocol Session Management', function () {

    test('can establish quantum-resistant signal session', function () {
        // Create identity keys for both users
        $aliceIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->alice->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        $bobIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->bob->id,
            'is_quantum_capable' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Establish session
        $session = SignalSession::create([
            'session_id' => 'session_alice_bob_' . time(),
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'local_registration_id' => 12345,
            'remote_registration_id' => 54321,
            'remote_identity_key' => base64_encode('bob_identity_key'),
            'session_state_encrypted' => base64_encode('encrypted_session_state'),
            'is_active' => true,
            'verification_status' => 'unverified',
            'protocol_version' => '3.0',
            'quantum_keys_encrypted' => base64_encode('quantum_keys_data'),
            'remote_quantum_key' => base64_encode('bob_quantum_identity'),
            'quantum_algorithm' => 'ML-KEM-768',
            'is_quantum_resistant' => true,
            'quantum_version' => 3,
            'established_at' => now(),
        ]);

        expect($session)->toBeInstanceOf(SignalSession::class);
        expect($session->isQuantumResistant())->toBe(true);
        expect($session->getQuantumAlgorithm())->toBe('ML-KEM-768');
        expect($session->is_active)->toBe(true);
        expect($session->verification_status)->toBe('unverified');
    });

    test('can retrieve session information via API', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'message_count_sent' => 25,
            'message_count_received' => 30,
            'last_key_rotation' => now()->subDays(7),
            'key_rotation_count' => 3,
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
                ]
            ]
        ]);

        $sessionData = $response->json('data.session');
        expect($sessionData['isQuantumResistant'])->toBe(true);
        expect($sessionData['quantumAlgorithm'])->toBe('ML-KEM-768');
        expect($sessionData['messagesSent'])->toBe(25);
        expect($sessionData['messagesReceived'])->toBe(30);
        expect($sessionData['keyRotations'])->toBe(3);
    });

    test('can manage multiple concurrent sessions', function () {
        // Alice has sessions with both Bob and Charlie
        $sessionWithBob = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'quantum_algorithm' => 'ML-KEM-768',
            'is_active' => true,
        ]);

        $conversationWithCharlie = Conversation::factory()->create([
            'created_by' => $this->alice->id,
            'type' => 'direct',
        ]);

        $sessionWithCharlie = SignalSession::factory()->create([
            'conversation_id' => $conversationWithCharlie->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->charlie->id,
            'quantum_algorithm' => 'ML-KEM-1024',
            'is_active' => true,
        ]);

        // Test retrieving all active sessions for Alice
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $stats = $response->json('data.sessionStats');

        expect($stats['activeSessions'])->toBeGreaterThanOrEqual(2);
        expect($stats['quantumSessions'])->toBeGreaterThanOrEqual(2);

        // Verify different algorithms are tracked
        $algorithmStats = $response->json('data.protocolStats.quantumAlgorithmsUsed');
        expect($algorithmStats)->toContain('ML-KEM-768');
        expect($algorithmStats)->toContain('ML-KEM-1024');
    });

    test('can perform session key rotation', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'last_key_rotation' => now()->subDays(30), // Old rotation
            'key_rotation_count' => 1,
        ]);

        // Perform key rotation
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/sessions/rotate-keys', [
                'session_id' => $session->session_id,
                'rotation_reason' => 'scheduled',
                'new_quantum_keys' => [
                    'quantum_root_key' => base64_encode(random_bytes(32)),
                    'quantum_chain_keys' => [
                        'sending' => base64_encode(random_bytes(32)),
                        'receiving' => base64_encode(random_bytes(32)),
                    ],
                    'algorithm' => 'ML-KEM-768',
                    'version' => 3,
                ],
            ]);

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
            ]
        ]);

        // Verify session was updated
        $rotatedSession = $session->fresh();
        expect($rotatedSession->key_rotation_count)->toBe(2);
        expect($rotatedSession->last_key_rotation->isAfter($session->last_key_rotation))->toBe(true);
    });

    test('can verify user identity in session', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'verification_status' => 'unverified',
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Create identity keys for verification
        $aliceIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->alice->id,
            'key_fingerprint' => 'alice_fingerprint_123',
        ]);

        $bobIdentity = SignalIdentityKey::factory()->create([
            'user_id' => $this->bob->id,
            'key_fingerprint' => 'bob_fingerprint_456',
        ]);

        // Perform identity verification
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/identity/verify', [
                'verifier_user_id' => $this->alice->id,
                'target_user_id' => $this->bob->id,
                'verification_method' => 'fingerprint_comparison',
                'verified_fingerprint' => $bobIdentity->key_fingerprint,
                'verification_successful' => true,
                'session_id' => $session->session_id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'verification_id',
                'verification_status',
                'verified_at',
                'verification_method',
                'session_updated',
            ]
        ]);

        // Session should be marked as verified
        $verifiedSession = $session->fresh();
        expect($verifiedSession->verification_status)->toBe('verified');
    });

    test('handles session expiration and cleanup', function () {
        // Create old inactive session
        $oldSession = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_active' => false,
            'last_activity_at' => now()->subDays(90),
            'created_at' => now()->subDays(100),
        ]);

        // Create recent active session
        $activeSession = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->charlie->id,
            'is_active' => true,
            'last_activity_at' => now()->subMinutes(5),
        ]);

        // Check session statistics
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $stats = $response->json('data.sessionStats');

        expect($stats['activeSessions'])->toBeGreaterThanOrEqual(1);
        expect($stats['totalSessions'])->toBeGreaterThanOrEqual(2);
        expect($stats['inactiveSessions'])->toBeGreaterThanOrEqual(1);
        expect($stats['expiredSessions'])->toBeGreaterThanOrEqual(1);
    });

    test('supports group session management', function () {
        // Create group session with multiple participants
        $groupSession = SignalSession::factory()->create([
            'conversation_id' => $this->groupConversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'session_type' => 'group',
        ]);

        // Add another participant session
        $groupSession2 = SignalSession::factory()->create([
            'conversation_id' => $this->groupConversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->charlie->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'session_type' => 'group',
        ]);

        // Test group session statistics
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $stats = $response->json('data.sessionStats');

        expect($stats['groupSessions'])->toBeGreaterThanOrEqual(2);
        expect($stats['directSessions'])->toBeGreaterThanOrEqual(0);
    });

    test('tracks session security metrics', function () {
        // Create sessions with different security levels
        SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'verification_status' => 'verified',
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'security_score' => 95,
        ]);

        SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->charlie->id,
            'verification_status' => 'unverified',
            'is_quantum_resistant' => false,
            'quantum_algorithm' => null,
            'security_score' => 65,
        ]);

        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['securityStats']['averageSecurityScore'])->toBeGreaterThan(70);
        expect($data['securityStats']['verifiedSessions'])->toBeGreaterThanOrEqual(1);
        expect($data['securityStats']['quantumResistantSessions'])->toBeGreaterThanOrEqual(1);
        expect($data['securityStats']['highSecuritySessions'])->toBeGreaterThanOrEqual(1);
    });

    test('handles session recovery after compromise', function () {
        // Create compromised session
        $compromisedSession = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'verification_status' => 'compromised',
            'is_active' => false,
            'compromise_detected_at' => now()->subHours(2),
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Test session info for compromised session
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/sessions/info?' . http_build_query([
                'conversation_id' => $this->conversation->id,
                'remote_user_id' => $this->bob->id,
            ]));

        $response->assertStatus(200);
        $sessionData = $response->json('data.session');

        expect($sessionData['verificationStatus'])->toBe('compromised');
        expect($sessionData['isActive'])->toBe(false);
        expect($sessionData['requiresRenewal'])->toBe(true);
        expect($sessionData['compromiseDetectedAt'])->not->toBeNull();
    });

    test('supports session migration between algorithms', function () {
        // Start with classical session
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => false,
            'quantum_algorithm' => null,
            'protocol_version' => '2.0',
        ]);

        // Simulate algorithm upgrade
        $session->update([
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'protocol_version' => '3.0',
            'quantum_version' => 3,
            'algorithm_upgraded_at' => now(),
            'previous_algorithm' => 'Curve25519',
        ]);

        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/sessions/info?' . http_build_query([
                'conversation_id' => $this->conversation->id,
                'remote_user_id' => $this->bob->id,
            ]));

        $response->assertStatus(200);
        $sessionData = $response->json('data.session');

        expect($sessionData['isQuantumResistant'])->toBe(true);
        expect($sessionData['quantumAlgorithm'])->toBe('ML-KEM-768');
        expect($sessionData['algorithmUpgraded'])->toBe(true);
        expect($sessionData['previousAlgorithm'])->toBe('Curve25519');
    });

    test('enforces session limits and quotas', function () {
        // Create maximum allowed sessions
        $maxSessions = 50;
        
        for ($i = 0; $i < $maxSessions; $i++) {
            $remoteUser = User::factory()->create();
            $conversation = Conversation::factory()->create([
                'created_by' => $this->alice->id,
                'type' => 'direct',
            ]);

            SignalSession::factory()->create([
                'conversation_id' => $conversation->id,
                'local_user_id' => $this->alice->id,
                'remote_user_id' => $remoteUser->id,
                'is_active' => true,
            ]);
        }

        // Check statistics reflect the sessions
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $stats = $response->json('data.sessionStats');

        expect($stats['activeSessions'])->toBe($maxSessions);
        expect($stats['sessionQuotaUsed'])->toBeGreaterThan(80); // Assuming quota is around 60
        expect($stats['nearSessionLimit'])->toBe(true);
    });

    test('provides comprehensive session health metrics', function () {
        // Create sessions with various health states
        SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_active' => true,
            'verification_status' => 'verified',
            'is_quantum_resistant' => true,
            'last_activity_at' => now()->subMinutes(5),
            'message_count_sent' => 100,
            'message_count_received' => 95,
            'error_count' => 0,
        ]);

        SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->charlie->id,
            'is_active' => true,
            'verification_status' => 'unverified',
            'is_quantum_resistant' => false,
            'last_activity_at' => now()->subHours(6),
            'message_count_sent' => 10,
            'message_count_received' => 8,
            'error_count' => 3,
        ]);

        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['healthStats']['overallHealth'])->toBeIn(['healthy', 'warning', 'critical']);
        expect($data['healthStats']['healthySessions'])->toBeGreaterThanOrEqual(1);
        expect($data['healthStats']['sessionsWithErrors'])->toBeGreaterThanOrEqual(1);
        expect($data['healthStats']['averageMessageSuccess'])->toBeGreaterThan(0.8);
        expect($data['healthStats']['recommendedActions'])->toBeArray();
    });

});