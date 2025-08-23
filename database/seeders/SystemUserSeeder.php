<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    public function run()
    {
        // Create system user
        User::firstOrCreate([
            'email' => 'system@system.local',
        ], [
            'name' => 'System User',
            'email' => 'system@system.local',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Create additional system/service users
        $systemUsers = [
            [
                'name' => 'OAuth Service',
                'email' => 'oauth@system.local',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Audit Service',
                'email' => 'audit@system.local',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Backup Service',
                'email' => 'backup@system.local',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Monitoring Service',
                'email' => 'monitor@system.local',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Notification Service',
                'email' => 'notifications@system.local',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($systemUsers as $userData) {
            User::firstOrCreate([
                'email' => $userData['email'],
            ], $userData);
        }
    }
}
