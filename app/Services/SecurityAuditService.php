<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SecurityAuditService
{
    /**
     * Security event types with risk scores
     */
    private const EVENT_RISK_SCORES = [
        // Authentication events
        'auth.login.success' => 1,
        'auth.login.failed' => 3,
        'auth.login.brute_force' => 9,
        'auth.logout' => 1,
        'auth.password.changed' => 4,
        'auth.mfa.enabled' => 2,
        'auth.mfa.disabled' => 6,
        'auth.mfa.failed' => 5,

        // Device events
        'device.registered' => 3,
        'device.trusted' => 4,
        'device.untrusted' => 5,
        'device.revoked' => 6,
        'device.suspicious.access' => 8,
        'device.unknown.location' => 6,

        // E2EE events (metadata only, never content)
        'e2ee.key.generated' => 2,
        'e2ee.key.rotated' => 3,
        'e2ee.key.compromised' => 10,
        'e2ee.encryption.failed' => 7,
        'e2ee.decryption.failed' => 6,
        'e2ee.algorithm.downgrade' => 9,

        // Chat security events
        'chat.conversation.created' => 1,
        'chat.participant.added' => 2,
        'chat.participant.removed' => 3,
        'chat.message.blocked' => 5,
        'chat.file.blocked' => 4,
        'chat.suspicious.activity' => 7,

        // System events
        'system.backup.accessed' => 5,
        'system.admin.access' => 4,
        'system.config.changed' => 6,
        'system.vulnerability.detected' => 9,

        // API security events
        'api.rate_limit.exceeded' => 4,
        'api.unauthorized.access' => 6,
        'api.token.compromised' => 8,
        'api.suspicious.patterns' => 7,
    ];

    /**
     * Log security event without compromising E2EE
     */
    public function logEvent(
        string $eventType,
        ?User $user = null,
        ?UserDevice $device = null,
        ?string $conversationId = null,
        array $metadata = [],
        ?Request $request = null,
        ?string $organizationId = null
    ): SecurityAuditLog {
        // Calculate risk score
        $riskScore = $this->calculateRiskScore($eventType, $user, $device, $metadata);

        // Get location info without storing personal data
        $locationInfo = $this->getSecureLocationInfo($request);

        // Sanitize metadata to ensure no sensitive data is logged
        $sanitizedMetadata = $this->sanitizeMetadata($metadata);

        $auditLog = SecurityAuditLog::create([
            'event_type' => $eventType,
            'severity' => $this->getSeverityFromRiskScore($riskScore),
            'user_id' => $user?->id,
            'device_id' => $device?->id,
            'conversation_id' => $conversationId,
            'ip_address' => $this->hashIpAddress($request?->ip()),
            'user_agent' => $this->sanitizeUserAgent($request?->userAgent()),
            'location' => $locationInfo,
            'metadata' => $sanitizedMetadata,
            'risk_score' => $riskScore,
            'status' => $riskScore >= 8 ? 'pending' : 'normal',
            'organization_id' => $organizationId,
        ]);

        // Trigger immediate response for high-risk events
        if ($auditLog->isHighRisk()) {
            $this->handleHighRiskEvent($auditLog);
        }

        // Update user risk profile
        if ($user) {
            $this->updateUserRiskProfile($user, $riskScore);
        }

        Log::info('Security event logged', [
            'event_type' => $eventType,
            'risk_score' => $riskScore,
            'user_id' => $user?->id,
            'audit_log_id' => $auditLog->id,
        ]);

        return $auditLog;
    }

    /**
     * Detect anomalous patterns in user behavior
     */
    public function detectAnomalies(User $user): array
    {
        $anomalies = [];
        $timeWindow = Carbon::now()->subHours(24);

        // Check for unusual login patterns
        $recentLogins = SecurityAuditLog::where('user_id', $user->id)
            ->where('event_type', 'auth.login.success')
            ->where('created_at', '>=', $timeWindow)
            ->get();

        // Multiple locations in short time
        $uniqueLocations = $recentLogins->pluck('location.country')->unique();
        if ($uniqueLocations->count() > 2) {
            $anomalies[] = [
                'type' => 'multiple_locations',
                'severity' => 'high',
                'description' => 'Login from multiple countries in 24 hours',
                'locations' => $uniqueLocations->toArray(),
            ];
        }

        // Unusual time patterns
        $loginHours = $recentLogins->map(fn($log) => Carbon::parse($log->created_at)->hour);
        $usualHours = Cache::remember(
            "user_usual_hours_{$user->id}",
            3600,
            fn() => $this->getUserUsualLoginHours($user)
        );

        $unusualLogins = $loginHours->filter(fn($hour) => !in_array($hour, $usualHours));
        if ($unusualLogins->count() > 0) {
            $anomalies[] = [
                'type' => 'unusual_time',
                'severity' => 'medium',
                'description' => 'Login at unusual hours',
                'unusual_hours' => $unusualLogins->unique()->values()->toArray(),
            ];
        }

        // Failed authentication attempts
        $failedAttempts = SecurityAuditLog::where('user_id', $user->id)
            ->where('event_type', 'auth.login.failed')
            ->where('created_at', '>=', $timeWindow)
            ->count();

        if ($failedAttempts > 5) {
            $anomalies[] = [
                'type' => 'failed_attempts',
                'severity' => 'high',
                'description' => 'Multiple failed login attempts',
                'count' => $failedAttempts,
            ];
        }

        // Device changes
        $deviceChanges = SecurityAuditLog::where('user_id', $user->id)
            ->whereIn('event_type', ['device.registered', 'device.untrusted', 'device.revoked'])
            ->where('created_at', '>=', $timeWindow)
            ->count();

        if ($deviceChanges > 3) {
            $anomalies[] = [
                'type' => 'device_changes',
                'severity' => 'medium',
                'description' => 'Multiple device changes in short period',
                'count' => $deviceChanges,
            ];
        }

        return $anomalies;
    }

    /**
     * Generate security report for organization
     */
    public function generateSecurityReport(string $organizationId, Carbon $from, Carbon $to): array
    {
        $logs = SecurityAuditLog::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'summary' => [
                'total_events' => $logs->count(),
                'high_risk_events' => $logs->where('risk_score', '>=', 8)->count(),
                'medium_risk_events' => $logs->whereBetween('risk_score', [5, 7])->count(),
                'low_risk_events' => $logs->where('risk_score', '<', 5)->count(),
                'unresolved_events' => $logs->where('status', 'pending')->count(),
            ],
            'event_breakdown' => $logs->groupBy('event_type')->map->count(),
            'risk_trends' => $this->calculateRiskTrends($logs),
            'top_users_by_risk' => $this->getTopUsersByRisk($logs),
            'geographic_distribution' => $this->getGeographicDistribution($logs),
            'recommendations' => $this->generateSecurityRecommendations($logs),
        ];
    }

    /**
     * Calculate risk score for an event
     */
    private function calculateRiskScore(
        string $eventType,
        ?User $user,
        ?UserDevice $device,
        array $metadata
    ): int {
        $baseScore = self::EVENT_RISK_SCORES[$eventType] ?? 5;

        // Adjust based on user history
        if ($user) {
            $recentEvents = SecurityAuditLog::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->count();

            if ($recentEvents > 10) {
                $baseScore += 2; // Increase risk for active users
            }
        }

        // Adjust based on device trust
        if ($device && !$device->is_trusted) {
            $baseScore += 2;
        }

        // Adjust based on metadata indicators
        if (!empty($metadata['suspicious_indicators'])) {
            $baseScore += count($metadata['suspicious_indicators']);
        }

        return min($baseScore, 10); // Cap at 10
    }

    /**
     * Get severity level from risk score
     */
    private function getSeverityFromRiskScore(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 8 => 'critical',
            $riskScore >= 6 => 'high',
            $riskScore >= 4 => 'medium',
            $riskScore >= 2 => 'low',
            default => 'info',
        };
    }

    /**
     * Get secure location info without storing personal data
     */
    private function getSecureLocationInfo(?Request $request): ?array
    {
        if (!$request) {
            return null;
        }

        $ip = $request->ip();
        if (!$ip || $ip === '127.0.0.1') {
            return null;
        }

        // Use a basic IP-to-country service (implement as needed)
        // This should only store country/region, not exact location
        return [
            'country' => $this->getCountryFromIp($ip),
            'region' => $this->getRegionFromIp($ip),
            // Never store city or precise location
        ];
    }

    /**
     * Sanitize metadata to prevent logging of sensitive data
     */
    private function sanitizeMetadata(array $metadata): array
    {
        // Remove any potential PII or sensitive data
        $sensitiveKeys = [
            'password', 'token', 'key', 'secret', 'content',
            'message', 'email', 'phone', 'ssn', 'credit_card',
            'encrypted_content', 'decrypted_content'
        ];

        $sanitized = $metadata;

        array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });

        return $sanitized;
    }

    /**
     * Hash IP address for privacy
     */
    private function hashIpAddress(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        // Hash IP with salt for privacy while maintaining ability to detect patterns
        return Hash::make($ip . config('app.key'));
    }

    /**
     * Sanitize user agent
     */
    private function sanitizeUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        // Extract only basic browser/OS info, remove detailed version numbers
        return preg_replace('/\d+\.\d+(\.\d+)*/', 'X.X', $userAgent);
    }

    /**
     * Handle high-risk security events
     */
    private function handleHighRiskEvent(SecurityAuditLog $auditLog): void
    {
        // Trigger webhooks for high-risk events
        app(WebhookService::class)->trigger('security.high_risk_event', [
            'event' => [
                'id' => $auditLog->id,
                'type' => $auditLog->event_type,
                'risk_score' => $auditLog->risk_score,
                'severity' => $auditLog->severity,
                'timestamp' => $auditLog->created_at->toISOString(),
            ],
            'user' => $auditLog->user ? [
                'id' => $auditLog->user->id,
                'name' => $auditLog->user->name,
            ] : null,
            'metadata' => $auditLog->metadata,
        ], $auditLog->organization_id);

        // Auto-disable compromised accounts
        if ($auditLog->event_type === 'e2ee.key.compromised' && $auditLog->user) {
            // Revoke all user sessions and devices
            $auditLog->user->devices()->update(['is_active' => false]);

            Log::critical('Auto-disabled user account due to key compromise', [
                'user_id' => $auditLog->user->id,
                'audit_log_id' => $auditLog->id,
            ]);
        }
    }

    /**
     * Update user risk profile
     */
    private function updateUserRiskProfile(User $user, int $eventRiskScore): void
    {
        $cacheKey = "user_risk_profile_{$user->id}";
        $currentProfile = Cache::get($cacheKey, ['score' => 0, 'events' => 0]);

        $newProfile = [
            'score' => min(($currentProfile['score'] + $eventRiskScore) / 2, 10),
            'events' => $currentProfile['events'] + 1,
            'last_updated' => now(),
        ];

        Cache::put($cacheKey, $newProfile, 3600); // 1 hour
    }

    /**
     * Get user's usual login hours using advanced statistical analysis
     */
    private function getUserUsualLoginHours(User $user): array
    {
        // Use cached results for performance
        $cacheKey = "user_login_hours_{$user->id}";
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            return $this->calculateUserLoginPatterns($user);
        });
    }

    /**
     * Calculate user login patterns using statistical analysis
     */
    private function calculateUserLoginPatterns(User $user): array
    {
        // Get comprehensive login history
        $loginHistory = SecurityAuditLog::where('user_id', $user->id)
            ->where('event_type', 'auth.login.success')
            ->where('created_at', '>=', Carbon::now()->subDays(60)) // Extended period for better analysis
            ->orderBy('created_at')
            ->get();

        if ($loginHistory->isEmpty()) {
            return range(9, 17); // Default business hours
        }

        // Analyze patterns by day of week and hour
        $patterns = $this->analyzeLoginPatterns($loginHistory);
        
        // Calculate usual hours using multiple criteria
        $usualHours = $this->determineUsualHours($patterns);
        
        // Apply time zone and seasonal adjustments
        $adjustedHours = $this->applyTimeAdjustments($usualHours, $user);
        
        return $adjustedHours;
    }

    /**
     * Analyze login patterns by various dimensions
     */
    private function analyzeLoginPatterns($loginHistory): array
    {
        $patterns = [
            'hourly' => [],
            'daily' => [],
            'weekly' => [],
            'combined' => []
        ];

        foreach ($loginHistory as $login) {
            $loginTime = Carbon::parse($login->created_at);
            $hour = $loginTime->hour;
            $dayOfWeek = $loginTime->dayOfWeek; // 0 = Sunday, 6 = Saturday
            $weekKey = $loginTime->format('W-Y'); // Week number and year
            
            // Track hourly patterns
            $patterns['hourly'][$hour] = ($patterns['hourly'][$hour] ?? 0) + 1;
            
            // Track daily patterns (weekday vs weekend)
            $dayType = in_array($dayOfWeek, [0, 6]) ? 'weekend' : 'weekday';
            $patterns['daily'][$dayType][$hour] = ($patterns['daily'][$dayType][$hour] ?? 0) + 1;
            
            // Track weekly patterns for consistency
            $patterns['weekly'][$weekKey][$hour] = ($patterns['weekly'][$weekKey][$hour] ?? 0) + 1;
            
            // Combined pattern for statistical weight
            $patterns['combined']["{$dayType}_{$hour}"] = ($patterns['combined']["{$dayType}_{$hour}"] ?? 0) + 1;
        }

        return $patterns;
    }

    /**
     * Determine usual hours using statistical methods
     */
    private function determineUsualHours(array $patterns): array
    {
        $totalLogins = array_sum($patterns['hourly']);
        
        if ($totalLogins < 5) {
            return range(9, 17); // Default for insufficient data
        }

        // Calculate statistical thresholds
        $hourlyFrequencies = $patterns['hourly'];
        $frequencies = array_values($hourlyFrequencies);
        
        // Use median and standard deviation for more robust analysis
        $median = $this->calculateMedian($frequencies);
        $stdDev = $this->calculateStandardDeviation($frequencies);
        
        // Dynamic threshold based on login frequency distribution
        $baseThreshold = max(1, $totalLogins * 0.15); // Base 15% threshold
        $statsThreshold = max(1, $median + ($stdDev * 0.5)); // Statistical threshold
        $adaptiveThreshold = min($baseThreshold, $statsThreshold);
        
        // Identify usual hours
        $usualHours = [];
        foreach ($hourlyFrequencies as $hour => $count) {
            if ($count >= $adaptiveThreshold) {
                $usualHours[] = $hour;
            }
        }

        // Apply clustering to group consecutive hours
        $clusteredHours = $this->clusterConsecutiveHours($usualHours);
        
        // Expand clusters to include reasonable work/personal time windows
        $expandedHours = $this->expandTimeWindows($clusteredHours, $patterns);
        
        return $expandedHours;
    }

    /**
     * Calculate median of an array
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        
        if ($count === 0) return 0;
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }
        
        return $values[floor($count / 2)];
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation(array $values): float
    {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(fn($val) => pow($val - $mean, 2), $values);
        
        return sqrt(array_sum($squaredDifferences) / count($values));
    }

    /**
     * Cluster consecutive hours together
     */
    private function clusterConsecutiveHours(array $hours): array
    {
        if (empty($hours)) return [];
        
        sort($hours);
        $clusters = [];
        $currentCluster = [$hours[0]];
        
        for ($i = 1; $i < count($hours); $i++) {
            if ($hours[$i] === $hours[$i - 1] + 1) {
                // Consecutive hour
                $currentCluster[] = $hours[$i];
            } else {
                // Gap found, start new cluster
                $clusters[] = $currentCluster;
                $currentCluster = [$hours[$i]];
            }
        }
        
        $clusters[] = $currentCluster; // Add final cluster
        
        // Flatten clusters back to individual hours
        return array_merge(...$clusters);
    }

    /**
     * Expand time windows to include reasonable buffer hours
     */
    private function expandTimeWindows(array $usualHours, array $patterns): array
    {
        if (empty($usualHours)) return [];
        
        sort($usualHours);
        $expanded = $usualHours;
        
        // Add buffer hours before and after main activity periods
        $minHour = min($usualHours);
        $maxHour = max($usualHours);
        
        // Check if we should expand the window
        $weekdayPattern = $patterns['daily']['weekday'] ?? [];
        $totalWeekdayLogins = array_sum($weekdayPattern);
        
        // Expand window if there's significant activity in adjacent hours
        if ($minHour > 0 && ($weekdayPattern[$minHour - 1] ?? 0) > $totalWeekdayLogins * 0.05) {
            $expanded[] = $minHour - 1;
        }
        
        if ($maxHour < 23 && ($weekdayPattern[$maxHour + 1] ?? 0) > $totalWeekdayLogins * 0.05) {
            $expanded[] = $maxHour + 1;
        }
        
        sort($expanded);
        return array_unique($expanded);
    }

    /**
     * Apply timezone and seasonal adjustments
     */
    private function applyTimeAdjustments(array $hours, User $user): array
    {
        // Get user's timezone if available
        $userTimezone = $user->timezone ?? config('app.timezone', 'UTC');
        
        try {
            $userTime = now()->setTimezone($userTimezone);
            $utcOffset = $userTime->offsetHours;
            
            // No adjustment needed if already in correct timezone
            if ($userTimezone === config('app.timezone')) {
                return $hours;
            }
            
            // Apply timezone adjustment to usual hours
            $adjustedHours = array_map(function ($hour) use ($utcOffset) {
                $adjusted = $hour + $utcOffset;
                
                // Wrap around 24-hour format
                if ($adjusted < 0) $adjusted += 24;
                if ($adjusted >= 24) $adjusted -= 24;
                
                return $adjusted;
            }, $hours);
            
            sort($adjustedHours);
            return array_unique($adjustedHours);
            
        } catch (\Exception $e) {
            Log::warning('Failed to apply timezone adjustments for user login hours', [
                'user_id' => $user->id,
                'timezone' => $userTimezone,
                'error' => $e->getMessage()
            ]);
            
            return $hours; // Return original hours if adjustment fails
        }
    }

    /**
     * Simple IP to country mapping (implement with actual service)
     */
    private function getCountryFromIp(string $ip): ?string
    {
        // This should integrate with a proper IP geolocation service
        // For now, return null to avoid storing location data
        return null;
    }

    /**
     * Simple IP to region mapping (implement with actual service)
     */
    private function getRegionFromIp(string $ip): ?string
    {
        // This should integrate with a proper IP geolocation service
        // For now, return null to avoid storing location data
        return null;
    }

    /**
     * Calculate risk trends over time
     */
    private function calculateRiskTrends($logs): array
    {
        return $logs->groupBy(function ($log) {
            return Carbon::parse($log->created_at)->format('Y-m-d');
        })->map(function ($dayLogs) {
            return [
                'average_risk' => $dayLogs->avg('risk_score'),
                'total_events' => $dayLogs->count(),
                'high_risk_events' => $dayLogs->where('risk_score', '>=', 8)->count(),
            ];
        })->toArray();
    }

    /**
     * Get top users by risk score
     */
    private function getTopUsersByRisk($logs): array
    {
        return $logs->where('user_id', '!=', null)
            ->groupBy('user_id')
            ->map(function ($userLogs) {
                return [
                    'user_id' => $userLogs->first()->user_id,
                    'user_name' => $userLogs->first()->user->name ?? 'Unknown',
                    'total_risk_score' => $userLogs->sum('risk_score'),
                    'average_risk_score' => $userLogs->avg('risk_score'),
                    'event_count' => $userLogs->count(),
                ];
            })
            ->sortByDesc('total_risk_score')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get geographic distribution of events
     */
    private function getGeographicDistribution($logs): array
    {
        return $logs->whereNotNull('location')
            ->groupBy('location.country')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    /**
     * Generate security recommendations
     */
    private function generateSecurityRecommendations($logs): array
    {
        $recommendations = [];

        $highRiskCount = $logs->where('risk_score', '>=', 8)->count();
        if ($highRiskCount > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'incident_response',
                'title' => 'High number of critical security events',
                'description' => "There have been {$highRiskCount} critical security events. Consider reviewing security policies.",
            ];
        }

        $failedLogins = $logs->where('event_type', 'auth.login.failed')->count();
        $totalLogins = $logs->where('event_type', 'auth.login.success')->count();

        if ($totalLogins > 0 && ($failedLogins / $totalLogins) > 0.1) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'authentication',
                'title' => 'High failed login rate',
                'description' => 'Consider implementing additional authentication measures like MFA.',
            ];
        }

        return $recommendations;
    }
}
