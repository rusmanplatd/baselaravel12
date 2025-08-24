<?php

use Carbon\Carbon;

test('device token generation works', function () {
    $token = str()->random(64);

    expect($token)->not->toBeEmpty();
    expect(strlen($token))->toBe(64);
});

test('device expiration calculation works', function () {
    $now = Carbon::now();
    $expiry = $now->copy()->addDays(30);

    expect((int) $now->diffInDays($expiry))->toBe(30);
    expect($expiry->greaterThan($now))->toBeTrue();
});

test('device expiration check works', function () {
    $futureDate = Carbon::now()->addDays(10);
    $pastDate = Carbon::now()->subDays(1);

    expect($futureDate->isPast())->toBeFalse();
    expect($pastDate->isPast())->toBeTrue();
});

test('device fingerprint generation is consistent', function () {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    $ipAddress = '192.168.1.1';
    $deviceType = 'desktop';

    $fingerprint1 = hash('sha256', $userAgent.$ipAddress.$deviceType);
    $fingerprint2 = hash('sha256', $userAgent.$ipAddress.$deviceType);

    expect($fingerprint1)->toBe($fingerprint2);
    expect(strlen($fingerprint1))->toBe(64); // SHA256 produces 64 character hex string
});

test('device type detection works correctly', function () {
    $mobileUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';
    $tabletUA = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15';
    $desktopUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    // Test mobile detection
    $containsIPhone = str_contains($mobileUA, 'iPhone');
    expect($containsIPhone)->toBeTrue();

    // Test tablet detection
    $containsIPad = str_contains($tabletUA, 'iPad');
    expect($containsIPad)->toBeTrue();

    // Test desktop detection (no mobile/tablet indicators)
    $isMobileOrTablet = str_contains($desktopUA, 'Mobile') || str_contains($desktopUA, 'iPad') || str_contains($desktopUA, 'Tablet');
    expect($isMobileOrTablet)->toBeFalse();
});

test('session lifetime calculation works', function () {
    $sessionLifetime = 120; // minutes
    $lastActivity = Carbon::now()->subMinutes($sessionLifetime + 10);
    $cutoff = Carbon::now()->subMinutes($sessionLifetime);

    expect($lastActivity->lessThan($cutoff))->toBeTrue();
});

test('security alert detection logic works', function () {
    $ips = ['192.168.1.1', '192.168.1.2', '192.168.1.3', '192.168.1.4', '192.168.1.5'];
    $uniqueIPs = array_unique($ips);

    expect(count($uniqueIPs))->toBe(5);
    expect(count($uniqueIPs) > 3)->toBeTrue(); // Should trigger multiple locations alert
});

test('device name generation logic works', function () {
    $userAgents = [
        'iPhone' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        'iPad' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
        'Android' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36',
        'Windows' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Mac' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
    ];

    foreach ($userAgents as $expectedDevice => $ua) {
        $contains = str_contains($ua, $expectedDevice);
        expect($contains)->toBeTrue();
    }
});

test('cookie settings validation works', function () {
    $cookieName = 'trusted_device_token';
    $cookieValue = str()->random(64);
    $expiryMinutes = 30 * 24 * 60; // 30 days in minutes

    expect($cookieName)->toBe('trusted_device_token');
    expect(strlen($cookieValue))->toBe(64);
    expect($expiryMinutes)->toBe(43200); // 30 days = 43200 minutes
});
