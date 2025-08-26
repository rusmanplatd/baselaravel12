<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic cleanup of expired trusted devices and sessions
Schedule::command('security:cleanup')
    ->cron(config('trusted_devices.cleanup_schedule', '0 2 * * *'))
    ->when(function () {
        return config('trusted_devices.auto_cleanup', true);
    })
    ->onSuccess(function () {
        Log::info('Security cleanup completed successfully');
    })
    ->onFailure(function () {
        Log::error('Security cleanup failed');
    });
