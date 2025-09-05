<?php

namespace Database\Seeders;

use App\Models\Chat\RateLimitConfig;
use Illuminate\Database\Seeder;

class RateLimitConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // Message sending limits
            [
                'action_name' => 'messages',
                'scope' => 'per_user',
                'max_attempts' => 100,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 1800, // 30 minutes
                'escalation_rules' => [
                    ['violations' => 3, 'penalty_multiplier' => 2],
                    ['violations' => 5, 'penalty_multiplier' => 4],
                    ['violations' => 10, 'penalty_type' => 'temporary_ban', 'duration_hours' => 24],
                ],
                'description' => 'Standard message sending rate limit per user',
            ],
            [
                'action_name' => 'messages',
                'scope' => 'per_ip',
                'max_attempts' => 200,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'Message sending rate limit per IP address',
            ],

            // File upload limits
            [
                'action_name' => 'file_uploads',
                'scope' => 'per_user',
                'max_attempts' => 20,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 1800, // 30 minutes
                'escalation_rules' => [
                    ['violations' => 2, 'penalty_multiplier' => 2],
                    ['violations' => 3, 'penalty_type' => 'file_limit', 'duration_hours' => 6],
                ],
                'description' => 'File upload rate limit per user',
            ],
            [
                'action_name' => 'file_uploads',
                'scope' => 'per_ip',
                'max_attempts' => 50,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'File upload rate limit per IP address',
            ],

            // Conversation creation
            [
                'action_name' => 'create_conversation',
                'scope' => 'per_user',
                'max_attempts' => 10,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 1800, // 30 minutes
                'description' => 'Conversation creation rate limit per user',
            ],

            // Device registration
            [
                'action_name' => 'device_registration',
                'scope' => 'per_user',
                'max_attempts' => 5,
                'window_seconds' => 86400, // 24 hours
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'Device registration rate limit per user',
            ],
            [
                'action_name' => 'device_registration',
                'scope' => 'per_ip',
                'max_attempts' => 20,
                'window_seconds' => 86400, // 24 hours
                'penalty_duration_seconds' => 7200, // 2 hours
                'description' => 'Device registration rate limit per IP',
            ],

            // Login attempts (authentication)
            [
                'action_name' => 'login_attempts',
                'scope' => 'per_ip',
                'max_attempts' => 5,
                'window_seconds' => 900, // 15 minutes
                'penalty_duration_seconds' => 1800, // 30 minutes
                'escalation_rules' => [
                    ['violations' => 3, 'penalty_multiplier' => 3],
                    ['violations' => 5, 'penalty_type' => 'temporary_ban', 'duration_hours' => 2],
                ],
                'description' => 'Login attempt rate limit per IP',
            ],

            // Abuse reports
            [
                'action_name' => 'abuse_reports',
                'scope' => 'per_user',
                'max_attempts' => 10,
                'window_seconds' => 86400, // 24 hours
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'Abuse report submission rate limit per user',
            ],

            // Poll/Survey creation
            [
                'action_name' => 'poll_creation',
                'scope' => 'per_user',
                'max_attempts' => 15,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 1800, // 30 minutes
                'description' => 'Poll creation rate limit per user',
            ],
            [
                'action_name' => 'poll_votes',
                'scope' => 'per_user',
                'max_attempts' => 50,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 600, // 10 minutes
                'description' => 'Poll voting rate limit per user',
            ],

            // API access limits
            [
                'action_name' => 'api_calls',
                'scope' => 'per_user',
                'max_attempts' => 1000,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'General API rate limit per user',
            ],
            [
                'action_name' => 'api_calls',
                'scope' => 'per_ip',
                'max_attempts' => 2000,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'General API rate limit per IP',
            ],

            // Chat encryption operations
            [
                'action_name' => 'key_generation',
                'scope' => 'per_user',
                'max_attempts' => 50,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 1800, // 30 minutes
                'description' => 'Encryption key generation rate limit',
            ],

            // Backup operations
            [
                'action_name' => 'backup_requests',
                'scope' => 'per_user',
                'max_attempts' => 3,
                'window_seconds' => 86400, // 24 hours
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'Chat backup request rate limit per user',
            ],

            // Video/Audio calls
            [
                'action_name' => 'video_calls',
                'scope' => 'per_user',
                'max_attempts' => 20,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 1800, // 30 minutes
                'description' => 'Video/Audio call initiation rate limit per user',
            ],
            [
                'action_name' => 'video_calls',
                'scope' => 'per_ip',
                'max_attempts' => 50,
                'window_seconds' => 3600, // 1 hour
                'penalty_duration_seconds' => 3600, // 1 hour
                'description' => 'Video/Audio call initiation rate limit per IP',
            ],
        ];

        foreach ($configs as $config) {
            RateLimitConfig::updateOrCreate(
                [
                    'action_name' => $config['action_name'],
                    'scope' => $config['scope'],
                ],
                $config
            );
        }

        $this->command->info('Rate limit configurations seeded successfully!');
    }
}
