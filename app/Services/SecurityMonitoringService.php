<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use App\Events\DeviceSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

class SecurityMonitoringService
{
    private DeviceTrustService $deviceTrustService;
    
    private const ALERT_CACHE_KEY = 'security_alerts_';
    private const ALERT_CACHE_TTL = 3600; // 1 hour
    private const RATE_LIMIT_CACHE_KEY = 'rate_limit_';
    private const RATE_LIMIT_TTL = 900; // 15 minutes

    public function __construct(DeviceTrustService $deviceTrustService)
    {
        $this->deviceTrustService = $deviceTrustService;
    }

    /**
     * Monitor device registration event.
     */
    public function monitorDeviceRegistration(User $user, UserDevice $device): void
    {
        $alerts = [];

        // Check if user has too many devices
        if ($this->deviceTrustService->hasReachedDeviceLimit($user)) {
            $alerts[] = $this->createAlert('device_limit_reached', 'medium', [
                'message' => 'User has reached maximum device limit',
                'device_count' => $user->devices()->count(),
            ]);
        }

        // Check registration rate limiting
        if ($this->isRegistrationRateLimited($user)) {
            $alerts[] = $this->createAlert('rapid_device_registration', 'high', [
                'message' => 'Multiple device registrations in short time period',
            ]);
        }

        // Check for suspicious device patterns
        if ($this->hasSuspiciousDevicePattern($user, $device)) {
            $alerts[] = $this->createAlert('suspicious_device_pattern', 'high', [
                'message' => 'Device registration pattern may indicate compromise',
                'device_type' => $device->device_type,
                'platform' => $device->platform,
            ]);
        }

        $this->processSecurityAlerts($user, $alerts);

        // Broadcast device registration event
        Event::dispatch(new DeviceSync($user, 'device_registered', [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type,
        ]));
    }

    /**
     * Monitor device verification event.
     */
    public function monitorDeviceVerification(User $user, UserDevice $device): void
    {
        $alerts = [];

        // Check verification timing
        $timeSinceRegistration = $device->created_at->diffInMinutes(now());
        if ($timeSinceRegistration < 1) {
            $alerts[] = $this->createAlert('rapid_verification', 'medium', [
                'message' => 'Device verified unusually quickly after registration',
                'time_to_verify_minutes' => $timeSinceRegistration,
            ]);
        }

        $this->processSecurityAlerts($user, $alerts);

        // Broadcast device verification event
        Event::dispatch(new DeviceSync($user, 'device_verified', [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'verified_at' => $device->verified_at->toISOString(),
        ]));
    }

    /**
     * Monitor key rotation event.
     */
    public function monitorKeyRotation(User $user, UserDevice $device): void
    {
        $alerts = [];

        // Check rotation frequency
        if ($this->isKeyRotationTooFrequent($device)) {
            $alerts[] = $this->createAlert('excessive_key_rotation', 'medium', [
                'message' => 'Device keys rotated too frequently',
                'device_id' => $device->device_id,
            ]);
        }

        $this->processSecurityAlerts($user, $alerts);

        // Broadcast key rotation event
        Event::dispatch(new DeviceSync($user, 'keys_rotated', [
            'device_id' => $device->device_id,
            'rotated_at' => now()->toISOString(),
        ]));
    }

    /**
     * Monitor device revocation event.
     */
    public function monitorDeviceRevocation(User $user, UserDevice $device): void
    {
        // Log security event
        Log::warning('Device revoked', [
            'user_id' => $user->id,
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'revoked_at' => now()->toISOString(),
        ]);

        // Broadcast device revocation event
        Event::dispatch(new DeviceSync($user, 'device_revoked', [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'revoked_at' => now()->toISOString(),
        ]));
    }

    /**
     * Monitor cross-device message encryption.
     */
    public function monitorCrossDeviceMessage(User $user, array $targetDevices, string $messageId): void
    {
        $alerts = [];

        // Check for unusual target device count
        if (count($targetDevices) > 8) {
            $alerts[] = $this->createAlert('large_device_broadcast', 'low', [
                'message' => 'Message encrypted for unusually large number of devices',
                'device_count' => count($targetDevices),
                'message_id' => $messageId,
            ]);
        }

        $this->processSecurityAlerts($user, $alerts);
    }

    /**
     * Get security alerts for user.
     */
    public function getSecurityAlerts(User $user): array
    {
        $cacheKey = self::ALERT_CACHE_KEY . $user->id;
        return Cache::get($cacheKey, []);
    }

