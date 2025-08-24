<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Trust Duration
    |--------------------------------------------------------------------------
    |
    | This value controls how long (in days) a device should be trusted by
    | default. Users can override this when marking a device as trusted.
    |
    */

    'default_trust_duration' => env('TRUSTED_DEVICES_DEFAULT_DURATION', 30),

    /*
    |--------------------------------------------------------------------------
    | Maximum Trust Duration
    |--------------------------------------------------------------------------
    |
    | The maximum number of days a device can be trusted. This prevents
    | users from setting excessively long trust periods.
    |
    */

    'max_trust_duration' => env('TRUSTED_DEVICES_MAX_DURATION', 365),

    /*
    |--------------------------------------------------------------------------
    | Cookie Name
    |--------------------------------------------------------------------------
    |
    | The name of the cookie used to store the trusted device token.
    |
    */

    'cookie_name' => env('TRUSTED_DEVICES_COOKIE_NAME', 'trusted_device_token'),

    /*
    |--------------------------------------------------------------------------
    | Strict IP Validation
    |--------------------------------------------------------------------------
    |
    | When enabled, trusted devices will only be valid from the same IP
    | address where they were originally trusted. This increases security
    | but may cause issues for users with dynamic IPs.
    |
    */

    'strict_ip_validation' => env('TRUSTED_DEVICES_STRICT_IP', false),

    /*
    |--------------------------------------------------------------------------
    | Auto Cleanup
    |--------------------------------------------------------------------------
    |
    | Whether to automatically clean up expired trusted devices and sessions
    | during the application cleanup process.
    |
    */

    'auto_cleanup' => env('TRUSTED_DEVICES_AUTO_CLEANUP', true),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Schedule
    |--------------------------------------------------------------------------
    |
    | How often to run the cleanup process. This uses Laravel's cron
    | expression syntax.
    |
    */

    'cleanup_schedule' => env('TRUSTED_DEVICES_CLEANUP_SCHEDULE', '0 2 * * *'), // Daily at 2 AM

    /*
    |--------------------------------------------------------------------------
    | Session Tracking
    |--------------------------------------------------------------------------
    |
    | Configuration for session tracking and management.
    |
    */

    'session_tracking' => [

        /*
        |--------------------------------------------------------------------------
        | Enable Session Tracking
        |--------------------------------------------------------------------------
        |
        | Whether to track user sessions in the database for security monitoring.
        |
        */

        'enabled' => env('SESSION_TRACKING_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Security Alerts
        |--------------------------------------------------------------------------
        |
        | Configuration for security alerts based on session patterns.
        |
        */

        'security_alerts' => [
            'multiple_locations_threshold' => 3, // Alert if more than 3 unique IPs in 7 days
            'multiple_sessions_threshold' => 5,  // Alert if more than 5 active sessions
            'detection_window_days' => 7,        // Window for detecting suspicious patterns
        ],
    ],

];
