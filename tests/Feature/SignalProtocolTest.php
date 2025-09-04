<?php

use App\Models\Chat\Conversation;
use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalOnetimePrekey;
use App\Models\Chat\SignalSession;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
});

test('can create signal identity key', function () {
    $identityKey = SignalIdentityKey::create([
        'user_id' => $this->user->id,
        'registration_id' => 12345,
        'public_key' => base64_encode('dummy_public_key'),
        'private_key_encrypted' => base64_encode('encrypted_private_key'),
        'key_fingerprint' => hash('sha256', 'dummy_public_key'),
        'is_active' => true,
    ]);

    expect($identityKey)->toBeInstanceOf(SignalIdentityKey::class);
    expect($identityKey->user_id)->toBe($this->user->id);
    expect($identityKey->registration_id)->toBe(12345);
    expect($identityKey->is_active)->toBe(true);
});

test('can create signal signed prekey', function () {
    $identityKey = SignalIdentityKey::create([
        'user_id' => $this->user->id,
        'registration_id' => 12345,
        'public_key' => base64_encode('dummy_public_key'),
        'private_key_encrypted' => base64_encode('encrypted_private_key'),
        'key_fingerprint' => hash('sha256', 'dummy_public_key'),
        'is_active' => true,
    ]);

    $signedPrekey = SignalSignedPrekey::create([
        'user_id' => $this->user->id,
        'key_id' => 1,
        'public_key' => base64_encode('signed_prekey_public'),
        'private_key_encrypted' => base64_encode('signed_prekey_private'),
        'signature' => base64_encode('signature_data'),
        'generated_at' => now(),
        'is_active' => true,
    ]);

    expect($signedPrekey)->toBeInstanceOf(SignalSignedPrekey::class);
    expect($signedPrekey->user_id)->toBe($this->user->id);
    expect($signedPrekey->key_id)->toBe(1);
    expect($signedPrekey->is_active)->toBe(true);
});

test('can create signal one-time prekey', function () {
    $onetimePrekey = SignalOnetimePrekey::create([
        'user_id' => $this->user->id,
        'key_id' => 1,
        'public_key' => base64_encode('onetime_prekey_public'),
        'private_key_encrypted' => base64_encode('onetime_prekey_private'),
        'is_used' => false,
    ]);

    expect($onetimePrekey)->toBeInstanceOf(SignalOnetimePrekey::class);
    expect($onetimePrekey->user_id)->toBe($this->user->id);
    expect($onetimePrekey->key_id)->toBe(1);
    expect($onetimePrekey->is_used)->toBe(false);
});

test('signal protocol statistics endpoint requires authentication', function () {
    $response = $this->getJson('/api/v1/chat/signal/statistics');

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('authenticated user can access signal protocol statistics', function () {
    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/chat/signal/statistics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'statistics' => [
                'user_id',
                'identity_keys',
                'signed_prekeys',
                'onetime_prekeys_available',
                'onetime_prekeys_used',
                'active_sessions',
                'total_sessions',
                'messages_sent',
                'messages_received',
                'verified_sessions',
                'key_rotations_performed',
            ],
            'recent_sessions',
            'health_score' => [
                'score',
                'status',
                'issues',
            ],
        ]);
});

test('can upload prekey bundle', function () {
    $bundleData = [
        'registration_id' => 12345,
        'identity_key' => base64_encode('identity_public_key'),
        'signed_pre_key' => [
            'key_id' => 1,
            'public_key' => base64_encode('signed_prekey_public'),
            'signature' => base64_encode('signature_data'),
        ],
        'one_time_pre_keys' => [
            [
                'key_id' => 1,
                'public_key' => base64_encode('onetime_prekey_1'),
            ],
            [
                'key_id' => 2,
                'public_key' => base64_encode('onetime_prekey_2'),
            ],
        ],
    ];

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/v1/chat/signal/upload-bundle', $bundleData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'stats' => [
                'identity_key_id',
                'signed_prekey_count',
                'onetime_prekey_count',
            ],
        ]);

    // Verify data was actually stored
    $this->assertDatabaseHas('signal_identity_keys', [
        'user_id' => $this->user->id,
        'registration_id' => 12345,
    ]);

    $this->assertDatabaseHas('signal_signed_prekeys', [
        'user_id' => $this->user->id,
        'key_id' => 1,
    ]);

    $this->assertDatabaseHas('signal_onetime_prekeys', [
        'user_id' => $this->user->id,
        'key_id' => 1,
    ]);
});

