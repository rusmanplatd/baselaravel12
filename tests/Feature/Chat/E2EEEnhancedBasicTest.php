<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    
    $this->user1 = User::factory()->create(['name' => 'Alice']);
    $this->user2 = User::factory()->create(['name' => 'Bob']);
    
    $this->conversation = Conversation::factory()->create([
        'type' => 'direct',
        'created_by' => $this->user1->id,
    ]);
    
    $this->conversation->participants()->create(['user_id' => $this->user1->id]);
    $this->conversation->participants()->create(['user_id' => $this->user2->id]);
});

describe('E2EE Enhanced Basic Tests', function () {
    it('performs end-to-end encryption with two users', function () {
        // Generate key pairs for both users
        $keyPair1 = $this->encryptionService->generateKeyPair();
        $keyPair2 = $this->encryptionService->generateKeyPair();
        
        // Create devices for both users
        $device1 = UserDevice::factory()->create([
            'user_id' => $this->user1->id,
            'device_name' => 'Alice Phone',
            'device_type' => 'mobile',
            'public_key' => $keyPair1['public_key'],
            'is_trusted' => true,
        ]);
        
        $device2 = UserDevice::factory()->create([
            'user_id' => $this->user2->id,
            'device_name' => 'Bob Phone',
            'device_type' => 'mobile',
            'public_key' => $keyPair2['public_key'],
            'is_trusted' => true,
        ]);
        
        // Generate shared symmetric key
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        
        // Create encryption keys for both users
        $encKey1 = EncryptionKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'device_id' => $device1->id,
            'device_fingerprint' => $device1->device_fingerprint,
            'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair1['public_key']),
            'public_key' => $keyPair1['public_key'],
            'key_version' => 1,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
            'is_active' => true,
        ]);
        
        $encKey2 = EncryptionKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user2->id,
            'device_id' => $device2->id,
            'device_fingerprint' => $device2->device_fingerprint,
            'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair2['public_key']),
            'public_key' => $keyPair2['public_key'],
            'key_version' => 1,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
            'is_active' => true,
        ]);
        
        // Test message exchange
        $testMessages = [
            ['sender' => $this->user1, 'content' => 'Hello Bob! This message is encrypted. 🔒'],
            ['sender' => $this->user2, 'content' => 'Hi Alice! I can read your encrypted message perfectly! 🛡️'],
            ['sender' => $this->user1, 'content' => 'Great! Let\'s discuss our secret project plans...'],
            ['sender' => $this->user2, 'content' => 'Perfect! The encryption is working flawlessly. 🚀']
        ];
        
        $messages = [];
        foreach ($testMessages as $msgData) {
            $message = Message::createEncrypted(
                $this->conversation->id,
                $msgData['sender']->id,
                $msgData['content'],
                $symmetricKey
            );
            
            $messages[] = [
                'message' => $message,
                'sender' => $msgData['sender'],
                'content' => $msgData['content']
            ];
            
            // Verify immediate decryption
            $decrypted = $message->decryptContent($symmetricKey);
            expect($decrypted)->toBe($msgData['content']);
        }
        
        // Verify both users can decrypt each other's messages
        foreach ($messages as $msgData) {
            $decryptedContent = $msgData['message']->decryptContent($symmetricKey);
            expect($decryptedContent)->toBe($msgData['content']);
        }
        
        // Verify key integrity for both users
        $decryptedKey1 = $encKey1->decryptSymmetricKey($keyPair1['private_key']);
        $decryptedKey2 = $encKey2->decryptSymmetricKey($keyPair2['private_key']);
        
        expect($decryptedKey1)->toBe($symmetricKey);
        expect($decryptedKey2)->toBe($symmetricKey);
        
        // Verify database state
        $totalMessages = Message::where('conversation_id', $this->conversation->id)->count();
        expect($totalMessages)->toBe(4);
        
        $activeKeys = EncryptionKey::where('conversation_id', $this->conversation->id)
            ->where('is_active', true)
            ->count();
        expect($activeKeys)->toBe(2);
        
        echo "\n✅ Basic E2EE test successful: 4 encrypted messages exchanged between 2 users";
    });
    
    it('handles message encryption with various content types', function () {
        // Setup basic encryption
        $keyPair = $this->encryptionService->generateKeyPair();
        $device = UserDevice::factory()->create([
            'user_id' => $this->user1->id,
            'public_key' => $keyPair['public_key'],
            'is_trusted' => true,
        ]);
        
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        EncryptionKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'device_id' => $device->id,
            'device_fingerprint' => $device->device_fingerprint,
            'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair['public_key']),
            'public_key' => $keyPair['public_key'],
            'key_version' => 1,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
            'is_active' => true,
        ]);
        
        // Test various message content types
        $testCases = [
            'Simple text' => 'Hello world!',
            'Unicode emoji' => 'Testing emoji support 🔒🛡️🚀💬🌟',
            'Special characters' => 'Special chars: !@#$%^&*()_+-=[]{}|;:,.<>?',
            'Numbers and symbols' => '123456789 + math symbols: ∑∆∞≈≤≥≠',
            'Long text' => str_repeat('This is a longer message to test encryption with larger content. ', 10),
            'JSON-like content' => '{"type":"encrypted","secure":true,"level":"high"}',
            'HTML-like content' => '<div class="encrypted">Secure HTML content</div>',
            'Multiline text' => "Line 1: Secure communication\nLine 2: End-to-end encryption\nLine 3: Privacy first!",
            'Binary representation' => base64_encode(random_bytes(32)),
        ];
        
        $encryptedMessages = [];
        foreach ($testCases as $type => $content) {
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $content,
                $symmetricKey
            );
            
            $encryptedMessages[] = [
                'type' => $type,
                'message' => $message,
                'original_content' => $content
            ];
        }
        
        // Verify all message types decrypt correctly
        foreach ($encryptedMessages as $msgData) {
            $decrypted = $msgData['message']->decryptContent($symmetricKey);
            expect($decrypted)->toBe($msgData['original_content']);
        }
        
        expect(count($encryptedMessages))->toBe(count($testCases));
        
        echo "\n✅ Content type test successful: " . count($testCases) . " different message types encrypted/decrypted";
    });
    
    it('tests encryption performance and reliability', function () {
        // Setup
        $keyPair = $this->encryptionService->generateKeyPair();
        $device = UserDevice::factory()->create([
            'user_id' => $this->user1->id,
            'public_key' => $keyPair['public_key'],
            'is_trusted' => true,
        ]);
        
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        EncryptionKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'device_id' => $device->id,
            'device_fingerprint' => $device->device_fingerprint,
            'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair['public_key']),
            'public_key' => $keyPair['public_key'],
            'key_version' => 1,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
            'is_active' => true,
        ]);
        
        // Performance test: encrypt and decrypt many messages
        $messageCount = 100;
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $messages = [];
        for ($i = 0; $i < $messageCount; $i++) {
            $content = "Performance test message #{$i} - " . 
                       str_repeat('content', rand(5, 20));
            
            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $content,
                $symmetricKey
            );
            
            $messages[] = [
                'message' => $message,
                'content' => $content
            ];
        }
        
        $encryptionTime = microtime(true) - $startTime;
        
        // Verify all messages
        $verificationStart = microtime(true);
        foreach ($messages as $msgData) {
            $decrypted = $msgData['message']->decryptContent($symmetricKey);
            expect($decrypted)->toBe($msgData['content']);
        }
        $verificationTime = microtime(true) - $verificationStart;
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        
        // Performance assertions
        expect($encryptionTime)->toBeLessThan(30.0); // Under 30 seconds
        expect($verificationTime)->toBeLessThan(10.0); // Under 10 seconds
        expect($memoryUsed)->toBeLessThan(50.0); // Under 50MB
        
        $avgEncryptionTime = ($encryptionTime / $messageCount) * 1000; // ms per message
        $avgVerificationTime = ($verificationTime / $messageCount) * 1000; // ms per message
        
        expect($avgEncryptionTime)->toBeLessThan(300); // Under 300ms per message
        expect($avgVerificationTime)->toBeLessThan(100); // Under 100ms per message
        
        // Verify integrity
        expect(count($messages))->toBe($messageCount);
        
        $dbMessageCount = Message::where('conversation_id', $this->conversation->id)->count();
        expect($dbMessageCount)->toBe($messageCount);
        
        echo "\n✅ Performance test successful:";
        echo "\n   • {$messageCount} messages encrypted in " . number_format($encryptionTime * 1000, 2) . "ms";
        echo "\n   • Average encryption time: " . number_format($avgEncryptionTime, 2) . "ms/message";
        echo "\n   • Verification time: " . number_format($verificationTime * 1000, 2) . "ms";
        echo "\n   • Average verification time: " . number_format($avgVerificationTime, 2) . "ms/message";
        echo "\n   • Memory usage: " . number_format($memoryUsed, 2) . "MB";
    });
    
    it('validates encryption security properties', function () {
        $keyPair = $this->encryptionService->generateKeyPair();
        $device = UserDevice::factory()->create([
            'user_id' => $this->user1->id,
            'public_key' => $keyPair['public_key'],
            'is_trusted' => true,
        ]);
        
        $symmetricKey = $this->encryptionService->generateSymmetricKey();
        EncryptionKey::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user1->id,
            'device_id' => $device->id,
            'device_fingerprint' => $device->device_fingerprint,
            'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair['public_key']),
            'public_key' => $keyPair['public_key'],
            'key_version' => 1,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
            'is_active' => true,
        ]);
        
        // Test 1: Same message produces different encrypted output (due to random IV)
        $message = 'This message should produce different encrypted output each time';
        
        $encrypted1 = $this->encryptionService->encryptMessage($message, $symmetricKey);
        $encrypted2 = $this->encryptionService->encryptMessage($message, $symmetricKey);
        
        expect($encrypted1['data'])->not()->toBe($encrypted2['data']);
        expect($encrypted1['iv'])->not()->toBe($encrypted2['iv']);
        
        // But both should decrypt to the same message
        $decrypted1 = $this->encryptionService->decryptMessage($encrypted1['data'], $encrypted1['iv'], $symmetricKey);
        $decrypted2 = $this->encryptionService->decryptMessage($encrypted2['data'], $encrypted2['iv'], $symmetricKey);
        
        expect($decrypted1)->toBe($message);
        expect($decrypted2)->toBe($message);
        
        // Test 2: Wrong key should fail
        $wrongKey = $this->encryptionService->generateSymmetricKey();
        
        expect(fn() => $this->encryptionService->decryptMessage($encrypted1['data'], $encrypted1['iv'], $wrongKey))
            ->toThrow(\App\Exceptions\DecryptionException::class);
        
        // Test 3: Tampered ciphertext should fail
        $tamperedData = base64_encode('tampered_encrypted_data');
        
        expect(fn() => $this->encryptionService->decryptMessage($tamperedData, $encrypted1['iv'], $symmetricKey))
            ->toThrow(\App\Exceptions\DecryptionException::class);
        
        // Test 4: IV uniqueness across many encryptions
        $ivs = [];
        for ($i = 0; $i < 50; $i++) {
            $encrypted = $this->encryptionService->encryptMessage("Test message {$i}", $symmetricKey);
            expect($ivs)->not()->toContain($encrypted['iv']);
            $ivs[] = $encrypted['iv'];
        }
        
        expect(count($ivs))->toBe(50);
        expect(count(array_unique($ivs)))->toBe(50);
        
        // Test 5: Key strength validation
        expect(strlen($symmetricKey))->toBe(32); // 256 bits
        expect(strlen($keyPair['public_key']))->toBeGreaterThan(200); // Reasonable RSA key size
        expect(strlen($keyPair['private_key']))->toBeGreaterThan(800); // Reasonable RSA private key size
        
        echo "\n✅ Security validation test successful: All security properties verified";
    });
});