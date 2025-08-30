<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeviceTrustService
{
    private const TRUST_SCORE_CACHE_KEY = 'device_trust_score_';
    private const TRUST_SCORE_TTL = 300; // 5 minutes
    private const MAX_DEVICES_PER_USER = 10;
    private const DEVICE_ACTIVITY_THRESHOLD = 30; // days

    public function __construct()
    {
    }

    /**
     * Calculate comprehensive trust score for a device.
     */
    public function calculateTrustScore(UserDevice $device): array
    {
        $cacheKey = self::TRUST_SCORE_CACHE_KEY . $device->id;
        
        return Cache::remember($cacheKey, self::TRUST_SCORE_TTL, function () use ($device) {
            $factors = [
                'verification_status' => $this->getVerificationScore($device),
                'usage_pattern' => $this->getUsagePatternScore($device),
                'security_posture' => $this->getSecurityPostureScore($device),
                'device_age' => $this->getDeviceAgeScore($device),
                'location_consistency' => $this->getLocationConsistencyScore($device),
                'key_rotation_health' => $this->getKeyRotationScore($device),
            ];

            $weightedScore = 0;
            $weights = [
                'verification_status' => 0.25,
                'usage_pattern' => 0.20,
                'security_posture' => 0.20,
                'device_age' => 0.10,
                'location_consistency' => 0.15,
                'key_rotation_health' => 0.10,
            ];

            foreach ($factors as $factor => $score) {
                $weightedScore += $score * $weights[$factor];
            }

            $finalScore = min(10, max(0, round($weightedScore, 1)));

            return [
                'overall_score' => $finalScore,
                'factors' => $factors,
                'recommendations' => $this->generateTrustRecommendations($device, $factors),
                'calculated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get trust recommendation for a device.
     */
    public function getTrustRecommendation(UserDevice $device): string
    {
        $trustData = $this->calculateTrustScore($device);
        $score = $trustData['overall_score'];

        if ($score >= 8.0) {
            return 'highly_trusted';
        } elseif ($score >= 6.0) {
            return 'trusted';
        } elseif ($score >= 4.0) {
            return 'moderate';
        } elseif ($score >= 2.0) {
            return 'low_trust';
        } else {
            return 'untrusted';
        }
    }

    /**
     * Check if user has reached device limit.
     */
    public function hasReachedDeviceLimit(User $user): bool
    {
        $activeDeviceCount = $user->devices()
            ->where('is_trusted', true)
            ->where('verification_status', 'verified')
            ->count();

        return $activeDeviceCount >= self::MAX_DEVICES_PER_USER;
    }

    /**
     * Get device anomalies for security monitoring.
     */
    public function detectDeviceAnomalies(UserDevice $device): array
    {
        $anomalies = [];

        // Check for suspicious login patterns
        if ($this->hasSuspiciousLoginPattern($device)) {
            $anomalies[] = [
                'type' => 'suspicious_login_pattern',
                'severity' => 'medium',
                'description' => 'Unusual login times or frequency detected',
                'detected_at' => now()->toISOString(),
            ];
        }

        // Check for location jumps
        if ($this->hasLocationAnomalies($device)) {
            $anomalies[] = [
                'type' => 'location_anomaly',
                'severity' => 'high',
                'description' => 'Device location changed suspiciously quickly',
                'detected_at' => now()->toISOString(),
            ];
        }

        // Check for key rotation anomalies
        if ($this->hasKeyRotationAnomalies($device)) {
            $anomalies[] = [
                'type' => 'key_rotation_anomaly',
                'severity' => 'medium',
                'description' => 'Unusual key rotation pattern detected',
                'detected_at' => now()->toISOString(),
            ];
        }

        // Check for inactive device
        if ($this->isDeviceStale($device)) {
            $anomalies[] = [
                'type' => 'inactive_device',
                'severity' => 'low',
                'description' => 'Device has been inactive for extended period',
                'detected_at' => now()->toISOString(),
            ];
        }

        return $anomalies;
    }

    /**
     * Get device security recommendations.
     */
    public function getSecurityRecommendations(User $user): array
    {
        $recommendations = [];
        $devices = $user->devices()->where('is_trusted', true)->get();

        foreach ($devices as $device) {
            $trustData = $this->calculateTrustScore($device);
            $deviceRecommendations = $trustData['recommendations'];

            if (!empty($deviceRecommendations)) {
                $recommendations[$device->device_id] = [
                    'device_name' => $device->device_name,
                    'trust_score' => $trustData['overall_score'],
                    'recommendations' => $deviceRecommendations,
                ];
            }
        }

        // Global recommendations
        if ($this->hasReachedDeviceLimit($user)) {
            $recommendations['_global'][] = [
                'type' => 'device_limit',
                'priority' => 'medium',
                'message' => 'You have reached the maximum number of trusted devices. Consider removing unused devices.',
            ];
        }

        $staleDevices = $devices->filter(fn($d) => $this->isDeviceStale($d));
        if ($staleDevices->count() > 0) {
            $recommendations['_global'][] = [
                'type' => 'stale_devices',
                'priority' => 'low',
                'message' => "You have {$staleDevices->count()} inactive devices. Consider removing them for better security.",
            ];
        }

        return $recommendations;
    }

    /**
     * Revoke device access and cleanup.
     */
    public function revokeDeviceAccess(UserDevice $device): bool
    {
        try {
            // Revoke device
            $device->revoke();

            // Clear cache
            $cacheKey = self::TRUST_SCORE_CACHE_KEY . $device->id;
            Cache::forget($cacheKey);

            // Log security event
            Log::info('Device access revoked', [
                'device_id' => $device->device_id,
                'user_id' => $device->user_id,
                'device_name' => $device->device_name,
                'revoked_at' => now()->toISOString(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to revoke device access', [
                'device_id' => $device->device_id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get verification score based on device status.
     */
    private function getVerificationScore(UserDevice $device): float
    {
        switch ($device->verification_status) {
            case 'verified':
                return $device->verified_at && $device->verified_at->gt(now()->subDays(30)) ? 10.0 : 8.0;
            case 'pending':
                return 3.0;
            case 'expired':
                return 1.0;
            case 'rejected':
                return 0.0;
            default:
                return 0.0;
        }
    }

    /**
     * Get usage pattern score.
     */
    private function getUsagePatternScore(UserDevice $device): float
    {
        if (!$device->last_seen_at) {
            return 0.0;
        }

        $daysSinceLastSeen = $device->last_seen_at->diffInDays(now());
        
        if ($daysSinceLastSeen <= 1) {
            return 10.0;
        } elseif ($daysSinceLastSeen <= 7) {
            return 8.0;
        } elseif ($daysSinceLastSeen <= 30) {
            return 6.0;
        } elseif ($daysSinceLastSeen <= 90) {
            return 4.0;
        } else {
            return 2.0;
        }
    }

    /**
     * Get security posture score.
     */
    private function getSecurityPostureScore(UserDevice $device): float
    {
        $score = 0.0;

        // Base score from quantum security level
        $score += ($device->quantum_security_level ?? 0) * 0.8;

        // Additional points for security features
        if ($device->device_fingerprint && !empty($device->device_fingerprint)) {
            $score += 1.0;
        }

        if ($device->security_metadata && isset($device->security_metadata['secure_enclave'])) {
            $score += 1.0;
        }

        return min(10.0, $score);
    }

    /**
     * Get device age score.
     */
    private function getDeviceAgeScore(UserDevice $device): float
    {
        $daysSinceCreation = $device->created_at->diffInDays(now());

        if ($daysSinceCreation >= 30) {
            return 10.0; // Established device
        } elseif ($daysSinceCreation >= 7) {
            return 7.0;
        } elseif ($daysSinceCreation >= 1) {
            return 5.0;
        } else {
            return 2.0; // New device
        }
    }

    /**
     * Get location consistency score.
     */
    private function getLocationConsistencyScore(UserDevice $device): float
    {
        // For now, return a default score
        // In a real implementation, you would analyze IP geolocation patterns
        return 7.0;
    }

    /**
     * Get key rotation score.
     */
    private function getKeyRotationScore(UserDevice $device): float
    {
        if (!$device->last_key_rotation_at) {
            return 5.0; // Neutral for new devices
        }

        $daysSinceRotation = $device->last_key_rotation_at->diffInDays(now());

        if ($daysSinceRotation <= 30) {
            return 10.0;
        } elseif ($daysSinceRotation <= 90) {
            return 7.0;
        } elseif ($daysSinceRotation <= 180) {
            return 5.0;
        } else {
            return 2.0;
        }
    }

    /**
     * Generate trust recommendations.
     */
    private function generateTrustRecommendations(UserDevice $device, array $factors): array
    {
        $recommendations = [];

        if ($factors['verification_status'] < 8.0) {
            $recommendations[] = [
                'type' => 'verification',
                'priority' => 'high',
                'message' => 'Verify this device to improve trust score',
            ];
        }

        if ($factors['key_rotation_health'] < 5.0) {
            $recommendations[] = [
                'type' => 'key_rotation',
                'priority' => 'medium',
                'message' => 'Rotate encryption keys regularly for better security',
            ];
        }

        if ($factors['usage_pattern'] < 5.0) {
            $recommendations[] = [
                'type' => 'activity',
                'priority' => 'low',
                'message' => 'Consider removing this inactive device',
            ];
        }

        return $recommendations;
    }

    /**
     * Check for suspicious login patterns.
     */
    private function hasSuspiciousLoginPattern(UserDevice $device): bool
    {
        // Placeholder for actual implementation
        return false;
    }

    /**
     * Check for location anomalies.
     */
    private function hasLocationAnomalies(UserDevice $device): bool
    {
        // Placeholder for actual implementation
        return false;
    }

    /**
     * Check for key rotation anomalies.
     */
    private function hasKeyRotationAnomalies(UserDevice $device): bool
    {
        if (!$device->last_key_rotation_at) {
            return false;
        }

        // Check if rotation happened too frequently (potential attack)
        $rotationsSinceCreation = $device->created_at->diffInDays($device->last_key_rotation_at);
        return $rotationsSinceCreation < 1; // More than once per day is suspicious
    }

    /**
     * Check if device is stale.
     */
    private function isDeviceStale(UserDevice $device): bool
    {
        if (!$device->last_seen_at) {
            return true;
        }

        return $device->last_seen_at->lt(now()->subDays(self::DEVICE_ACTIVITY_THRESHOLD));
    }
}