test('can retrieve prekey bundle for other user', function () {
    $otherUser = User::factory()->create();

    // Create identity key and prekeys for the other user
    SignalIdentityKey::create([
        'user_id' => $otherUser->id,
        'registration_id' => 54321,
        'public_key' => base64_encode('other_identity_public_key'),
        'private_key_encrypted' => base64_encode('other_identity_private_key'),
        'key_fingerprint' => hash('sha256', 'other_identity_public_key'),
        'is_active' => true,
    ]);

    SignalSignedPrekey::create([
        'user_id' => $otherUser->id,
        'key_id' => 1,
        'public_key' => base64_encode('other_signed_prekey_public'),
        'private_key_encrypted' => base64_encode('other_signed_prekey_private'),
        'signature' => base64_encode('other_signature_data'),
        'generated_at' => now(),
        'is_active' => true,
    ]);

    SignalOnetimePrekey::create([
        'user_id' => $otherUser->id,
        'key_id' => 1,
        'public_key' => base64_encode('other_onetime_prekey_public'),
        'private_key_encrypted' => base64_encode('other_onetime_prekey_private'),
        'is_used' => false,
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/chat/signal/prekey-bundle/{$otherUser->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'request_id',
            'registration_id',
            'identity_key',
            'signed_pre_key' => [
                'key_id',
                'public_key',
                'signature',
            ],
            'one_time_pre_key' => [
                'key_id',
                'public_key',
            ],
        ]);

    // Verify the one-time prekey is marked as used
    $this->assertDatabaseHas('signal_onetime_prekeys', [
        'user_id' => $otherUser->id,
        'key_id' => 1,
        'is_used' => true,
        'used_by_user_id' => $this->user->id,
    ]);
});

test('can send signal protocol message', function () {
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create();

    // Add both users as participants
    \App\Models\Chat\Participant::create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->user->id,
        'role' => 'member',
    ]);
    \App\Models\Chat\Participant::create([
        'conversation_id' => $conversation->id,
        'user_id' => $otherUser->id,
        'role' => 'member',
    ]);

    $messageData = [
        'conversation_id' => $conversation->id,
        'recipient_user_id' => $otherUser->id,
        'signal_message' => [
            'type' => 'prekey',
            'version' => 3,
            'registration_id' => 12345,
            'prekey_id' => 1,
            'signed_prekey_id' => 1,
            'base_key' => base64_encode('base_key_data'),
            'identity_key' => base64_encode('identity_key_data'),
            'message' => [
                'ciphertext' => base64_encode('encrypted_message'),
                'counter' => 0,
                'previousCounter' => 0,
            ],
        ],
        'delivery_options' => [
            'priority' => 'high',
            'require_receipt' => true,
        ],
    ];

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/v1/chat/signal/messages/send', $messageData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message_id',
            'session_id',
            'delivery_status',
        ]);

    // Verify message was stored
    $this->assertDatabaseHas('signal_messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $this->user->id,
        'recipient_user_id' => $otherUser->id,
        'message_type' => 'prekey',
        'protocol_version' => 3,
    ]);
});

test('can get session information', function () {
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create();

    // Create a Signal session
    $session = SignalSession::create([
        'session_id' => 'test-session-123',
        'conversation_id' => $conversation->id,
        'local_user_id' => $this->user->id,
        'remote_user_id' => $otherUser->id,
        'local_registration_id' => 12345,
        'remote_registration_id' => 54321,
        'remote_identity_key' => base64_encode('remote_identity_key'),
        'session_state_encrypted' => base64_encode('encrypted_session_state'),
        'is_active' => true,
        'verification_status' => 'unverified',
        'protocol_version' => '3.0',
        'last_activity_at' => now(),
    ]);

    $response = $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/chat/signal/sessions/info?'.http_build_query([
            'conversation_id' => $conversation->id,
            'user_id' => $otherUser->id,
        ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'session' => [
                'session_id',
                'remote_user_id',
                'is_active',
                'verification_status',
                'protocol_version',
                'messages_sent',
                'messages_received',
                'key_rotations',
                'last_activity_at',
                'created_at',
                'remote_identity_fingerprint',
                'is_verified',
                'age_in_days',
            ],
        ]);
});

test('can verify user identity', function () {
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create();

    // Create a Signal session
    $session = SignalSession::create([
        'session_id' => 'test-session-456',
        'conversation_id' => $conversation->id,
        'local_user_id' => $this->user->id,
        'remote_user_id' => $otherUser->id,
        'local_registration_id' => 12345,
        'remote_registration_id' => 54321,
        'remote_identity_key' => base64_encode('remote_identity_key'),
        'session_state_encrypted' => base64_encode('encrypted_session_state'),
        'is_active' => true,
        'verification_status' => 'unverified',
        'protocol_version' => '3.0',
        'last_activity_at' => now(),
    ]);

    $fingerprint = hash('sha256', base64_decode($session->remote_identity_key));

    $verificationData = [
        'conversation_id' => $conversation->id,
        'user_id' => $otherUser->id,
        'fingerprint' => $fingerprint,
        'verification_method' => 'fingerprint',
    ];

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/v1/chat/signal/identity/verify', $verificationData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'verification_successful',
            'session_verified',
            'message',
        ]);

    // Verify identity verification record was created
    $this->assertDatabaseHas('signal_identity_verifications', [
        'verifier_user_id' => $this->user->id,
        'target_user_id' => $otherUser->id,
        'verification_method' => 'fingerprint',
        'verification_successful' => true,
    ]);
});

test('can rotate session keys', function () {
    // Create a conversation first
    $conversation = Conversation::factory()->create([
        'created_by' => $this->user->id,
        'name' => 'Test Conversation',
        'type' => 'direct',
    ]);

    $otherUser = User::factory()->create();
    
    $session = SignalSession::create([
        'session_id' => 'test-session-789',
        'conversation_id' => $conversation->id,
        'local_user_id' => $this->user->id,
        'remote_user_id' => $otherUser->id,
        'local_registration_id' => 12345,
        'remote_registration_id' => 54321,
        'remote_identity_key' => base64_encode('remote_identity_key'),
        'session_state_encrypted' => base64_encode('encrypted_session_state'),
        'is_active' => true,
        'verification_status' => 'unverified',
        'protocol_version' => '3.0',
        'last_activity_at' => now(),
    ]);

    $rotationData = [
        'session_id' => $session->session_id,
        'reason' => 'Scheduled rotation',
    ];

    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/v1/chat/signal/sessions/rotate-keys', $rotationData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'session_id',
            'key_rotations',
        ]);

    // Verify key rotation was logged
    $this->assertDatabaseHas('signal_key_rotations', [
        'user_id' => $this->user->id,
        'session_id' => $session->id,
        'rotation_type' => 'session_keys',
        'reason' => 'Scheduled rotation',
    ]);
});
