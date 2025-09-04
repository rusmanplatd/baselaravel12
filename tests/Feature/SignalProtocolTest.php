<?php

use App\Models\User;
use App\Models\Chat\SignalIdentityKey;
use App\Models\Chat\SignalSignedPrekey;
use App\Models\Chat\SignalOnetimePrekey;

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
        'identity_key' => [
            'public_key' => base64_encode('identity_public_key'),
            'key_fingerprint' => hash('sha256', 'identity_public_key'),
        ],
        'signed_prekey' => [
            'key_id' => 1,
            'public_key' => base64_encode('signed_prekey_public'),
            'signature' => base64_encode('signature_data'),
            'generated_at' => now()->toISOString(),
        ],
        'onetime_prekeys' => [
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
                 'message',
                 'bundle_id',
                 'keys_uploaded' => [
                     'identity_key',
                     'signed_prekey',
                     'onetime_prekeys',
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
                 'bundle' => [
                     'registration_id',
                     'identity_key' => [
                         'public_key',
                         'key_fingerprint',
                     ],
                     'signed_prekey' => [
                         'key_id',
                         'public_key',
                         'signature',
                         'generated_at',
                     ],
                     'onetime_prekey' => [
                         'key_id',
                         'public_key',
                     ],
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