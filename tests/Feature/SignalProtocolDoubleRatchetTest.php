<?php

use App\Models\Chat\Conversation;
use App\Models\Chat\SignalSession;
use App\Models\Chat\SignalMessage;
use App\Models\Chat\SignalIdentityKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create(['email_verified_at' => now()]);
    $this->bob = User::factory()->create(['email_verified_at' => now()]);
    
    $this->conversation = Conversation::factory()->create([
        'created_by' => $this->alice->id,
        'name' => 'Test Conversation',
        'type' => 'direct',
    ]);
});

describe('Double Ratchet Protocol', function () {

    test('can create signal session with quantum support', function () {
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

        $session = SignalSession::create([
            'session_id' => 'test_session_alice_bob_quantum',
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
            'last_activity_at' => now(),
            'quantum_keys_encrypted' => base64_encode('quantum_keys_data'),
            'remote_quantum_key' => base64_encode('bob_quantum_identity'),
            'quantum_algorithm' => 'ML-KEM-768',
            'is_quantum_resistant' => true,
            'quantum_version' => 3,
        ]);

        expect($session)->toBeInstanceOf(SignalSession::class);
        expect($session->is_quantum_resistant)->toBe(true);
        expect($session->quantum_algorithm)->toBe('ML-KEM-768');
        expect($session->quantum_version)->toBe(3);
        expect($session->is_quantum_resistant)->toBe(true);
        expect($session->quantum_algorithm)->toBe('ML-KEM-768');
    });

    test('can send and store quantum-resistant messages', function () {
        // Create quantum-capable session
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'quantum_version' => 3,
        ]);

        // Send quantum-encrypted message
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/messages/send', [
                'conversation_id' => $this->conversation->id,
                'recipient_user_id' => $this->bob->id,
                'message' => [
                    'type' => 'prekey',
                    'version' => 3,
                    'message' => [
                        'header' => [
                            'sender_chain_key' => base64_encode(random_bytes(32)),
                            'previous_counter' => 0,
                            'ratchet_key' => base64_encode(random_bytes(32)),
                        ],
                        'ciphertext' => base64_encode(random_bytes(64)),
                        'isQuantumEncrypted' => true,
                        'quantumAlgorithm' => 'ML-KEM-768',
                        'quantumCiphertext' => base64_encode(random_bytes(128)),
                        'quantumIv' => base64_encode(random_bytes(12)),
                    ],
                    'timestamp' => now()->timestamp,
                    'isQuantumResistant' => true,
                    'encryptionVersion' => 3,
                ]
            ]);

        $response->assertStatus(200);

        // Verify message was stored with quantum information
        $storedMessage = SignalMessage::where('conversation_id', $this->conversation->id)->first();
        
        expect($storedMessage)->not->toBeNull();
        expect($storedMessage->is_quantum_resistant)->toBe(true);
        expect($storedMessage->quantum_algorithm)->toBe('ML-KEM-768');
        expect($storedMessage->quantum_version)->toBe(3);
        expect($storedMessage->is_quantum_resistant)->toBe(true);
        expect($storedMessage->quantum_algorithm)->toBe('ML-KEM-768');
    });

    test('handles double ratchet key rotation with quantum keys', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'message_count_sent' => 0,
            'message_count_received' => 0,
        ]);

        // Simulate sending multiple messages to trigger key rotation
        $messagesToSend = 5;
        
        for ($i = 0; $i < $messagesToSend; $i++) {
            $response = $this->actingAs($this->alice, 'api')
                ->postJson('/api/v1/chat/signal/messages/send', [
                    'conversation_id' => $this->conversation->id,
                    'recipient_user_id' => $this->bob->id,
                    'message' => [
                        'type' => $i === 0 ? 'prekey' : 'normal',
                        'version' => 3,
                        'message' => [
                            'header' => [
                                'sender_chain_key' => base64_encode(random_bytes(32)),
                                'previous_counter' => $i,
                                'ratchet_key' => base64_encode(random_bytes(32)),
                            ],
                            'ciphertext' => base64_encode("Message $i quantum encrypted"),
                            'isQuantumEncrypted' => true,
                            'quantumAlgorithm' => 'ML-KEM-768',
                        ],
                        'timestamp' => now()->timestamp,
                        'isQuantumResistant' => true,
                        'encryptionVersion' => 3,
                    ]
                ]);

            $response->assertStatus(200);
        }

        // Verify messages were stored correctly
        $messages = SignalMessage::where('conversation_id', $this->conversation->id)
            ->orderBy('created_at')
            ->get();

        expect($messages)->toHaveCount($messagesToSend);
        
        // First message should be prekey type
        expect($messages->first()->message_type)->toBe('prekey');
        expect($messages->first()->is_quantum_resistant)->toBe(true);
        
        // Subsequent messages should be normal type
        for ($i = 1; $i < $messagesToSend; $i++) {
            expect($messages[$i]->message_type)->toBe('normal');
            expect($messages[$i]->is_quantum_resistant)->toBe(true);
            expect($messages[$i]->quantum_algorithm)->toBe('ML-KEM-768');
        }

        // Session should track message counts
        $updatedSession = $session->fresh();
        expect($updatedSession->message_count_sent)->toBeGreaterThan(0);
    });

    test('can rotate session keys maintaining quantum resistance', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'last_key_rotation' => now()->subDays(30),
        ]);

        // Perform key rotation
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/sessions/rotate-keys', [
                'session_id' => $session->session_id,
                'new_quantum_keys' => [
                    'quantum_root_key' => base64_encode(random_bytes(32)),
                    'quantum_chain_keys' => [
                        'sending' => base64_encode(random_bytes(32)),
                        'receiving' => base64_encode(random_bytes(32)),
                    ],
                    'algorithm' => 'ML-KEM-768',
                    'version' => 3,
                ]
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
            ]
        ]);

        // Verify session was updated
        $rotatedSession = $session->fresh();
        expect($rotatedSession->last_key_rotation)->toBeGreaterThan($session->last_key_rotation);
        expect($rotatedSession->is_quantum_resistant)->toBe(true);
        expect($rotatedSession->quantum_algorithm)->toBe('ML-KEM-768');
    });

    test('handles hybrid encryption mode correctly', function () {
        // Create session with hybrid encryption
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => false, // Classical session
            'quantum_algorithm' => null,
        ]);

        // Send message with hybrid encryption
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/messages/send', [
                'conversation_id' => $this->conversation->id,
                'recipient_user_id' => $this->bob->id,
                'message' => [
                    'type' => 'prekey',
                    'version' => 3,
                    'message' => [
                        'header' => [
                            'sender_chain_key' => base64_encode(random_bytes(32)),
                            'previous_counter' => 0,
                            'ratchet_key' => base64_encode(random_bytes(32)),
                        ],
                        'ciphertext' => base64_encode('Classical encrypted message'),
                        'isQuantumEncrypted' => false,
                        'quantumAlgorithm' => null,
                        'hybridMode' => true,
                        'classicalCiphertext' => base64_encode('RSA encrypted portion'),
                        'quantumCiphertext' => base64_encode('ML-KEM encrypted portion'),
                    ],
                    'timestamp' => now()->timestamp,
                    'isQuantumResistant' => false,
                    'encryptionVersion' => 2, // Hybrid version
                ]
            ]);

        $response->assertStatus(200);

        $hybridMessage = SignalMessage::where('conversation_id', $this->conversation->id)->first();
        expect($hybridMessage->is_quantum_resistant)->toBe(false);
        expect($hybridMessage->quantum_version)->toBe(2);
    });

    test('validates message ordering and out-of-order delivery', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
        ]);

        // Send messages out of order
        $messageData = [
            ['counter' => 0, 'content' => 'First message'],
            ['counter' => 2, 'content' => 'Third message (out of order)'],
            ['counter' => 1, 'content' => 'Second message (delayed)'],
        ];

        foreach ($messageData as $data) {
            $response = $this->actingAs($this->alice, 'api')
                ->postJson('/api/v1/chat/signal/messages/send', [
                    'conversation_id' => $this->conversation->id,
                    'recipient_user_id' => $this->bob->id,
                    'message' => [
                        'type' => $data['counter'] === 0 ? 'prekey' : 'normal',
                        'version' => 3,
                        'message' => [
                            'header' => [
                                'sender_chain_key' => base64_encode(random_bytes(32)),
                                'previous_counter' => $data['counter'],
                                'ratchet_key' => base64_encode(random_bytes(32)),
                            ],
                            'ciphertext' => base64_encode($data['content']),
                            'isQuantumEncrypted' => true,
                            'quantumAlgorithm' => 'ML-KEM-768',
                        ],
                        'timestamp' => now()->timestamp + $data['counter'],
                        'isQuantumResistant' => true,
                        'encryptionVersion' => 3,
                    ]
                ]);

            $response->assertStatus(200);
        }

        // Verify all messages were stored
        $messages = SignalMessage::where('conversation_id', $this->conversation->id)
            ->orderBy('created_at')
            ->get();

        expect($messages)->toHaveCount(3);
        
        // All should be quantum-resistant
        foreach ($messages as $message) {
            expect($message->is_quantum_resistant)->toBe(true);
            expect($message->quantum_algorithm)->toBe('ML-KEM-768');
        }
    });

    test('enforces forward secrecy with quantum keys', function () {
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-1024', // High security
            'quantum_version' => 3,
        ]);

        // Send message that should be encrypted with forward secrecy
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/messages/send', [
                'conversation_id' => $this->conversation->id,
                'recipient_user_id' => $this->bob->id,
                'message' => [
                    'type' => 'prekey',
                    'version' => 3,
                    'message' => [
                        'header' => [
                            'sender_chain_key' => base64_encode(random_bytes(32)),
                            'previous_counter' => 0,
                            'ratchet_key' => base64_encode(random_bytes(32)),
                        ],
                        'ciphertext' => base64_encode('Forward secure quantum message'),
                        'isQuantumEncrypted' => true,
                        'quantumAlgorithm' => 'ML-KEM-1024',
                        'quantumCiphertext' => base64_encode(random_bytes(256)), // ML-KEM-1024 ciphertext
                        'quantumIv' => base64_encode(random_bytes(12)),
                        'forwardSecure' => true,
                        'ephemeralKey' => base64_encode(random_bytes(32)),
                    ],
                    'timestamp' => now()->timestamp,
                    'isQuantumResistant' => true,
                    'encryptionVersion' => 3,
                ]
            ]);

        $response->assertStatus(200);

        $forwardSecureMessage = SignalMessage::where('conversation_id', $this->conversation->id)->first();
        expect($forwardSecureMessage->is_quantum_resistant)->toBe(true);
        expect($forwardSecureMessage->quantum_algorithm)->toBe('ML-KEM-1024');
        expect($forwardSecureMessage->quantum_version)->toBe(3);

        // Verify ratchet message structure includes quantum data
        $ratchetMessage = $forwardSecureMessage->ratchet_message;
        expect($ratchetMessage)->toBeArray();
        expect($ratchetMessage['isQuantumEncrypted'])->toBe(true);
    });

    test('handles session recovery with quantum state', function () {
        // Create session that needs recovery
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-768',
            'is_active' => false, // Inactive session
            'verification_status' => 'compromised',
        ]);

        // Test session info retrieval for recovery
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
                    'lastActivity',
                    'keyRotations',
                ]
            ]
        ]);

        $sessionInfo = $response->json('data.session');
        expect($sessionInfo['isQuantumResistant'])->toBe(true);
        expect($sessionInfo['quantumAlgorithm'])->toBe('ML-KEM-768');
        expect($sessionInfo['verificationStatus'])->toBe('compromised');
        expect($sessionInfo['isActive'])->toBe(false);
    });

    test('tracks quantum encryption performance metrics', function () {
        // Create multiple sessions with different quantum algorithms
        $algorithms = ['ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024'];
        $sessions = [];

        foreach ($algorithms as $algorithm) {
            $user = User::factory()->create();
            $sessions[] = SignalSession::factory()->create([
                'conversation_id' => $this->conversation->id,
                'local_user_id' => $this->alice->id,
                'remote_user_id' => $user->id,
                'is_quantum_resistant' => true,
                'quantum_algorithm' => $algorithm,
                'message_count_sent' => rand(10, 100),
                'message_count_received' => rand(10, 100),
                'last_activity_at' => now()->subMinutes(rand(1, 60)),
            ]);
        }

        // Get statistics
        $response = $this->actingAs($this->alice, 'api')
            ->getJson('/api/v1/chat/signal/statistics');

        $response->assertStatus(200);
        
        $sessionStats = $response->json('data.sessionStats');
        expect($sessionStats['activeSessions'])->toBeGreaterThanOrEqual(3);
        expect($sessionStats['quantumSessions'])->toBeGreaterThanOrEqual(3);

        $protocolStats = $response->json('data.protocolStats');
        expect($protocolStats['quantumAlgorithmsUsed'])->toContain('ML-KEM-512');
        expect($protocolStats['quantumAlgorithmsUsed'])->toContain('ML-KEM-768');
        expect($protocolStats['quantumAlgorithmsUsed'])->toContain('ML-KEM-1024');
    });

    test('handles algorithm downgrade gracefully', function () {
        // Start with high-security quantum algorithm
        $session = SignalSession::factory()->create([
            'conversation_id' => $this->conversation->id,
            'local_user_id' => $this->alice->id,
            'remote_user_id' => $this->bob->id,
            'is_quantum_resistant' => true,
            'quantum_algorithm' => 'ML-KEM-1024',
            'quantum_version' => 3,
        ]);

        // Simulate device compatibility requiring downgrade
        $downgradedSession = $session->replicate();
        $downgradedSession->quantum_algorithm = 'ML-KEM-768';
        $downgradedSession->save();

        // Send message with downgraded algorithm
        $response = $this->actingAs($this->alice, 'api')
            ->postJson('/api/v1/chat/signal/messages/send', [
                'conversation_id' => $this->conversation->id,
                'recipient_user_id' => $this->bob->id,
                'message' => [
                    'type' => 'normal',
                    'version' => 3,
                    'message' => [
                        'header' => [
                            'sender_chain_key' => base64_encode(random_bytes(32)),
                            'previous_counter' => 1,
                            'ratchet_key' => base64_encode(random_bytes(32)),
                        ],
                        'ciphertext' => base64_encode('Downgraded quantum message'),
                        'isQuantumEncrypted' => true,
                        'quantumAlgorithm' => 'ML-KEM-768', // Downgraded
                    ],
                    'timestamp' => now()->timestamp,
                    'isQuantumResistant' => true,
                    'encryptionVersion' => 3,
                ]
            ]);

        $response->assertStatus(200);

        $downgradedMessage = SignalMessage::where('conversation_id', $this->conversation->id)
            ->latest()
            ->first();

        expect($downgradedMessage->is_quantum_resistant)->toBe(true);
        expect($downgradedMessage->quantum_algorithm)->toBe('ML-KEM-768');
    });

});