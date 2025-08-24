<?php

namespace Tests\Unit\Services;

use App\Events\EncryptionKeyRotated;
use App\Models\Chat\Conversation;
use App\Models\Chat\DeviceKeyShare;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Participant;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EnhancedMultiDeviceEncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MultiDeviceEncryptionService $service;

    private ChatEncryptionService $encryptionService;

    private User $user;

    private UserDevice $device1;

    private UserDevice $device2;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryptionService = $this->createMock(ChatEncryptionService::class);
        $this->service = new MultiDeviceEncryptionService($this->encryptionService);

        $this->user = User::factory()->create();

        $this->device1 = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'is_trusted' => true,
            'is_active' => true,
            'security_level' => 'high',
            'device_capabilities' => ['messaging', 'encryption'],
            'device_info' => ['test' => true],
        ]);

        $this->device2 = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'is_trusted' => true,
            'is_active' => true,
            'security_level' => 'medium',
            'device_capabilities' => ['messaging'],
            'device_info' => ['test' => true],
        ]);

        $this->conversation = Conversation::factory()->create();

        Participant::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);
    }

    /** @test */
    public function it_can_register_device_with_enhanced_features()
    {
        $result = $this->service->registerDevice(
            $this->user,
            'Test Device',
            'desktop',
            'test-public-key',
            'test-fingerprint',
            'linux',
            'Test User Agent',
            ['messaging', 'encryption', 'biometric'],
            'maximum',
            ['screen' => '1920x1080', 'timezone' => 'UTC']
        );

        $this->assertInstanceOf(UserDevice::class, $result);
        $this->assertEquals('Test Device', $result->device_name);
        $this->assertEquals('desktop', $result->device_type);
        $this->assertEquals('test-public-key', $result->public_key);
        $this->assertEquals('test-fingerprint', $result->device_fingerprint);
        $this->assertEquals('linux', $result->platform);
        $this->assertEquals(['messaging', 'encryption', 'biometric'], $result->device_capabilities);
        $this->assertEquals('maximum', $result->security_level);
        $this->assertEquals(['screen' => '1920x1080', 'timezone' => 'UTC'], $result->device_info);
        $this->assertEquals(2, $result->encryption_version);
        $this->assertFalse($result->is_trusted);
        $this->assertTrue($result->is_active);
    }

    /** @test */
    public function it_updates_existing_device_on_re_registration()
    {
        $existingDevice = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'device_fingerprint' => 'existing-fingerprint',
            'device_name' => 'Old Name',
        ]);

        $result = $this->service->registerDevice(
            $this->user,
            'Updated Device',
            'mobile',
            'new-public-key',
            'existing-fingerprint',
            'android',
            'Updated User Agent',
            ['messaging'],
            'low',
            ['version' => '2.0']
        );

        $this->assertEquals($existingDevice->id, $result->id);
        $this->assertEquals('Updated Device', $result->device_name);
        $this->assertEquals('mobile', $result->device_type);
        $this->assertEquals('new-public-key', $result->public_key);
        $this->assertEquals('android', $result->platform);
    }

    /** @test */
    public function it_can_share_keys_with_new_device()
    {
        // Create encryption keys for device1
        $encryptionKey = EncryptionKey::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'device_id' => $this->device1->id,
            'is_active' => true,
        ]);

        $result = $this->service->shareKeysWithNewDevice($this->device1, $this->device2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('shared_conversations', $result);
        $this->assertArrayHasKey('failed_conversations', $result);
        $this->assertArrayHasKey('total_keys_shared', $result);
        $this->assertGreaterThan(0, $result['total_keys_shared']);

        // Verify key share was created
        $this->assertDatabaseHas('device_key_shares', [
            'from_device_id' => $this->device1->id,
            'to_device_id' => $this->device2->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_prevents_key_sharing_between_different_users_devices()
    {
        $otherUser = User::factory()->create();
        $otherDevice = UserDevice::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Devices must belong to the same user');

        $this->service->shareKeysWithNewDevice($this->device1, $otherDevice);
    }

    /** @test */
    public function it_can_accept_key_share()
    {
        $keyShare = DeviceKeyShare::factory()->create([
            'from_device_id' => $this->device1->id,
            'to_device_id' => $this->device2->id,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'is_active' => true,
            'expires_at' => now()->addDays(1),
        ]);

        $result = $this->service->acceptKeyShare($this->device2, $keyShare, 'test-symmetric-key');

        $this->assertInstanceOf(EncryptionKey::class, $result);
        $this->assertEquals($this->conversation->id, $result->conversation_id);
        $this->assertEquals($this->device2->id, $result->device_id);

        // Verify key share was marked as accepted
        $keyShare->refresh();
        $this->assertTrue($keyShare->is_accepted);
    }

    /** @test */
    public function it_rejects_expired_key_share()
    {
        $keyShare = DeviceKeyShare::factory()->create([
            'to_device_id' => $this->device2->id,
            'expires_at' => now()->subDays(1),
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Key share is expired or inactive');

        $this->service->acceptKeyShare($this->device2, $keyShare, 'test-symmetric-key');
    }

    /** @test */
    public function it_can_rotate_conversation_keys()
    {
        $this->encryptionService->expects($this->once())
            ->method('generateSymmetricKey')
            ->willReturn('new-symmetric-key');

        // Create existing encryption keys
        EncryptionKey::factory()->create([
            'conversation_id' => $this->conversation->id,
            'device_id' => $this->device1->id,
            'key_version' => 1,
            'is_active' => true,
        ]);

        EncryptionKey::factory()->create([
            'conversation_id' => $this->conversation->id,
            'device_id' => $this->device2->id,
            'key_version' => 1,
            'is_active' => true,
        ]);

        Event::fake([EncryptionKeyRotated::class]);

        $result = $this->service->rotateConversationKeys($this->conversation, $this->device1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rotated_devices', $result);
        $this->assertArrayHasKey('failed_devices', $result);
        $this->assertArrayHasKey('key_version', $result);
        $this->assertEquals(2, $result['key_version']);

        // Verify old keys were deactivated
        $this->assertDatabaseHas('chat_encryption_keys', [
            'conversation_id' => $this->conversation->id,
            'key_version' => 1,
            'is_active' => false,
        ]);

        // Verify new keys were created
        $this->assertDatabaseHas('chat_encryption_keys', [
            'conversation_id' => $this->conversation->id,
            'key_version' => 2,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_revoke_device_access()
    {
        // Create encryption keys for the device
        $key1 = EncryptionKey::factory()->create([
            'device_id' => $this->device1->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true,
        ]);

        $key2 = EncryptionKey::factory()->create([
            'device_id' => $this->device1->id,
            'conversation_id' => Conversation::factory()->create()->id,
            'is_active' => true,
        ]);

        // Create pending key share
        $keyShare = DeviceKeyShare::factory()->create([
            'to_device_id' => $this->device1->id,
            'is_active' => true,
            'is_accepted' => false,
        ]);

        $result = $this->service->revokeDeviceAccess($this->device1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('revoked_keys', $result);
        $this->assertArrayHasKey('cancelled_shares', $result);
        $this->assertEquals(2, $result['revoked_keys']);
        $this->assertEquals(1, $result['cancelled_shares']);

        // Verify keys were deactivated
        $key1->refresh();
        $key2->refresh();
        $keyShare->refresh();

        $this->assertFalse($key1->is_active);
        $this->assertFalse($key2->is_active);
        $this->assertFalse($keyShare->is_active);
    }

    /** @test */
    public function it_can_revoke_access_for_specific_conversation()
    {
        $otherConversation = Conversation::factory()->create();

        $key1 = EncryptionKey::factory()->create([
            'device_id' => $this->device1->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true,
        ]);

        $key2 = EncryptionKey::factory()->create([
            'device_id' => $this->device1->id,
            'conversation_id' => $otherConversation->id,
            'is_active' => true,
        ]);

        $result = $this->service->revokeDeviceAccess($this->device1, $this->conversation->id);

        $this->assertEquals(1, $result['revoked_keys']);

        // Verify only the specified conversation's key was revoked
        $key1->refresh();
        $key2->refresh();

        $this->assertFalse($key1->is_active);
        $this->assertTrue($key2->is_active);
    }

    /** @test */
    public function it_can_cleanup_expired_key_shares()
    {
        // Create expired key shares
        DeviceKeyShare::factory()->create([
            'expires_at' => now()->subHours(1),
            'is_accepted' => false,
            'is_active' => true,
        ]);

        DeviceKeyShare::factory()->create([
            'expires_at' => now()->subDays(1),
            'is_accepted' => false,
            'is_active' => true,
        ]);

        // Create non-expired key share (should not be affected)
        DeviceKeyShare::factory()->create([
            'expires_at' => now()->addDays(1),
            'is_accepted' => false,
            'is_active' => true,
        ]);

        // Create expired but already accepted key share (should not be affected)
        DeviceKeyShare::factory()->create([
            'expires_at' => now()->subHours(1),
            'is_accepted' => true,
            'is_active' => true,
        ]);

        $result = $this->service->cleanupExpiredKeyShares();

        $this->assertEquals(2, $result);

        // Verify expired unaccepted shares were deactivated
        $this->assertEquals(2, DeviceKeyShare::where('is_active', false)->count());
        $this->assertEquals(2, DeviceKeyShare::where('is_active', true)->count());
    }

    /** @test */
    public function it_can_generate_device_encryption_summary()
    {
        // Create encryption keys
        EncryptionKey::factory()->count(3)->create([
            'device_id' => $this->device1->id,
            'is_active' => true,
        ]);

        // Create pending key shares
        DeviceKeyShare::factory()->count(2)->create([
            'to_device_id' => $this->device1->id,
            'is_active' => true,
            'is_accepted' => false,
        ]);

        $summary = $this->service->getDeviceEncryptionSummary($this->device1);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('device_id', $summary);
        $this->assertArrayHasKey('device_name', $summary);
        $this->assertArrayHasKey('is_trusted', $summary);
        $this->assertArrayHasKey('security_level', $summary);
        $this->assertArrayHasKey('security_score', $summary);
        $this->assertArrayHasKey('active_conversation_keys', $summary);
        $this->assertArrayHasKey('pending_key_shares', $summary);

        $this->assertEquals($this->device1->id, $summary['device_id']);
        $this->assertEquals(3, $summary['active_conversation_keys']);
        $this->assertEquals(2, $summary['pending_key_shares']);
    }

    /** @test */
    public function it_can_verify_device_integrity()
    {
        // Create a device with some issues
        $device = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'encryption_version' => 1, // Outdated
            'last_used_at' => now()->subDays(100), // Not used recently
            'is_trusted' => false,
        ]);

        $result = $this->service->verifyDeviceIntegrity($device);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('device_id', $result);
        $this->assertArrayHasKey('security_score', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('recommendations', $result);

        $this->assertEquals($device->id, $result['device_id']);
        $this->assertGreaterThan(0, count($result['issues']));
        $this->assertGreaterThan(0, count($result['recommendations']));
        $this->assertEquals('warning', $result['status']);
    }

    /** @test */
    public function it_can_initiate_device_verification()
    {
        $result = $this->service->initiateDeviceVerification($this->device1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('verification_methods', $result);

        $challenge = $result['challenge'];
        $this->assertArrayHasKey('challenge_id', $challenge);
        $this->assertArrayHasKey('device_id', $challenge);
        $this->assertArrayHasKey('timestamp', $challenge);
        $this->assertArrayHasKey('nonce', $challenge);

        // Verify challenge is cached
        $cachedChallenge = Cache::get("device_verification_{$this->device1->id}");
        $this->assertNotNull($cachedChallenge);
        $this->assertEquals($challenge['challenge_id'], $cachedChallenge['challenge_id']);
    }

    /** @test */
    public function it_can_complete_device_verification_successfully()
    {
        // Set up challenge
        $challenge = [
            'challenge_id' => 'test-challenge-id',
            'device_id' => $this->device1->id,
            'timestamp' => now()->timestamp,
            'nonce' => 'test-nonce',
            'verification_type' => 'security_key',
        ];

        Cache::put("device_verification_{$this->device1->id}", $challenge, now()->addMinutes(5));

        // Mock successful signature verification
        $this->service = $this->getMockBuilder(MultiDeviceEncryptionService::class)
            ->setConstructorArgs([$this->encryptionService])
            ->onlyMethods(['verifySignature'])
            ->getMock();

        $this->service->expects($this->once())
            ->method('verifySignature')
            ->willReturn(true);

        $result = $this->service->completeDeviceVerification(
            $this->device1,
            'test-challenge-id',
            ['signature' => 'test-signature']
        );

        $this->assertTrue($result);

        // Verify device was updated
        $this->device1->refresh();
        $this->assertTrue($this->device1->is_trusted);
        $this->assertNotNull($this->device1->verified_at);
        $this->assertEquals(0, $this->device1->failed_auth_attempts);
    }

    /** @test */
    public function it_handles_failed_device_verification()
    {
        $challenge = [
            'challenge_id' => 'test-challenge-id',
            'device_id' => $this->device1->id,
            'timestamp' => now()->timestamp,
            'nonce' => 'test-nonce',
            'verification_type' => 'security_key',
        ];

        Cache::put("device_verification_{$this->device1->id}", $challenge, now()->addMinutes(5));

        // Mock failed signature verification
        $this->service = $this->getMockBuilder(MultiDeviceEncryptionService::class)
            ->setConstructorArgs([$this->encryptionService])
            ->onlyMethods(['verifySignature'])
            ->getMock();

        $this->service->expects($this->once())
            ->method('verifySignature')
            ->willReturn(false);

        $result = $this->service->completeDeviceVerification(
            $this->device1,
            'test-challenge-id',
            ['signature' => 'invalid-signature']
        );

        $this->assertFalse($result);

        // Verify device failed auth attempts were incremented
        $this->device1->refresh();
        $this->assertEquals(1, $this->device1->failed_auth_attempts);
    }

    /** @test */
    public function it_locks_device_after_multiple_failed_verification_attempts()
    {
        $this->device1->update(['failed_auth_attempts' => 4]);

        $challenge = [
            'challenge_id' => 'test-challenge-id',
            'device_id' => $this->device1->id,
            'timestamp' => now()->timestamp,
            'nonce' => 'test-nonce',
            'verification_type' => 'security_key',
        ];

        Cache::put("device_verification_{$this->device1->id}", $challenge, now()->addMinutes(5));

        $this->service = $this->getMockBuilder(MultiDeviceEncryptionService::class)
            ->setConstructorArgs([$this->encryptionService])
            ->onlyMethods(['verifySignature'])
            ->getMock();

        $this->service->expects($this->once())
            ->method('verifySignature')
            ->willReturn(false);

        $result = $this->service->completeDeviceVerification(
            $this->device1,
            'test-challenge-id',
            ['signature' => 'invalid-signature']
        );

        $this->assertFalse($result);

        $this->device1->refresh();
        $this->assertEquals(5, $this->device1->failed_auth_attempts);
        $this->assertNotNull($this->device1->locked_until);
        $this->assertTrue($this->device1->locked_until->isFuture());
    }

    /** @test */
    public function it_can_create_device_session()
    {
        $sessionData = [
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Test User Agent',
            'location' => 'Test Location',
        ];

        $sessionId = $this->service->createDeviceSession($this->device1, $sessionData);

        $this->assertNotEmpty($sessionId);

        // Verify session was cached
        $sessionKey = "device_session_{$this->device1->id}_{$sessionId}";
        $session = Cache::get($sessionKey);

        $this->assertNotNull($session);
        $this->assertEquals($sessionId, $session['session_id']);
        $this->assertEquals($this->device1->id, $session['device_id']);
        $this->assertEquals($this->device1->user_id, $session['user_id']);
        $this->assertEquals('192.168.1.100', $session['ip_address']);
        $this->assertEquals('Test User Agent', $session['user_agent']);
        $this->assertEquals('Test Location', $session['location']);
    }

    /** @test */
    public function it_can_update_device_session_activity()
    {
        $sessionId = $this->service->createDeviceSession($this->device1);

        $result = $this->service->updateDeviceSessionActivity(
            $this->device1,
            $sessionId,
            'message_sent',
            ['conversation_id' => $this->conversation->id]
        );

        $this->assertTrue($result);

        // Verify session was updated
        $sessionKey = "device_session_{$this->device1->id}_{$sessionId}";
        $session = Cache::get($sessionKey);

        $this->assertCount(1, $session['activities']);
        $activity = $session['activities'][0];
        $this->assertEquals('message_sent', $activity['activity']);
        $this->assertEquals(['conversation_id' => $this->conversation->id], $activity['metadata']);
    }

    /** @test */
    public function it_can_terminate_device_session()
    {
        $sessionId = $this->service->createDeviceSession($this->device1);

        $result = $this->service->terminateDeviceSession($this->device1, $sessionId);

        $this->assertTrue($result);

        // Verify session was removed from cache
        $sessionKey = "device_session_{$this->device1->id}_{$sessionId}";
        $this->assertNull(Cache::get($sessionKey));
    }

    /** @test */
    public function it_can_generate_security_report()
    {
        // Create some test data
        EncryptionKey::factory()->count(3)->create(['user_id' => $this->user->id]);

        $report = $this->service->generateSecurityReport($this->user);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('user_id', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('overall_security_score', $report);
        $this->assertArrayHasKey('devices', $report);
        $this->assertArrayHasKey('security_alerts', $report);
        $this->assertArrayHasKey('recommendations', $report);
        $this->assertArrayHasKey('statistics', $report);

        $this->assertEquals($this->user->id, $report['user_id']);
        $this->assertGreaterThanOrEqual(0, $report['overall_security_score']);
        $this->assertLessThanOrEqual(100, $report['overall_security_score']);
    }

    /** @test */
    public function it_can_monitor_encryption_health()
    {
        $health = $this->service->monitorEncryptionHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertArrayHasKey('overall_status', $health);
        $this->assertArrayHasKey('metrics', $health);
        $this->assertArrayHasKey('alerts', $health);

        $this->assertContains($health['overall_status'], ['healthy', 'warning', 'critical']);
    }

    /** @test */
    public function it_can_perform_security_maintenance()
    {
        // Create expired key shares
        DeviceKeyShare::factory()->count(2)->create([
            'expires_at' => now()->subHours(1),
            'is_accepted' => false,
            'is_active' => true,
        ]);

        // Create inactive devices
        UserDevice::factory()->count(3)->create([
            'last_used_at' => now()->subDays(100),
            'is_active' => true,
        ]);

        $results = $this->service->performSecurityMaintenance();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('timestamp', $results);
        $this->assertArrayHasKey('actions_performed', $results);
        $this->assertArrayHasKey('cleanup_stats', $results);

        $this->assertGreaterThan(0, count($results['actions_performed']));
        $this->assertEquals(2, $results['cleanup_stats']['expired_key_shares_cleaned']);
        $this->assertEquals(3, $results['cleanup_stats']['inactive_devices_processed']);
    }
}