    /**
     * Clear security alerts for user.
     */
    public function clearSecurityAlerts(User $user): void
    {
        $cacheKey = self::ALERT_CACHE_KEY . $user->id;
        Cache::forget($cacheKey);
    }

    /**
     * Generate security report for user.
     */
    public function generateSecurityReport(User $user): array
    {
        $devices = $user->devices()->where('is_trusted', true)->get();
        $alerts = $this->getSecurityAlerts($user);
        
        $deviceAnalysis = [];
        $totalAnomalies = 0;

        foreach ($devices as $device) {
            $trustData = $this->deviceTrustService->calculateTrustScore($device);
            $anomalies = $this->deviceTrustService->detectDeviceAnomalies($device);
            
            $deviceAnalysis[$device->device_id] = [
                'device_name' => $device->device_name,
                'trust_score' => $trustData['overall_score'],
                'trust_level' => $device->trust_level,
                'anomalies' => $anomalies,
                'last_seen' => $device->last_seen_at?->toISOString(),
                'is_online' => $device->isOnline(),
            ];

            $totalAnomalies += count($anomalies);
        }

        return [
            'user_id' => $user->id,
            'report_generated_at' => now()->toISOString(),
            'summary' => [
                'total_devices' => $devices->count(),
                'active_devices' => $devices->filter(fn($d) => $d->isOnline())->count(),
                'total_alerts' => count($alerts),
                'total_anomalies' => $totalAnomalies,
                'average_trust_score' => $devices->avg(fn($d) => $this->deviceTrustService->calculateTrustScore($d)['overall_score']),
            ],
            'alerts' => $alerts,
            'device_analysis' => $deviceAnalysis,
            'recommendations' => $this->deviceTrustService->getSecurityRecommendations($user),
        ];
    }

    /**
     * Create security alert.
     */
    private function createAlert(string $type, string $severity, array $data): array
    {
        return [
            'id' => uniqid('alert_'),
            'type' => $type,
            'severity' => $severity,
            'data' => $data,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Process security alerts.
     */
    private function processSecurityAlerts(User $user, array $alerts): void
    {
        if (empty($alerts)) {
            return;
        }

        $cacheKey = self::ALERT_CACHE_KEY . $user->id;
        $existingAlerts = Cache::get($cacheKey, []);
        
        $allAlerts = array_merge($existingAlerts, $alerts);
        
        // Keep only last 50 alerts
        $allAlerts = array_slice($allAlerts, -50);
        
        Cache::put($cacheKey, $allAlerts, self::ALERT_CACHE_TTL);

        // Log high severity alerts
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'high') {
                Log::warning('High severity security alert', [
                    'user_id' => $user->id,
                    'alert' => $alert,
                ]);
            }
        }
    }

    /**
     * Check if device registration is rate limited.
     */
    private function isRegistrationRateLimited(User $user): bool
    {
        $cacheKey = self::RATE_LIMIT_CACHE_KEY . 'registration_' . $user->id;
        $registrationCount = Cache::get($cacheKey, 0);

        Cache::put($cacheKey, $registrationCount + 1, self::RATE_LIMIT_TTL);

        // Allow max 3 registrations per 15 minutes
        return $registrationCount >= 3;
    }

    /**
     * Check for suspicious device patterns.
     */
    private function hasSuspiciousDevicePattern(User $user, UserDevice $newDevice): bool
    {
        $recentDevices = $user->devices()
            ->where('created_at', '>', now()->subHour())
            ->where('id', '!=', $newDevice->id)
            ->get();

        // Flag if multiple devices registered from same IP recently
        $sameIpDevices = $recentDevices->filter(function ($device) use ($newDevice) {
            return $device->registration_ip === $newDevice->registration_ip;
        });

        if ($sameIpDevices->count() > 2) {
            return true;
        }

        // Flag if device type/platform combinations are unusual
        $existingCombinations = $user->devices()
            ->where('is_trusted', true)
            ->get()
            ->map(fn($d) => $d->device_type . '|' . $d->platform)
            ->unique()
            ->count();

        // If user suddenly adds very different device types, flag it
        return $existingCombinations > 5;
    }

    /**
     * Check if key rotation is too frequent.
     */
    private function isKeyRotationTooFrequent(UserDevice $device): bool
    {
        if (!$device->last_key_rotation_at) {
            return false;
        }

        // Flag if keys rotated more than once per hour
        return $device->last_key_rotation_at->gt(now()->subHour());
    }
}