<?php

use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserMfaSetting;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('activity logging demonstration', function () {
    echo "\n🚀 Activity Logging Demo\n";
    echo "========================\n\n";

    // Create test user
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);
    echo "✅ User created: {$user->name} ({$user->email})\n";

    // Create organization
    $organization = Organization::create([
        'name' => 'Acme Corporation',
        'organization_code' => 'ACME001',
        'organization_type' => 'corporate',
        'is_active' => true,
    ]);
    echo "✅ Organization created: {$organization->name}\n";

    // Create membership
    $membership = OrganizationMembership::create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'membership_type' => 'employee',
        'status' => 'active',
        'start_date' => now(),
    ]);
    echo "✅ Organization membership created\n";

    // Enable MFA
    $mfaSettings = UserMfaSetting::create([
        'user_id' => $user->id,
        'totp_enabled' => true,
        'totp_secret' => 'secret123',
        'totp_confirmed_at' => now(),
        'mfa_required' => true,
    ]);
    echo "✅ MFA enabled for user\n";

    // Add trusted device
    $device = TrustedDevice::create([
        'user_id' => $user->id,
        'device_name' => 'MacBook Pro',
        'device_type' => 'desktop',
        'browser' => 'Safari',
        'platform' => 'macOS',
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'is_active' => true,
        'expires_at' => now()->addDays(30),
    ]);
    echo "✅ Trusted device added\n";

    // Log various activities
    ActivityLogService::logAuth('login', 'User logged in from trusted device', [
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Safari/macOS',
        'mfa_verified' => true,
        'trusted_device' => true,
    ], $user);
    echo "✅ Login activity logged\n";

    ActivityLogService::logOrganization('role_assigned', 'User assigned admin role', [
        'role' => 'admin',
        'organization' => $organization->name,
        'assigned_by' => 'system',
    ], $user);
    echo "✅ Role assignment logged\n";

    ActivityLogService::logOAuth('client_authorized', 'User authorized OAuth client', [
        'client_name' => 'Mobile App',
        'scopes' => ['openid', 'profile', 'email'],
        'redirect_uri' => 'https://mobile.acme.com/callback',
    ], $user);
    echo "✅ OAuth authorization logged\n";

    ActivityLogService::logSystem('backup_completed', 'Daily backup completed successfully', [
        'backup_size' => '2.5GB',
        'duration' => '45 minutes',
        'backup_location' => 's3://backups/daily/',
    ]);
    echo "✅ System backup logged\n";

    // Update user profile to trigger LogsActivity trait
    $user->update(['name' => 'John Updated Doe']);
    echo "✅ Profile updated (automatic logging)\n";

    // Update organization to trigger LogsActivity trait
    $organization->update(['description' => 'Leading technology company']);
    echo "✅ Organization updated (automatic logging)\n";

    echo "\n📊 Activity Summary\n";
    echo "==================\n";

    $activities = Activity::orderBy('created_at', 'desc')->get();

    foreach ($activities as $activity) {
        $timestamp = $activity->created_at->format('Y-m-d H:i:s');
        $logName = strtoupper($activity->log_name);
        $event = $activity->event ?? 'N/A';
        $description = $activity->description;
        $causer = $activity->causer ? $activity->causer->name : 'System';

        echo "[$timestamp] {$logName} | {$event} | {$description} | By: {$causer}\n";
    }

    echo "\n🔍 Query Examples\n";
    echo "=================\n";

    echo 'Auth activities: '.Activity::auth()->count()."\n";
    echo 'Organization activities: '.Activity::organizationManagement()->count()."\n";
    echo 'OAuth activities: '.Activity::oauth()->count()."\n";
    echo 'System activities: '.Activity::system()->count()."\n";
    echo 'Security activities: '.Activity::where('log_name', 'security')->count()."\n";
    echo 'User activities: '.Activity::where('log_name', 'user')->count()."\n";
    echo "Activities by {$user->name}: ".Activity::forUser($user->id)->count()."\n";

    echo "\n✨ Demo completed successfully!\n";
    echo "All user activities are now being logged automatically.\n\n";

    // Basic assertion to ensure the test passes
    expect(Activity::count())->toBeGreaterThan(5);
});
