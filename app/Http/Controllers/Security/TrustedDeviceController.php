<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TrustedDeviceController extends Controller
{
    public function __construct(
        private TrustedDeviceService $trustedDeviceService
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $devices = $this->trustedDeviceService->getUserTrustedDevices($user);

        return Inertia::render('security/trusted-devices', [
            'devices' => $devices->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'browser' => $device->browser,
                    'platform' => $device->platform,
                    'ip_address' => $device->ip_address,
                    'location' => $device->location,
                    'last_used_at' => $device->last_used_at,
                    'expires_at' => $device->expires_at,
                    'is_active' => $device->is_active,
                    'is_current' => request()->cookie('trusted_device_token') === $device->device_token,
                ];
            }),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => 'nullable|string|max:255',
            'remember_duration' => 'nullable|integer|min:1|max:365',
        ]);

        $user = Auth::user();
        $deviceName = $request->input('device_name');

        $trustedDevice = $this->trustedDeviceService->markDeviceAsTrusted(
            $request,
            $user,
            $deviceName
        );

        // Extend device if duration specified
        if ($request->has('remember_duration')) {
            $trustedDevice->extend($request->input('remember_duration'));
        }

        return response()->json([
            'message' => 'Device marked as trusted successfully',
            'device' => [
                'id' => $trustedDevice->id,
                'device_name' => $trustedDevice->device_name,
                'device_type' => $trustedDevice->device_type,
                'expires_at' => $trustedDevice->expires_at,
            ],
        ]);
    }

    public function show(): Response
    {
        return Inertia::render('security/trust-device', [
            'currentDevice' => [
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ],
        ]);
    }

    public function update(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'device_name' => 'sometimes|string|max:255',
            'extend_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $user = Auth::user();
        $device = $user->trustedDevices()->find($deviceId);

        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if ($request->has('device_name')) {
            $device->update(['device_name' => $request->input('device_name')]);
        }

        if ($request->has('extend_days')) {
            $device->extend($request->input('extend_days'));
        }

        return response()->json([
            'message' => 'Device updated successfully',
            'device' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'expires_at' => $device->expires_at,
            ],
        ]);
    }

    public function destroy(string $deviceId): JsonResponse
    {
        $user = Auth::user();

        if ($this->trustedDeviceService->revokeTrustedDevice($deviceId, $user)) {
            return response()->json(['message' => 'Device revoked successfully']);
        }

        return response()->json(['message' => 'Device not found'], 404);
    }

    public function revokeAll(): JsonResponse
    {
        $user = Auth::user();
        $currentDeviceToken = request()->cookie('trusted_device_token');
        $currentDevice = null;

        if ($currentDeviceToken) {
            $currentDevice = $user->getTrustedDeviceByToken($currentDeviceToken);
        }

        $revokedCount = $this->trustedDeviceService->revokeAllTrustedDevices(
            $user,
            $currentDevice?->id
        );

        return response()->json([
            'message' => "Successfully revoked {$revokedCount} trusted devices",
            'revoked_count' => $revokedCount,
        ]);
    }

    public function cleanup(): JsonResponse
    {
        $user = Auth::user();
        $cleanedCount = $this->trustedDeviceService->cleanupExpiredDevices($user);

        return response()->json([
            'message' => "Cleaned up {$cleanedCount} expired devices",
            'cleaned_count' => $cleanedCount,
        ]);
    }
}
