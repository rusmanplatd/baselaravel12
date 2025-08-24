<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Services\SessionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function __construct(
        private SessionManagementService $sessionService
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $sessions = $this->sessionService->getUserSessions($user);
        $stats = $this->sessionService->getSessionStats($user);
        $alerts = $this->sessionService->getSecurityAlerts($user);

        return Inertia::render('security/sessions', [
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'browser' => $session->browser,
                    'platform' => $session->platform,
                    'device_type' => $session->device_type,
                    'location' => $session->location,
                    'last_activity' => $session->last_activity_formatted,
                    'login_at' => $session->login_at,
                    'is_active' => $session->is_active,
                    'is_current' => $this->sessionService->isCurrentSession($session),
                    'trusted_device' => $session->device_name ? [
                        'id' => $session->trusted_device_id,
                        'device_name' => $session->device_name,
                        'device_type' => $session->device_type,
                    ] : null,
                ];
            }),
            'stats' => $stats,
            'alerts' => $alerts,
        ]);
    }

    public function destroy(string $sessionId): JsonResponse
    {
        $user = Auth::user();

        if ($this->sessionService->terminateSession($sessionId, $user)) {
            return response()->json(['message' => 'Session terminated successfully']);
        }

        return response()->json(['message' => 'Session not found'], 404);
    }

    public function terminateAll(): JsonResponse
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();

        $terminatedCount = $this->sessionService->terminateAllSessions($user, $currentSessionId);

        return response()->json([
            'message' => "Successfully terminated {$terminatedCount} sessions",
            'terminated_count' => $terminatedCount,
        ]);
    }

    public function terminateAllOthers(): JsonResponse
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();

        $terminatedCount = $this->sessionService->terminateAllSessions($user, $currentSessionId);

        return response()->json([
            'message' => "Successfully terminated {$terminatedCount} other sessions",
            'terminated_count' => $terminatedCount,
        ]);
    }

    public function show(string $sessionId): JsonResponse
    {
        $session = $this->sessionService->getSessionById($sessionId);

        if (! $session || $session->user_id !== Auth::id()) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        return response()->json([
            'session' => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'browser' => $session->browser,
                'platform' => $session->platform,
                'device_type' => $session->device_type,
                'location' => $session->location,
                'last_activity' => $session->last_activity,
                'login_at' => $session->login_at,
                'is_active' => $session->is_active,
                'is_current' => $this->sessionService->isCurrentSession($session),
                'metadata' => $session->metadata,
                'trusted_device' => $session->device_name ? [
                    'id' => $session->trusted_device_id,
                    'device_name' => $session->device_name,
                    'device_type' => $session->device_type,
                    'device_active' => $session->device_active,
                ] : null,
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->sessionService->getSessionStats($user);

        return response()->json($stats);
    }

    public function alerts(): JsonResponse
    {
        $user = Auth::user();
        $alerts = $this->sessionService->getSecurityAlerts($user);

        return response()->json(['alerts' => $alerts]);
    }

    public function cleanup(): JsonResponse
    {
        $cleanedCount = $this->sessionService->cleanupExpiredSessions();

        return response()->json([
            'message' => "Cleaned up {$cleanedCount} expired sessions",
            'cleaned_count' => $cleanedCount,
        ]);
    }
}
