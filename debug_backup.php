<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Services\ChatEncryptionService;
use Illuminate\Foundation\Application;

$app = new Application(__DIR__);
$app->singleton('app', function() use ($app) { return $app; });

// Bootstrap Laravel
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Set up test environment
$_ENV['DB_CONNECTION'] = 'pgsql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '5430';
$_ENV['DB_DATABASE'] = 'baselaravel12react_test';
$_ENV['DB_USERNAME'] = 'postgres';
$_ENV['DB_PASSWORD'] = 'Your_Strong_P455word';

// Create test user and data
$encryptionService = new ChatEncryptionService();
$user = User::factory()->create();

// Generate key pairs for user
$keyPair = $encryptionService->generateKeyPair();
$user->update(['public_key' => $keyPair['public_key']]);

$conversation = Conversation::factory()->direct()->create();
$conversation->participants()->create(['user_id' => $user->id, 'role' => 'admin']);

// Create encryption key using the new method
$encKey = EncryptionKey::createForUser(
    $conversation->id,
    $user->id,
    'test-symmetric-key',
    $user->public_key
);

echo "Created encryption key: " . $encKey->id . "\n";

// Create backup
$keyData = [
    'user_id' => $user->id,
    'public_key' => $user->public_key,
    'conversations' => $user->encryptionKeys()->with('conversation')->get()->map(function ($key) {
        return [
            'conversation_id' => $key->conversation_id,
            'conversation_name' => $key->conversation->name ?? 'Direct Chat',
            'encrypted_key' => $key->encrypted_key,
            'created_at' => $key->created_at,
        ];
    })->toArray(),
];

echo "Key data structure:\n";
print_r($keyData);

$backup = $encryptionService->createBackupEncryptionKey('SecureBackupPassword123!', $keyData);

echo "\nBackup created, length: " . strlen($backup) . "\n";

// Test restoration
try {
    $restored = $encryptionService->restoreFromBackup($backup, 'SecureBackupPassword123!');
    echo "Restored data:\n";
    print_r($restored);
} catch (Exception $e) {
    echo "Restoration failed: " . $e->getMessage() . "\n";
}