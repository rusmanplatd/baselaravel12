<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiveKit Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for LiveKit real-time communication server.
    | These values should be set in your .env file for security.
    |
    */

    'api_key' => env('LIVEKIT_API_KEY'),
    'api_secret' => env('LIVEKIT_API_SECRET'),
    'server_url' => env('LIVEKIT_SERVER_URL', 'ws://localhost:7880'),

    /*
    |--------------------------------------------------------------------------
    | Default Room Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration for new LiveKit rooms
    |
    */

    'defaults' => [
        'max_participants' => env('LIVEKIT_DEFAULT_MAX_PARTICIPANTS', 10),
        'empty_timeout' => env('LIVEKIT_DEFAULT_EMPTY_TIMEOUT', 600), // 10 minutes
        'participant_timeout' => env('LIVEKIT_DEFAULT_PARTICIPANT_TIMEOUT', 300), // 5 minutes
        'enable_e2ee' => env('LIVEKIT_ENABLE_E2EE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for LiveKit webhooks
    |
    */

    'webhooks' => [
        'enabled' => env('LIVEKIT_WEBHOOKS_ENABLED', true),
        'secret' => env('LIVEKIT_WEBHOOK_SECRET'),
        'events' => [
            'room_started',
            'room_finished',
            'participant_joined',
            'participant_left',
            'track_published',
            'track_unpublished',
            'recording_started',
            'recording_finished',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for LiveKit recording features
    |
    */

    'recording' => [
        'enabled' => env('LIVEKIT_RECORDING_ENABLED', false),
        'storage' => [
            'type' => env('LIVEKIT_RECORDING_STORAGE', 's3'), // s3, gcp, azure
            'bucket' => env('LIVEKIT_RECORDING_BUCKET'),
            'region' => env('LIVEKIT_RECORDING_REGION'),
            'access_key' => env('LIVEKIT_RECORDING_ACCESS_KEY'),
            'secret_key' => env('LIVEKIT_RECORDING_SECRET_KEY'),
        ],
        'formats' => ['mp4'], // mp4, webm
        'quality' => env('LIVEKIT_RECORDING_QUALITY', 'high'), // low, medium, high
    ],

    /*
    |--------------------------------------------------------------------------
    | E2EE Configuration
    |--------------------------------------------------------------------------
    |
    | End-to-end encryption settings for video calls
    |
    */

    'e2ee' => [
        'enabled' => env('LIVEKIT_E2EE_ENABLED', true),
        'key_provider' => env('LIVEKIT_E2EE_KEY_PROVIDER', 'internal'), // internal, external
        'algorithms' => [
            'encryption' => 'AES-GCM',
            'key_derivation' => 'HKDF-SHA256',
        ],
        'key_rotation' => [
            'enabled' => true,
            'interval' => 3600, // 1 hour
        ],
        'ratcheting' => [
            'enabled' => true,
            'window_size' => 64,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Settings
    |--------------------------------------------------------------------------
    |
    | Video and audio quality configuration
    |
    */

    'quality' => [
        'video' => [
            'max_bitrate' => env('LIVEKIT_MAX_VIDEO_BITRATE', 2000000), // 2 Mbps
            'max_framerate' => env('LIVEKIT_MAX_FRAMERATE', 30),
            'max_resolution' => env('LIVEKIT_MAX_RESOLUTION', '1280x720'),
            'simulcast' => env('LIVEKIT_SIMULCAST_ENABLED', true),
        ],
        'audio' => [
            'max_bitrate' => env('LIVEKIT_MAX_AUDIO_BITRATE', 64000), // 64 kbps
            'sample_rate' => env('LIVEKIT_AUDIO_SAMPLE_RATE', 48000),
            'channels' => env('LIVEKIT_AUDIO_CHANNELS', 1), // mono
            'noise_suppression' => env('LIVEKIT_NOISE_SUPPRESSION', true),
            'echo_cancellation' => env('LIVEKIT_ECHO_CANCELLATION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Configuration
    |--------------------------------------------------------------------------
    |
    | Network and connectivity settings
    |
    */

    'network' => [
        'ice_servers' => [
            [
                'urls' => ['stun:stun.l.google.com:19302'],
            ],
            // Add TURN servers if needed
        ],
        'connection_timeout' => env('LIVEKIT_CONNECTION_TIMEOUT', 15000), // 15 seconds
        'reconnect_attempts' => env('LIVEKIT_RECONNECT_ATTEMPTS', 3),
        'reconnect_delay' => env('LIVEKIT_RECONNECT_DELAY', 1000), // 1 second
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for LiveKit operations
    |
    */

    'rate_limits' => [
        'room_creation' => [
            'max_attempts' => 5,
            'window_minutes' => 60,
        ],
        'participant_join' => [
            'max_attempts' => 20,
            'window_minutes' => 60,
        ],
        'api_calls' => [
            'max_attempts' => 100,
            'window_minutes' => 60,
        ],
    ],
];
