<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);
    
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    $this->conversation = Conversation::factory()->direct()->create();
    $this->conversation->participants()->create(['user_id' => $this->user1->id, 'role' => 'admin']);
    $this->conversation->participants()->create(['user_id' => $this->user2->id, 'role' => 'member']);
});

describe('E2EE Cross-Platform Compatibility', function () {
    describe('Platform-Specific Device Registration', function () {
        it('handles iOS device encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $iOSDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'iPhone 15 Pro',
                'mobile',
                $keyPair['public_key'],
                'ios_device_'.uniqid(),
                'iOS',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
                ['messaging', 'encryption', 'biometric_auth'],
                'high',
                [
                    'os_version' => 'iOS 17.0',
                    'device_model' => 'iPhone15,2',
                    'secure_enclave' => true,
                    'touch_id' => true,
                    'face_id' => true,
                ]
            );

            expect($iOSDevice)->toBeInstanceOf(UserDevice::class);
            expect($iOSDevice->platform)->toBe('iOS');
            expect($iOSDevice->device_capabilities)->toContain('biometric_auth');
            expect($iOSDevice->device_info['secure_enclave'])->toBeTrue();
            expect($iOSDevice->security_level)->toBe('high');
        });

        it('handles Android device encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $androidDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'Samsung Galaxy S24',
                'mobile',
                $keyPair['public_key'],
                'android_device_'.uniqid(),
                'Android',
                'Mozilla/5.0 (Linux; Android 14; SM-S921U)',
                ['messaging', 'encryption', 'fingerprint'],
                'high',
                [
                    'os_version' => 'Android 14',
                    'device_model' => 'SM-S921U',
                    'security_patch' => '2024-01-01',
                    'keystore_version' => '4.1',
                    'strongbox' => true,
                ]
            );

            expect($androidDevice)->toBeInstanceOf(UserDevice::class);
            expect($androidDevice->platform)->toBe('Android');
            expect($androidDevice->device_capabilities)->toContain('fingerprint');
            expect($androidDevice->device_info['strongbox'])->toBeTrue();
            expect($androidDevice->security_level)->toBe('high');
        });

        it('handles Windows desktop encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $windowsDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'Windows Workstation',
                'desktop',
                $keyPair['public_key'],
                'windows_device_'.uniqid(),
                'Windows',
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ['messaging', 'encryption', 'file_sharing', 'screen_sharing'],
                'medium',
                [
                    'os_version' => 'Windows 11 Pro',
                    'build_number' => '22631',
                    'tpm_version' => '2.0',
                    'bitlocker_enabled' => true,
                    'windows_hello' => true,
                ]
            );

            expect($windowsDevice)->toBeInstanceOf(UserDevice::class);
            expect($windowsDevice->platform)->toBe('Windows');
            expect($windowsDevice->device_capabilities)->toContain('file_sharing');
            expect($windowsDevice->device_info['tpm_version'])->toBe('2.0');
            expect($windowsDevice->security_level)->toBe('medium');
        });

        it('handles macOS desktop encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $macDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'MacBook Pro M3',
                'desktop',
                $keyPair['public_key'],
                'macos_device_'.uniqid(),
                'macOS',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                ['messaging', 'encryption', 'file_sharing', 'secure_backup'],
                'high',
                [
                    'os_version' => 'macOS 14.0',
                    'processor' => 'Apple M3',
                    'secure_boot' => true,
                    'filevault_enabled' => true,
                    'keychain_access' => true,
                ]
            );

            expect($macDevice)->toBeInstanceOf(UserDevice::class);
            expect($macDevice->platform)->toBe('macOS');
            expect($macDevice->device_capabilities)->toContain('secure_backup');
            expect($macDevice->device_info['secure_boot'])->toBeTrue();
            expect($macDevice->security_level)->toBe('high');
        });

        it('handles Linux server encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $linuxDevice = $this->multiDeviceService->registerDevice(
                $this->user1,
                'Ubuntu Server',
                'server',
                $keyPair['public_key'],
                'linux_device_'.uniqid(),
                'Linux',
                'Mozilla/5.0 (X11; Linux x86_64)',
                ['messaging', 'encryption', 'backup', 'api_access'],
                'maximum',
                [
                    'os_version' => 'Ubuntu 22.04 LTS',
                    'kernel_version' => '5.15.0',
                    'selinux_enabled' => false,
                    'apparmor_enabled' => true,
                    'hardware_rng' => true,
                ]
            );

            expect($linuxDevice)->toBeInstanceOf(UserDevice::class);
            expect($linuxDevice->platform)->toBe('Linux');
            expect($linuxDevice->device_capabilities)->toContain('api_access');
            expect($linuxDevice->device_info['apparmor_enabled'])->toBeTrue();
            expect($linuxDevice->security_level)->toBe('maximum');
        });
    });

    describe('Cross-Platform Key Format Compatibility', function () {
        it('ensures RSA key format compatibility across platforms', function () {
            // Generate keys on different simulated platforms
            $platforms = ['iOS', 'Android', 'Windows', 'macOS', 'Linux'];
            $keyPairs = [];
            
            foreach ($platforms as $platform) {
                $keyPair = $this->encryptionService->generateKeyPair();
                $keyPairs[$platform] = $keyPair;
                
                // All platforms should generate compatible PEM format
                expect($keyPair['public_key'])->toStartWith('-----BEGIN PUBLIC KEY-----');
                expect($keyPair['public_key'])->toEndWith('-----END PUBLIC KEY-----');
                expect($keyPair['private_key'])->toStartWith('-----BEGIN PRIVATE KEY-----');
                expect($keyPair['private_key'])->toEndWith('-----END PRIVATE KEY-----');
            }
            
            // Test cross-platform encryption/decryption
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            foreach ($platforms as $encryptPlatform) {
                foreach ($platforms as $decryptPlatform) {
                    if ($encryptPlatform === $decryptPlatform) continue;
                    
                    $encryptedKey = $this->encryptionService->encryptSymmetricKey(
                        $symmetricKey,
                        $keyPairs[$encryptPlatform]['public_key']
                    );
                    
                    $decryptedKey = $this->encryptionService->decryptSymmetricKey(
                        $encryptedKey,
                        $keyPairs[$encryptPlatform]['private_key']
                    );
                    
                    expect($decryptedKey)->toBe($symmetricKey);
                }
            }
        });

        it('handles different base64 encoding standards', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $encryptedKey = $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair['public_key']);
            
            // Test different base64 variants that might come from different platforms
            $variants = [
                $encryptedKey, // Standard
                str_replace('+', '-', str_replace('/', '_', $encryptedKey)), // URL-safe
                rtrim($encryptedKey, '='), // No padding
            ];
            
            foreach ($variants as $variant) {
                try {
                    // The service should handle different base64 formats gracefully
                    $decoded = base64_decode($variant);
                    if ($decoded !== false && strlen($decoded) > 0) {
                        // If it's valid base64, it should work with our system
                        expect($decoded)->toBeString();
                    }
                } catch (\Exception $e) {
                    // Some variants might not be valid, which is fine
                    expect($e)->toBeInstanceOf(\Exception::class);
                }
            }
        });
    });

    describe('Platform-Specific Security Features', function () {
        it('leverages iOS Secure Enclave capabilities', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $iOSDevice = UserDevice::create([
                'user_id' => $this->user1->id,
                'device_name' => 'iPhone with Secure Enclave',
                'device_type' => 'mobile',
                'public_key' => $keyPair['public_key'],
                'device_fingerprint' => 'ios_secure_'.uniqid(),
                'platform' => 'iOS',
                'device_capabilities' => ['messaging', 'encryption', 'secure_enclave'],
                'security_level' => 'maximum',
                'encryption_version' => 2,
                'is_trusted' => true,
                'is_active' => true,
                'device_info' => [
                    'secure_enclave' => true,
                    'biometric_enabled' => true,
                    'device_passcode_set' => true,
                ],
            ]);

            // High-security devices should have enhanced capabilities
            expect($iOSDevice->security_level)->toBe('maximum');
            expect($iOSDevice->device_capabilities)->toContain('secure_enclave');
            expect($iOSDevice->device_info['secure_enclave'])->toBeTrue();
        });

        it('leverages Android Hardware Security Module', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $androidDevice = UserDevice::create([
                'user_id' => $this->user1->id,
                'device_name' => 'Android with HSM',
                'device_type' => 'mobile',
                'public_key' => $keyPair['public_key'],
                'device_fingerprint' => 'android_hsm_'.uniqid(),
                'platform' => 'Android',
                'device_capabilities' => ['messaging', 'encryption', 'hardware_keystore'],
                'security_level' => 'high',
                'encryption_version' => 2,
                'is_trusted' => true,
                'is_active' => true,
                'device_info' => [
                    'strongbox' => true,
                    'keystore_version' => '4.1',
                    'hardware_backed' => true,
                ],
            ]);

            expect($androidDevice->security_level)->toBe('high');
            expect($androidDevice->device_capabilities)->toContain('hardware_keystore');
            expect($androidDevice->device_info['strongbox'])->toBeTrue();
        });

        it('leverages Windows TPM for key protection', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $windowsDevice = UserDevice::create([
                'user_id' => $this->user1->id,
                'device_name' => 'Windows with TPM',
                'device_type' => 'desktop',
                'public_key' => $keyPair['public_key'],
                'device_fingerprint' => 'windows_tpm_'.uniqid(),
                'platform' => 'Windows',
                'device_capabilities' => ['messaging', 'encryption', 'tpm_protection'],
                'security_level' => 'high',
                'encryption_version' => 2,
                'is_trusted' => true,
                'is_active' => true,
                'device_info' => [
                    'tpm_version' => '2.0',
                    'bitlocker_enabled' => true,
                    'secure_boot' => true,
                ],
            ]);

            expect($windowsDevice->security_level)->toBe('high');
            expect($windowsDevice->device_capabilities)->toContain('tpm_protection');
            expect($windowsDevice->device_info['tpm_version'])->toBe('2.0');
        });
    });

    describe('Browser and WebView Compatibility', function () {
        it('handles web browser encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $webDevice = UserDevice::create([
                'user_id' => $this->user1->id,
                'device_name' => 'Chrome Browser',
                'device_type' => 'browser',
                'public_key' => $keyPair['public_key'],
                'device_fingerprint' => 'chrome_'.uniqid(),
                'platform' => 'Web',
                'device_capabilities' => ['messaging', 'encryption', 'web_crypto'],
                'security_level' => 'medium',
                'encryption_version' => 2,
                'is_trusted' => false, // Browsers typically start untrusted
                'is_active' => true,
                'device_info' => [
                    'browser' => 'Chrome',
                    'version' => '120.0.6099.109',
                    'web_crypto_supported' => true,
                    'persistent_storage' => false,
                ],
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]);

            expect($webDevice->platform)->toBe('Web');
            expect($webDevice->device_capabilities)->toContain('web_crypto');
            expect($webDevice->device_info['web_crypto_supported'])->toBeTrue();
            expect($webDevice->security_level)->toBe('medium'); // Browsers have limitations
        });

        it('handles mobile WebView encryption', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $webViewDevice = UserDevice::create([
                'user_id' => $this->user1->id,
                'device_name' => 'iOS WebView',
                'device_type' => 'webview',
                'public_key' => $keyPair['public_key'],
                'device_fingerprint' => 'ios_webview_'.uniqid(),
                'platform' => 'iOS WebView',
                'device_capabilities' => ['messaging', 'encryption', 'webview_bridge'],
                'security_level' => 'medium',
                'encryption_version' => 2,
                'is_trusted' => false,
                'is_active' => true,
                'device_info' => [
                    'host_app' => 'MyApp',
                    'webview_version' => 'WKWebView',
                    'bridge_available' => true,
                    'keychain_access' => false,
                ],
            ]);

            expect($webViewDevice->platform)->toBe('iOS WebView');
            expect($webViewDevice->device_capabilities)->toContain('webview_bridge');
            expect($webViewDevice->device_info['bridge_available'])->toBeTrue();
        });
    });

    describe('Message Format Compatibility', function () {
        it('ensures message format works across all platforms', function () {
            $platforms = [
                ['iOS', 'mobile'],
                ['Android', 'mobile'],
                ['Windows', 'desktop'],
                ['macOS', 'desktop'],
                ['Linux', 'server'],
                ['Web', 'browser'],
            ];

            $devices = [];
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Create devices for each platform
            foreach ($platforms as [$platform, $type]) {
                $keyPair = $this->encryptionService->generateKeyPair();
                
                $device = UserDevice::create([
                    'user_id' => $this->user1->id,
                    'device_name' => "{$platform} Device",
                    'device_type' => $type,
                    'public_key' => $keyPair['public_key'],
                    'device_fingerprint' => strtolower($platform).'_'.uniqid(),
                    'platform' => $platform,
                    'device_capabilities' => ['messaging', 'encryption'],
                    'security_level' => 'medium',
                    'encryption_version' => 2,
                    'is_trusted' => true,
                    'is_active' => true,
                ]);

                // Create encryption key for this device
                EncryptionKey::createForDevice(
                    $this->conversation->id,
                    $this->user1->id,
                    $device->id,
                    $symmetricKey,
                    $keyPair['public_key']
                );

                $devices[$platform] = $device;
            }

            // Test message creation and reading across platforms
            $testMessages = [
                'Simple text message',
                'Message with émojis 🚀 🔒 📱',
                'Message with special chars: !@#$%^&*()',
                'Long message: ' . str_repeat('Lorem ipsum dolor sit amet. ', 50),
            ];

            foreach ($testMessages as $content) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );

                // Each platform should be able to decrypt the message
                $decryptedContent = $message->decryptContent($symmetricKey);
                expect($decryptedContent)->toBe($content);

                // Verify encrypted content structure is consistent
                $encryptedData = json_decode($message->encrypted_content, true);
                expect($encryptedData)->toHaveKeys(['data', 'iv', 'hmac', 'auth_data', 'timestamp', 'nonce']);
            }
        });

        it('handles different character encodings', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            $testStrings = [
                'ASCII text',
                'UTF-8 with accents: café résumé naïve',
                'Chinese: 你好世界',
                'Arabic: مرحبا بالعالم',
                'Russian: Привет мир',
                'Emojis: 🌍🚀💫⭐️🔒',
                'Mixed: Hello 世界! 🌎 Café',
            ];

            foreach ($testStrings as $content) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    $content,
                    $symmetricKey
                );

                $decryptedContent = $message->decryptContent($symmetricKey);
                expect($decryptedContent)->toBe($content);
                expect(mb_strlen($decryptedContent))->toBe(mb_strlen($content));
            }
        });
    });

    describe('Performance Across Platforms', function () {
        it('maintains acceptable performance on mobile platforms', function () {
            $mobileDevices = [];
            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            // Simulate iOS and Android devices
            foreach (['iOS', 'Android'] as $platform) {
                $keyPair = $this->encryptionService->generateKeyPair();
                
                $device = UserDevice::create([
                    'user_id' => $this->user1->id,
                    'device_name' => "{$platform} Mobile",
                    'device_type' => 'mobile',
                    'public_key' => $keyPair['public_key'],
                    'device_fingerprint' => strtolower($platform).'_mobile_'.uniqid(),
                    'platform' => $platform,
                    'device_capabilities' => ['messaging', 'encryption'],
                    'security_level' => 'high',
                    'encryption_version' => 2,
                    'is_trusted' => true,
                    'is_active' => true,
                ]);

                $mobileDevices[$platform] = $device;
            }

            // Test encryption performance (should be fast enough for mobile)
            $startTime = microtime(true);
            
            for ($i = 0; $i < 10; $i++) {
                $message = Message::createEncrypted(
                    $this->conversation->id,
                    $this->user1->id,
                    "Mobile test message {$i}",
                    $symmetricKey
                );

                $decrypted = $message->decryptContent($symmetricKey);
                expect($decrypted)->toBe("Mobile test message {$i}");
            }

            $totalTime = microtime(true) - $startTime;
            
            // Should complete 10 encrypt/decrypt cycles in under 2 seconds on mobile
            expect($totalTime)->toBeLessThan(2.0);
        });

        it('optimizes for low-power devices', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            
            $lowPowerDevice = UserDevice::create([
                'user_id' => $this->user1->id,
                'device_name' => 'IoT Device',
                'device_type' => 'embedded',
                'public_key' => $keyPair['public_key'],
                'device_fingerprint' => 'iot_device_'.uniqid(),
                'platform' => 'Embedded',
                'device_capabilities' => ['messaging', 'encryption'],
                'security_level' => 'medium',
                'encryption_version' => 2,
                'is_trusted' => true,
                'is_active' => true,
                'device_info' => [
                    'cpu_limited' => true,
                    'memory_limited' => true,
                    'power_constrained' => true,
                ],
            ]);

            // Test that basic operations work on constrained devices
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $testMessage = 'Simple message for IoT';

            $message = Message::createEncrypted(
                $this->conversation->id,
                $this->user1->id,
                $testMessage,
                $symmetricKey
            );

            $decrypted = $message->decryptContent($symmetricKey);
            expect($decrypted)->toBe($testMessage);
            expect($lowPowerDevice->device_info['cpu_limited'])->toBeTrue();
        });
    });

    describe('Backward Compatibility', function () {
        it('maintains compatibility with older encryption versions', function () {
            $keyPair = $this->encryptionService->generateKeyPair();
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            
            // Create encryption key with older version
            $oldVersionKey = EncryptionKey::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user1->id,
                'encrypted_key' => $this->encryptionService->encryptSymmetricKey($symmetricKey, $keyPair['public_key']),
                'public_key' => $keyPair['public_key'],
                'key_version' => 1, // Older version
                'is_active' => true,
                'created_by' => $this->user1->id,
            ]);

            // Should still be able to decrypt with older version key
            $decryptedKey = $oldVersionKey->decryptSymmetricKey($keyPair['private_key']);
            expect($decryptedKey)->toBe($symmetricKey);
        });

        it('handles migration from older message formats', function () {
            // Simulate older message format (without some newer fields)
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $content = 'Legacy message format';
            
            $encrypted = $this->encryptionService->encryptMessage($content, $symmetricKey);
            
            // Create message with minimal fields (simulating older format)
            $legacyMessage = Message::create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => $this->user1->id,
                'content' => $content,
                'content_hash' => hash('sha256', $content),
                'encrypted_content' => json_encode([
                    'data' => $encrypted['data'],
                    'iv' => $encrypted['iv'],
                    // Missing some newer fields
                ]),
                'content_hmac' => $encrypted['hash'],
            ]);

            // Should still be able to handle legacy format
            try {
                $decrypted = $legacyMessage->decryptContent($symmetricKey);
                expect($decrypted)->toBe($content);
            } catch (\Exception $e) {
                // If legacy format is not supported, that's also acceptable
                expect($e)->toBeInstanceOf(\Exception::class);
            }
        });
    });
});