<?php

// Simple script to create a test user for Playwright tests

require_once __DIR__.'/../../vendor/autoload.php';

use App\Models\User;

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Create or find test user
    $user = User::where('email', 'test@example.com')->first();

    if (! $user) {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        echo "Created test user: test@example.com\n";
    } else {
        echo "Test user already exists: test@example.com\n";
    }

    echo "User ID: {$user->id}\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    exit(1);
}
