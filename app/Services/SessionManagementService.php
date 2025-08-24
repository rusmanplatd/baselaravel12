<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Jenssegers\Agent\Agent;

class SessionManagementService
{
    private Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent;
    }

    public function createSession(Request $request, User $user, ?TrustedDevice $trustedDevice = null): void
    {
        $this->agent->setUserAgent($request->userAgent());

        $sessionData = [
            'trusted_device_id' => $trustedDevice?->id,
            'browser' => $this->agent->browser().' '.$this->agent->version($this->agent->browser()),
            'platform' => $this->agent->platform().' '.$this->agent->version($this->agent->platform()),
            'device_type' => $this->getDeviceType(),
            'location' => $this->getLocationFromIP($request->ip()),
            'login_at' => now(),
            'is_active' => true,
            'metadata' => json_encode([
                'mfa_verified' => Session::get('mfa_verified', false),
                'remember_token' => Session::get('remember_token'),
                'timezone' => $request->header('X-Timezone') ?? config('app.timezone'),
                'languages' => $request->header('Accept-Language'),
            ]),
        ];

        // Update the existing session record with additional data
        DB::table('sessions')
            ->where('id', Session::getId())
            ->update($sessionData);
    }

    public function updateSessionActivity(string $sessionId, ?TrustedDevice $trustedDevice = null): void
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('is_active', true)
            ->update([
                'last_activity' => now()->timestamp,
                'trusted_device_id' => $trustedDevice?->id,
                'payload' => $this->getSessionPayload(),
            ]);
    }

    public function terminateSession(string $sessionId, User $user): bool
    {
        $updated = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        if (! $updated) {
            return false;
        }

        // If terminating current session, invalidate the session
        if ($sessionId === Session::getId()) {
            Session::invalidate();
            Session::regenerateToken();
        }

        return true;
    }

    public function terminateAllSessions(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('is_active', true);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->update(['is_active' => false]);
    }

    public function getUserSessions(User $user): \Illuminate\Support\Collection
    {
        return DB::table('sessions')
            ->leftJoin('trusted_devices', 'sessions.trusted_device_id', '=', 'trusted_devices.id')
            ->where('sessions.user_id', $user->id)
            ->orderBy('sessions.last_activity', 'desc')
            ->select([
                'sessions.*',
                'trusted_devices.device_name',
                'trusted_devices.device_token',
                'trusted_devices.is_active as device_active',
            ])
            ->get()
            ->map(function ($session) {
                $session->metadata = $session->metadata ? json_decode($session->metadata, true) : null;
                $session->last_activity_formatted = \Carbon\Carbon::createFromTimestamp($session->last_activity);

                return $session;
            });
    }

    public function getActiveSessions(User $user): \Illuminate\Support\Collection
    {
        return DB::table('sessions')
            ->leftJoin('trusted_devices', 'sessions.trusted_device_id', '=', 'trusted_devices.id')
            ->where('sessions.user_id', $user->id)
            ->where('sessions.is_active', true)
            ->orderBy('sessions.last_activity', 'desc')
            ->select([
                'sessions.*',
                'trusted_devices.device_name',
                'trusted_devices.device_token',
                'trusted_devices.is_active as device_active',
            ])
            ->get()
            ->map(function ($session) {
                $session->metadata = $session->metadata ? json_decode($session->metadata, true) : null;
                $session->last_activity_formatted = \Carbon\Carbon::createFromTimestamp($session->last_activity);

                return $session;
            });
    }

    public function cleanupExpiredSessions(): int
    {
        $sessionLifetime = config('session.lifetime', 120); // minutes
        $cutoff = now()->subMinutes($sessionLifetime)->timestamp;

        return DB::table('sessions')
            ->where('last_activity', '<', $cutoff)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function getSessionStats(User $user): array
    {
        $activeSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        $totalSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->count();

        $trustedDeviceSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('trusted_device_id')
            ->count();

        $recentLogins = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('login_at', '>=', now()->subDays(7))
            ->count();

        $uniqueIPs = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->distinct()
            ->count('ip_address');

        $deviceTypes = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->select('device_type', DB::raw('count(*) as count'))
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();

        return [
            'active_sessions' => $activeSessions,
            'total_sessions' => $totalSessions,
            'trusted_device_sessions' => $trustedDeviceSessions,
            'recent_logins' => $recentLogins,
            'unique_ips' => $uniqueIPs,
            'device_types' => $deviceTypes,
            'current_session_id' => Session::getId(),
        ];
    }

    public function isCurrentSession(object $session): bool
    {
        return $session->id === Session::getId();
    }

    public function getSessionById(string $sessionId): ?object
    {
        return DB::table('sessions')
            ->leftJoin('trusted_devices', 'sessions.trusted_device_id', '=', 'trusted_devices.id')
            ->leftJoin('sys_users', 'sessions.user_id', '=', 'sys_users.id')
            ->where('sessions.id', $sessionId)
            ->select([
                'sessions.*',
                'trusted_devices.device_name',
                'trusted_devices.device_token',
                'trusted_devices.is_active as device_active',
                'sys_users.name as user_name',
                'sys_users.email as user_email',
            ])
            ->first();
    }

    public function validateSession(Request $request, User $user): bool
    {
        $sessionId = Session::getId();
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $session || $this->isSessionExpired($session)) {
            return false;
        }

        // Update session activity
        $this->updateSessionActivity($sessionId);

        return true;
    }

    public function getSecurityAlerts(User $user): array
    {
        $alerts = [];
        $alertConfig = config('trusted_devices.session_tracking.security_alerts');

        // Check for suspicious login locations
        $detectionWindowDays = $alertConfig['detection_window_days'] ?? 7;
        $locationThreshold = $alertConfig['multiple_locations_threshold'] ?? 3;

        $recentSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('login_at', '>=', now()->subDays($detectionWindowDays))
            ->get();

        $uniqueIPs = $recentSessions->pluck('ip_address')->unique();
        if ($uniqueIPs->count() > $locationThreshold) {
            $alerts[] = [
                'type' => 'multiple_locations',
                'message' => "Multiple login locations detected in the last {$detectionWindowDays} days",
                'data' => ['ip_count' => $uniqueIPs->count()],
            ];
        }

        // Check for concurrent sessions
        $sessionThreshold = $alertConfig['multiple_sessions_threshold'] ?? 5;
        $activeSessions = $this->getActiveSessions($user)->count();
        if ($activeSessions > $sessionThreshold) {
            $alerts[] = [
                'type' => 'multiple_sessions',
                'message' => 'High number of active sessions detected',
                'data' => ['session_count' => $activeSessions],
            ];
        }

        // Check for sessions without trusted devices
        $untrustedSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('trusted_device_id')
            ->count();

        if ($untrustedSessions > 0) {
            $alerts[] = [
                'type' => 'untrusted_devices',
                'message' => 'Active sessions from untrusted devices detected',
                'data' => ['untrusted_count' => $untrustedSessions],
            ];
        }

        return $alerts;
    }

    private function isSessionExpired(object $session): bool
    {
        $sessionLifetime = config('session.lifetime', 120); // minutes

        return $session->last_activity < now()->subMinutes($sessionLifetime)->timestamp;
    }

    private function getDeviceType(): string
    {
        if ($this->agent->isMobile()) {
            return 'mobile';
        }

        if ($this->agent->isTablet()) {
            return 'tablet';
        }

        return 'desktop';
    }

    private function getLocationFromIP(string $ip): ?string
    {
        // In a real application, you might want to use a GeoIP service
        // For now, return null or implement a basic lookup using the IP
        unset($ip); // Suppress unused parameter warning until GeoIP is implemented

        return null;
    }

    private function getSessionPayload(): string
    {
        // Get a serialized version of current session data (excluding sensitive info)
        $sessionData = Session::all();

        // Remove sensitive data
        unset($sessionData['password_confirmed_at']);
        unset($sessionData['_token']);

        return serialize($sessionData);
    }
}
