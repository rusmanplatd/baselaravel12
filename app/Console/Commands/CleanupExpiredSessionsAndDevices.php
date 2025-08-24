<?php

namespace App\Console\Commands;

use App\Models\TrustedDevice;
use App\Services\SessionManagementService;
use Illuminate\Console\Command;

class CleanupExpiredSessionsAndDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:cleanup {--dry-run : Show what would be cleaned without actually cleaning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired trusted devices and sessions';

    /**
     * Execute the console command.
     */
    public function handle(SessionManagementService $sessionService)
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Starting security cleanup...');
        $this->newLine();

        // Clean up expired trusted devices
        $this->info('🔍 Checking for expired trusted devices...');

        $expiredDevicesCount = TrustedDevice::expired()->count();

        if ($expiredDevicesCount > 0) {
            if ($isDryRun) {
                $this->warn("Would delete {$expiredDevicesCount} expired trusted devices");
            } else {
                $deletedDevices = TrustedDevice::expired()->delete();
                $this->info("✅ Deleted {$deletedDevices} expired trusted devices");
            }
        } else {
            $this->info('✅ No expired trusted devices found');
        }

        // Clean up expired sessions
        $this->info('🔍 Checking for expired sessions...');

        if ($isDryRun) {
            // Count expired sessions without cleaning them
            $sessionLifetime = config('session.lifetime', 120); // minutes
            $cutoff = now()->subMinutes($sessionLifetime)->timestamp;
            $expiredSessionsCount = \Illuminate\Support\Facades\DB::table('sessions')
                ->where('last_activity', '<', $cutoff)
                ->where('is_active', true)
                ->count();

            if ($expiredSessionsCount > 0) {
                $this->warn("Would mark {$expiredSessionsCount} expired sessions as inactive");
            } else {
                $this->info('✅ No expired sessions found');
            }
        } else {
            $cleanedSessions = $sessionService->cleanupExpiredSessions();

            if ($cleanedSessions > 0) {
                $this->info("✅ Marked {$cleanedSessions} expired sessions as inactive");
            } else {
                $this->info('✅ No expired sessions found');
            }
        }

        // Summary
        $this->newLine();
        if ($isDryRun) {
            $this->info('🔍 Dry run completed. Use without --dry-run to perform actual cleanup.');
        } else {
            $this->info('✅ Security cleanup completed successfully!');
        }

        return Command::SUCCESS;
    }
}
