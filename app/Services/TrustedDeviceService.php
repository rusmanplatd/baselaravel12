<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Jenssegers\Agent\Agent;

class TrustedDeviceService
{
    private Agent $agent;

    private function getCookieName(): string
    {
        return config('trusted_devices.cookie_name', 'trusted_device_token');
    }

    private function getDefaultExpiryDays(): int
    {
        return config('trusted_devices.default_trust_duration', 30);
    }

    public function __construct()
    {
        $this->agent = new Agent;
    }

    public function checkTrustedDevice(Request $request, User $user): ?TrustedDevice
    {
        $deviceToken = $request->cookie($this->getCookieName());

        if (! $deviceToken) {
            return null;
        }

        return $user->getTrustedDeviceByToken($deviceToken);
    }

    public function createTrustedDevice(Request $request, User $user, ?string $deviceName = null): TrustedDevice
    {
        $this->agent->setUserAgent($request->userAgent());

        $deviceData = [
            'user_id' => $user->id,
            'device_name' => $deviceName ?? $this->generateDeviceName($request),
            'device_type' => $this->getDeviceType(),
            'browser' => $this->agent->browser().' '.$this->agent->version($this->agent->browser()),
            'platform' => $this->agent->platform().' '.$this->agent->version($this->agent->platform()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'location' => $this->getLocationFromIP($request->ip()),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($this->getDefaultExpiryDays()),
            'metadata' => [
                'created_via' => 'web_auth',
                'languages' => $request->header('Accept-Language'),
                'timezone' => $request->header('X-Timezone') ?? config('app.timezone'),
            ],
        ];

        return TrustedDevice::create($deviceData);
    }

    public function markDeviceAsTrusted(Request $request, User $user, ?string $deviceName = null): TrustedDevice
    {
        // Clean up expired devices first
        $this->cleanupExpiredDevices($user);

        // Check if device already exists and is active
        $existingDevice = $this->findExistingDevice($request, $user);

        if ($existingDevice && $existingDevice->isActive()) {
            $existingDevice->updateLastUsed();
            $this->setCookie($existingDevice->device_token);

            return $existingDevice;
        }

        // Create new trusted device
        $trustedDevice = $this->createTrustedDevice($request, $user, $deviceName);
        $this->setCookie($trustedDevice->device_token);

        return $trustedDevice;
    }

    public function revokeTrustedDevice(string $deviceId, User $user): bool
    {
        $device = $user->trustedDevices()->find($deviceId);

        if (! $device) {
            return false;
        }

        $device->revoke();

        // If this is the current device, clear the cookie
        if (request()->cookie($this->getCookieName()) === $device->device_token) {
            Cookie::queue(Cookie::forget($this->getCookieName()));
        }

        return true;
    }

    public function revokeAllTrustedDevices(User $user, ?string $exceptDeviceId = null): int
    {
        $query = $user->trustedDevices()->active();

        if ($exceptDeviceId) {
            $query->where('id', '!=', $exceptDeviceId);
        }

        $devices = $query->get();

        foreach ($devices as $device) {
            $device->revoke();
        }

        // Clear cookie if current device was revoked
        if (! $exceptDeviceId || request()->cookie($this->getCookieName()) !== $user->trustedDevices()->find($exceptDeviceId)?->device_token) {
            Cookie::queue(Cookie::forget($this->getCookieName()));
        }

        return $devices->count();
    }

    public function extendTrustedDevice(string $deviceId, User $user, int $days = 30): bool
    {
        $device = $user->trustedDevices()->find($deviceId);

        if (! $device || ! $device->isActive()) {
            return false;
        }

        $device->extend($days);

        return true;
    }

    public function getUserTrustedDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->trustedDevices()
            ->orderBy('last_used_at', 'desc')
            ->get();
    }

    public function cleanupExpiredDevices(User $user): int
    {
        return $user->trustedDevices()
            ->expired()
            ->delete();
    }

    public function validateDeviceAccess(Request $request, User $user): bool
    {
        $trustedDevice = $this->checkTrustedDevice($request, $user);

        if (! $trustedDevice) {
            return false;
        }

        // Update last used timestamp
        $trustedDevice->updateLastUsed();

        // Validate IP if strict mode is enabled
        if (config('trusted_devices.strict_ip_validation', false)) {
            return $trustedDevice->ip_address === $request->ip();
        }

        return true;
    }

    private function setCookie(string $token): void
    {
        Cookie::queue(
            $this->getCookieName(),
            $token,
            $this->getDefaultExpiryDays() * 24 * 60, // minutes
            '/',
            null,
            true, // secure
            true, // httpOnly
            false, // raw
            'lax' // sameSite
        );
    }

    private function generateDeviceName(Request $request): string
    {
        $this->agent->setUserAgent($request->userAgent());

        $browser = $this->agent->browser();
        $platform = $this->agent->platform();
        $device = $this->agent->device();

        if ($this->agent->isMobile()) {
            return $device ? "$device ($platform)" : "$browser on $platform";
        }

        if ($this->agent->isTablet()) {
            return $device ? "$device Tablet" : "$browser on $platform";
        }

        return "$browser on $platform";
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
        // For now, return null or implement a basic lookup
        return null;
    }

    private function findExistingDevice(Request $request, User $user): ?TrustedDevice
    {
        $fingerprint = $this->getDeviceFingerprint($request);

        return $user->trustedDevices()
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->active()
            ->first();
    }

    private function getDeviceFingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent().$request->ip().$this->getDeviceType());
    }
}
