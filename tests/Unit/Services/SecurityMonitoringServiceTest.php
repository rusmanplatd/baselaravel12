<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\SecurityMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecurityMonitoringService $service;

    private User $user;

    private UserDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SecurityMonitoringService;
        $this->user = User::factory()->create();
        $this->device = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'is_trusted' => true,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_log_security_events()
    {
        $event = $this->service->logSecurityEvent(
            'device_registration',
            'low',
            $this->device->id,
            $this->user->id,
            ['device_name' => 'Test Device', 'platform' => 'web']
        );

        $this->assertNotNull($event);
        $this->assertEquals('device_registration', $event['type']);
        $this->assertEquals('low', $event['severity']);
        $this->assertEquals($this->device->id, $event['device_id']);
        $this->assertEquals($this->user->id, $event['user_id']);
        $this->assertArrayHasKey('device_name', $event['details']);
    }

    /** @test */
    public function it_can_detect_suspicious_patterns()
    {
        // Log multiple failed verification attempts
        for ($i = 0; $i < 6; $i++) {
            $this->service->logSecurityEvent(
                'device_verification_failed',
                'medium',
                $this->device->id,
                $this->user->id,
                ['attempt' => $i + 1]
            );
        }

        $analysis = $this->service->analyzeSecurityTrends($this->device->id);

        $this->assertArrayHasKey('suspicious_patterns', $analysis);
        $this->assertArrayHasKey('risk_score', $analysis);
        $this->assertGreaterThan(50, $analysis['risk_score']);
    }

    /** @test */
    public function it_can_generate_security_report()
    {
        // Create various security events
        $this->service->logSecurityEvent('device_registration', 'low', $this->device->id, $this->user->id, []);
        $this->service->logSecurityEvent('message_decryption_failed', 'medium', $this->device->id, $this->user->id, []);
        $this->service->logSecurityEvent('suspicious_activity', 'high', $this->device->id, $this->user->id, []);

        $report = $this->service->generateSecurityReport($this->user->id);

        $this->assertArrayHasKey('total_events', $report);
        $this->assertArrayHasKey('security_score', $report);
        $this->assertArrayHasKey('threat_level', $report);
        $this->assertArrayHasKey('recommendations', $report);
        $this->assertArrayHasKey('recent_events', $report);

        $this->assertEquals(3, $report['total_events']);
        $this->assertIsArray($report['recommendations']);
        $this->assertIsArray($report['recent_events']);
    }

    /** @test */
    public function it_can_track_device_health_metrics()
    {
        // Simulate device usage and security events
        $this->service->trackDeviceActivity($this->device->id, 'message_sent');
        $this->service->trackDeviceActivity($this->device->id, 'key_rotation');

        // Log a security event
        $this->service->logSecurityEvent('encryption_key_rotation', 'low', $this->device->id, $this->user->id, []);

        $metrics = $this->service->getDeviceHealthMetrics($this->device->id);

        $this->assertArrayHasKey('device_id', $metrics);
        $this->assertArrayHasKey('health_score', $metrics);
        $this->assertArrayHasKey('last_activity', $metrics);
        $this->assertArrayHasKey('security_events_count', $metrics);
        $this->assertArrayHasKey('risk_indicators', $metrics);

        $this->assertEquals($this->device->id, $metrics['device_id']);
        $this->assertGreaterThan(0, $metrics['health_score']);
    }

    /** @test */
    public function it_can_detect_anomalous_behavior()
    {
        // Simulate normal activity pattern
        for ($i = 0; $i < 10; $i++) {
            $this->service->trackDeviceActivity($this->device->id, 'message_sent', [
                'timestamp' => now()->subMinutes($i * 5)->timestamp,
                'location' => 'New York',
            ]);
        }

        // Simulate anomalous activity (different location, rapid succession)
        for ($i = 0; $i < 5; $i++) {
            $this->service->trackDeviceActivity($this->device->id, 'message_sent', [
                'timestamp' => now()->timestamp + $i,
                'location' => 'Tokyo',
            ]);
        }

        $analysis = $this->service->detectAnomalousActivity($this->device->id);

        $this->assertArrayHasKey('anomalies_detected', $analysis);
        $this->assertArrayHasKey('anomaly_score', $analysis);
        $this->assertArrayHasKey('anomaly_types', $analysis);

        $this->assertTrue($analysis['anomalies_detected']);
        $this->assertGreaterThan(0, $analysis['anomaly_score']);
        $this->assertContains('location_change', $analysis['anomaly_types']);
    }

    /** @test */
    public function it_can_monitor_encryption_integrity()
    {
        // Log encryption-related events
        $this->service->logSecurityEvent('message_decryption_failed', 'medium', $this->device->id, $this->user->id, [
            'conversation_id' => 'conv-123',
            'key_version' => 1,
        ]);

        $this->service->logSecurityEvent('key_share_failed', 'high', $this->device->id, $this->user->id, [
            'target_device' => 'device-456',
        ]);

        $integrity = $this->service->checkEncryptionIntegrity($this->device->id);

        $this->assertArrayHasKey('integrity_score', $integrity);
        $this->assertArrayHasKey('encryption_health', $integrity);
        $this->assertArrayHasKey('detected_issues', $integrity);
        $this->assertArrayHasKey('recommendations', $integrity);

        $this->assertLessThan(100, $integrity['integrity_score']); // Should be reduced due to failures
        $this->assertContains('decryption_failures', array_column($integrity['detected_issues'], 'type'));
    }

    /** @test */
    public function it_can_calculate_risk_scores()
    {
        // Create a mix of security events
        $events = [
            ['type' => 'device_registration', 'severity' => 'low'],
            ['type' => 'device_verification_failed', 'severity' => 'medium'],
            ['type' => 'message_decryption_failed', 'severity' => 'medium'],
            ['type' => 'suspicious_activity', 'severity' => 'high'],
            ['type' => 'unauthorized_access_attempt', 'severity' => 'high'],
        ];

        foreach ($events as $event) {
            $this->service->logSecurityEvent(
                $event['type'],
                $event['severity'],
                $this->device->id,
                $this->user->id,
                []
            );
        }

        $riskScore = $this->service->calculateRiskScore($this->device->id);

        $this->assertIsFloat($riskScore);
        $this->assertGreaterThan(0, $riskScore);
        $this->assertLessThanOrEqual(100, $riskScore);

        // Should be elevated due to high severity events
        $this->assertGreaterThan(30, $riskScore);
    }

    /** @test */
    public function it_can_provide_security_recommendations()
    {
        // Create various security issues
        $this->service->logSecurityEvent('device_verification_failed', 'medium', $this->device->id, $this->user->id, []);
        $this->service->logSecurityEvent('message_decryption_failed', 'medium', $this->device->id, $this->user->id, []);
        $this->service->logSecurityEvent('key_share_failed', 'high', $this->device->id, $this->user->id, []);

        $recommendations = $this->service->generateSecurityRecommendations($this->device->id);

        $this->assertIsArray($recommendations);
        $this->assertGreaterThan(0, count($recommendations));

        // Should include device verification recommendation
        $verificationRecs = array_filter($recommendations, function ($rec) {
            return strpos($rec['description'], 'verification') !== false;
        });
        $this->assertGreaterThan(0, count($verificationRecs));

        // Should include encryption-related recommendations
        $encryptionRecs = array_filter($recommendations, function ($rec) {
            return strpos($rec['description'], 'encryption') !== false || strpos($rec['description'], 'key') !== false;
        });
        $this->assertGreaterThan(0, count($encryptionRecs));
    }

    /** @test */
    public function it_can_alert_on_critical_events()
    {
        $alertCallback = $this->createMock(\Closure::class);
        $alertCallback->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function ($event) {
                return $event['severity'] === 'critical' && $event['type'] === 'device_compromised';
            }));

        $this->service->onCriticalEvent($alertCallback);

        // Trigger a critical event
        $this->service->logSecurityEvent(
            'device_compromised',
            'critical',
            $this->device->id,
            $this->user->id,
            ['reason' => 'Multiple failed authentication attempts']
        );
    }

    /** @test */
    public function it_can_export_security_data()
    {
        // Create some security events
        $this->service->logSecurityEvent('device_registration', 'low', $this->device->id, $this->user->id, []);
        $this->service->logSecurityEvent('message_sent', 'low', $this->device->id, $this->user->id, []);

        $exportData = $this->service->exportSecurityData($this->user->id, [
            'format' => 'json',
            'date_range' => [
                'start' => now()->subDays(7)->toDateString(),
                'end' => now()->toDateString(),
            ],
        ]);

        $this->assertArrayHasKey('user_id', $exportData);
        $this->assertArrayHasKey('export_timestamp', $exportData);
        $this->assertArrayHasKey('events', $exportData);
        $this->assertArrayHasKey('summary', $exportData);

        $this->assertEquals($this->user->id, $exportData['user_id']);
        $this->assertCount(2, $exportData['events']);
    }

    /** @test */
    public function it_can_handle_bulk_event_processing()
    {
        $events = [];
        for ($i = 0; $i < 100; $i++) {
            $events[] = [
                'type' => 'message_sent',
                'severity' => 'low',
                'device_id' => $this->device->id,
                'user_id' => $this->user->id,
                'details' => ['message_id' => "msg-{$i}"],
                'timestamp' => now()->subMinutes($i)->toISOString(),
            ];
        }

        $result = $this->service->processBulkEvents($events);

        $this->assertArrayHasKey('processed_count', $result);
        $this->assertArrayHasKey('failed_count', $result);
        $this->assertArrayHasKey('processing_time', $result);

        $this->assertEquals(100, $result['processed_count']);
        $this->assertEquals(0, $result['failed_count']);
        $this->assertGreaterThan(0, $result['processing_time']);
    }

    /** @test */
    public function it_maintains_event_retention_policy()
    {
        // Create old events (older than retention period)
        for ($i = 0; $i < 10; $i++) {
            $this->service->logSecurityEvent(
                'old_event',
                'low',
                $this->device->id,
                $this->user->id,
                ['created' => now()->subDays(95)->timestamp] // Older than 90-day retention
            );
        }

        // Create recent events
        for ($i = 0; $i < 5; $i++) {
            $this->service->logSecurityEvent(
                'recent_event',
                'low',
                $this->device->id,
                $this->user->id,
                ['created' => now()->subDays(30)->timestamp]
            );
        }

        // Apply retention policy
        $cleanupResult = $this->service->applyRetentionPolicy();

        $this->assertArrayHasKey('removed_events', $cleanupResult);
        $this->assertArrayHasKey('retained_events', $cleanupResult);

        // Should have removed old events but kept recent ones
        $this->assertEquals(10, $cleanupResult['removed_events']);
        $this->assertEquals(5, $cleanupResult['retained_events']);
    }
}
