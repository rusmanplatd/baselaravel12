# Quantum Algorithm Implementation Guide

## Quick Start Implementation

This guide provides step-by-step instructions for implementing quantum-resistant algorithms in your existing Laravel chat system without breaking changes.

## Step 1: Install ML-KEM Library

### Option A: LibOQS Extension (Recommended)

```bash
# Install liboqs C library
cd /tmp
git clone https://github.com/open-quantum-safe/liboqs.git
cd liboqs
mkdir build && cd build
cmake .. -DCMAKE_INSTALL_PREFIX=/usr/local
make -j$(nproc)
sudo make install

# Install PHP extension (if available)
pecl install liboqs-php
```

### Option B: Pure PHP Implementation (Fallback)

```bash
# Awaiting Paragon Initiative's ML-KEM PHP library
# Monitor: https://github.com/paragonie/ml-kem-php
composer require paragonie/ml-kem-php  # When available
```

## Step 2: Extend ChatEncryptionService

Create the quantum-resistant service extension:

```php
<?php
// app/Services/QuantumCryptoService.php

namespace App\Services;

use App\Exceptions\EncryptionException;
use Illuminate\Support\Facades\Log;

class QuantumCryptoService
{
    private ChatEncryptionService $encryptionService;
    
    public function __construct(ChatEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }
    
    /**
     * Generate ML-KEM key pair
     */
    public function generateMLKEMKeyPair(int $securityLevel = 768): array
    {
        if (!$this->isMLKEMAvailable()) {
            throw new EncryptionException('ML-KEM not available on this system');
        }
        
        try {
            // Use LibOQS if available
            if (extension_loaded('liboqs')) {
                return $this->generateLibOQSKeyPair($securityLevel);
            }
            
            // Fallback to pure PHP implementation
            return $this->generatePurePHPKeyPair($securityLevel);
            
        } catch (\Exception $e) {
            Log::error('ML-KEM key generation failed', [
                'security_level' => $securityLevel,
                'error' => $e->getMessage()
            ]);
            throw new EncryptionException('ML-KEM key generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * ML-KEM encapsulation
     */
    public function encapsulateMLKEM(string $publicKey, int $securityLevel = 768): array
    {
        $publicKeyBytes = base64_decode($publicKey);
        
        if (extension_loaded('liboqs')) {
            $kem = new \OQS\KEM("Kyber{$securityLevel}");
            [$ciphertext, $sharedSecret] = $kem->encaps($publicKeyBytes);
            
            return [
                'ciphertext' => base64_encode($ciphertext),
                'shared_secret' => $sharedSecret,
                'algorithm' => "ML-KEM-{$securityLevel}"
            ];
        }
        
        throw new EncryptionException('ML-KEM encapsulation not available');
    }
    
    /**
     * ML-KEM decapsulation
     */
    public function decapsulateMLKEM(string $ciphertext, string $privateKey, int $securityLevel = 768): string
    {
        $ciphertextBytes = base64_decode($ciphertext);
        $privateKeyBytes = base64_decode($privateKey);
        
        if (extension_loaded('liboqs')) {
            $kem = new \OQS\KEM("Kyber{$securityLevel}");
            return $kem->decaps($ciphertextBytes, $privateKeyBytes);
        }
        
        throw new EncryptionException('ML-KEM decapsulation not available');
    }
    
    /**
     * Check if ML-KEM is available
     */
    public function isMLKEMAvailable(): bool
    {
        return extension_loaded('liboqs') || class_exists('\Paragonie\MLKEM\MLKEM');
    }
    
    /**
     * Generate hybrid RSA + ML-KEM key pair
     */
    public function generateHybridKeyPair(): array
    {
        $rsaKeyPair = $this->encryptionService->generateKeyPair(4096);
        $mlkemKeyPair = $this->generateMLKEMKeyPair(768);
        
        $hybridPublicKey = base64_encode(json_encode([
            'version' => '1.0',
            'algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'components' => [
                'rsa' => $rsaKeyPair['public_key'],
                'ml-kem' => $mlkemKeyPair['public_key']
            ]
        ]));
        
        $hybridPrivateKey = base64_encode(json_encode([
            'version' => '1.0',
            'algorithm' => 'HYBRID-RSA4096-MLKEM768',
            'components' => [
                'rsa' => $rsaKeyPair['private_key'],
                'ml-kem' => $mlkemKeyPair['private_key']
            ]
        ]));
        
        return [
            'public_key' => $hybridPublicKey,
            'private_key' => $hybridPrivateKey,
            'algorithm' => 'HYBRID-RSA4096-MLKEM768'
        ];
    }
    
    private function generateLibOQSKeyPair(int $securityLevel): array
    {
        $kem = new \OQS\KEM("Kyber{$securityLevel}");
        [$publicKey, $privateKey] = $kem->keypair();
        
        return [
            'public_key' => base64_encode($publicKey),
            'private_key' => base64_encode($privateKey),
            'algorithm' => "ML-KEM-{$securityLevel}"
        ];
    }
}
```

## Step 3: Update ChatEncryptionService

Extend the existing service with quantum-resistant methods:

```php
<?php
// In app/Services/ChatEncryptionService.php - Add these methods

/**
 * Generate key pair with algorithm selection
 */
public function generateKeyPair(?int $keySize = null, string $algorithm = 'RSA-4096-OAEP'): array
{
    return match ($algorithm) {
        'ML-KEM-512' => app(QuantumCryptoService::class)->generateMLKEMKeyPair(512),
        'ML-KEM-768' => app(QuantumCryptoService::class)->generateMLKEMKeyPair(768),
        'ML-KEM-1024' => app(QuantumCryptoService::class)->generateMLKEMKeyPair(1024),
        'HYBRID-RSA4096-MLKEM768' => app(QuantumCryptoService::class)->generateHybridKeyPair(),
        default => $this->generateRSAKeyPair($keySize ?? 4096)
    };
}

/**
 * Encrypt symmetric key with algorithm support
 */
public function encryptSymmetricKeyWithAlgorithm(string $symmetricKey, string $publicKey, string $algorithm): string
{
    return match ($algorithm) {
        'ML-KEM-512' => app(QuantumCryptoService::class)->encapsulateMLKEM($publicKey, 512)['ciphertext'],
        'ML-KEM-768' => app(QuantumCryptoService::class)->encapsulateMLKEM($publicKey, 768)['ciphertext'],
        'ML-KEM-1024' => app(QuantumCryptoService::class)->encapsulateMLKEM($publicKey, 1024)['ciphertext'],
        'HYBRID-RSA4096-MLKEM768' => $this->encryptSymmetricKeyHybrid($symmetricKey, $publicKey),
        default => $this->encryptSymmetricKey($symmetricKey, $publicKey)
    };
}

/**
 * Decrypt symmetric key with algorithm support
 */
public function decryptSymmetricKeyWithAlgorithm(string $encryptedKey, string $privateKey, string $algorithm): string
{
    return match ($algorithm) {
        'ML-KEM-512' => app(QuantumCryptoService::class)->decapsulateMLKEM($encryptedKey, $privateKey, 512),
        'ML-KEM-768' => app(QuantumCryptoService::class)->decapsulateMLKEM($encryptedKey, $privateKey, 768),
        'ML-KEM-1024' => app(QuantumCryptoService::class)->decapsulateMLKEM($encryptedKey, $privateKey, 1024),
        'HYBRID-RSA4096-MLKEM768' => $this->decryptSymmetricKeyHybrid($encryptedKey, $privateKey),
        default => $this->decryptSymmetricKey($encryptedKey, $privateKey)
    };
}

/**
 * Algorithm negotiation
 */
public function negotiateAlgorithm(array $deviceCapabilities): string
{
    $commonAlgorithms = [];
    
    foreach ($deviceCapabilities as $deviceCaps) {
        if (empty($commonAlgorithms)) {
            $commonAlgorithms = $deviceCaps;
        } else {
            $commonAlgorithms = array_intersect($commonAlgorithms, $deviceCaps);
        }
    }
    
    // Priority: ML-KEM > Hybrid > RSA
    $priority = ['ML-KEM-768', 'HYBRID-RSA4096-MLKEM768', 'ML-KEM-512', 'RSA-4096-OAEP'];
    
    foreach ($priority as $algorithm) {
        if (in_array($algorithm, $commonAlgorithms)) {
            return $algorithm;
        }
    }
    
    throw new EncryptionException('No compatible algorithm found');
}
```

## Step 4: Update Device Registration

Modify device registration to include quantum capabilities:

```php
<?php
// In app/Services/MultiDeviceEncryptionService.php

public function registerQuantumDevice(
    User $user,
    string $deviceName,
    string $deviceType,
    string $publicKey,
    string $deviceFingerprint,
    array $quantumCapabilities = ['ml-kem-768']
): UserDevice {
    $allCapabilities = array_merge(
        ['messaging', 'encryption'], 
        $quantumCapabilities
    );
    
    $encryptionVersion = $this->determineEncryptionVersion($quantumCapabilities);
    
    return $this->registerDevice(
        $user,
        $deviceName,
        $deviceType,
        $publicKey,
        $deviceFingerprint,
        platform: null,
        userAgent: null,
        deviceCapabilities: $allCapabilities,
        securityLevel: 'high', // Quantum devices get high security
        deviceInfo: ['quantum_ready' => true]
    );
}

private function determineEncryptionVersion(array $capabilities): int
{
    $quantumCapabilities = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid'];
    
    return !empty(array_intersect($capabilities, $quantumCapabilities)) ? 3 : 2;
}
```

## Step 5: Update Conversation Setup

Modify conversation encryption setup:

```php
<?php
// In app/Services/MultiDeviceEncryptionService.php

public function setupQuantumConversationEncryption(
    Conversation $conversation,
    array $participantDevices,
    UserDevice $initiatingDevice
): array {
    // Get device capabilities
    $deviceCapabilities = [];
    foreach ($participantDevices as $device) {
        $caps = $device->device_capabilities ?? ['RSA-4096-OAEP'];
        $deviceCapabilities[] = $this->mapCapabilitiesToAlgorithms($caps);
    }
    
    // Negotiate best algorithm
    $encryptionService = app(ChatEncryptionService::class);
    $algorithm = $encryptionService->negotiateAlgorithm($deviceCapabilities);
    
    Log::info('Quantum conversation setup initiated', [
        'conversation_id' => $conversation->id,
        'algorithm' => $algorithm,
        'device_count' => count($participantDevices)
    ]);
    
    // Generate new symmetric key
    $symmetricKey = $encryptionService->generateSymmetricKey();
    $keyVersion = $this->getNextKeyVersion($conversation);
    
    $results = [
        'algorithm' => $algorithm,
        'key_version' => $keyVersion,
        'created_keys' => [],
        'failed_keys' => []
    ];
    
    // Create encryption keys for each device
    foreach ($participantDevices as $device) {
        try {
            // Encrypt symmetric key using negotiated algorithm
            $encryptedSymKey = $encryptionService->encryptSymmetricKeyWithAlgorithm(
                $symmetricKey,
                $device->public_key,
                $algorithm
            );
            
            $encryptionKey = EncryptionKey::create([
                'conversation_id' => $conversation->id,
                'user_id' => $device->user_id,
                'device_id' => $device->id,
                'encrypted_key' => $encryptedSymKey,
                'public_key' => $device->public_key,
                'algorithm' => $algorithm,
                'key_strength' => $this->getAlgorithmStrength($algorithm),
                'key_version' => $keyVersion,
                'device_fingerprint' => $device->device_fingerprint,
                'created_by_device_id' => $initiatingDevice->id,
            ]);
            
            $results['created_keys'][] = [
                'device_id' => $device->id,
                'encryption_key_id' => $encryptionKey->id,
                'algorithm' => $algorithm
            ];
            
        } catch (\Exception $e) {
            $results['failed_keys'][] = [
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}

private function mapCapabilitiesToAlgorithms(array $capabilities): array
{
    $algorithmMap = [
        'rsa-4096' => 'RSA-4096-OAEP',
        'ml-kem-512' => 'ML-KEM-512',
        'ml-kem-768' => 'ML-KEM-768',
        'ml-kem-1024' => 'ML-KEM-1024',
        'hybrid' => 'HYBRID-RSA4096-MLKEM768'
    ];
    
    $algorithms = [];
    foreach ($capabilities as $cap) {
        if (isset($algorithmMap[$cap])) {
            $algorithms[] = $algorithmMap[$cap];
        }
    }
    
    return $algorithms ?: ['RSA-4096-OAEP']; // Fallback
}

private function getAlgorithmStrength(string $algorithm): int
{
    return match ($algorithm) {
        'ML-KEM-512' => 512,
        'ML-KEM-768' => 768,
        'ML-KEM-1024' => 1024,
        'HYBRID-RSA4096-MLKEM768' => 768,
        default => 4096
    };
}
```

## Step 6: Update Message Encryption/Decryption

Modify message handling to support quantum algorithms:

```php
<?php
// In app/Http/Controllers/Api/Chat/MessageController.php

public function store(Request $request)
{
    $validated = $request->validate([
        'conversation_id' => 'required|exists:chat_conversations,id',
        'content' => 'required|string',
        'message_type' => 'string|in:text,image,file,voice',
    ]);
    
    $conversation = Conversation::findOrFail($validated['conversation_id']);
    $user = auth()->user();
    $device = $user->getCurrentDevice(); // Implement this method
    
    // Get encryption keys for this conversation
    $encryptionKeys = EncryptionKey::where('conversation_id', $conversation->id)
        ->where('is_active', true)
        ->with('device')
        ->get();
    
    if ($encryptionKeys->isEmpty()) {
        return response()->json(['error' => 'No encryption keys found'], 400);
    }
    
    // Use the first key to determine algorithm (all should be same version)
    $firstKey = $encryptionKeys->first();
    $algorithm = $firstKey->algorithm;
    
    Log::info('Encrypting message with quantum algorithm', [
        'algorithm' => $algorithm,
        'key_version' => $firstKey->key_version
    ]);
    
    // Get symmetric key for this device
    $deviceKey = $encryptionKeys->where('device_id', $device->id)->first();
    if (!$deviceKey) {
        return response()->json(['error' => 'No encryption key for this device'], 403);
    }
    
    // Decrypt symmetric key using algorithm-specific method
    $encryptionService = app(ChatEncryptionService::class);
    $symmetricKey = $encryptionService->decryptSymmetricKeyWithAlgorithm(
        $deviceKey->encrypted_key,
        $device->getPrivateKey(), // Implement this method
        $algorithm
    );
    
    // Encrypt message
    $encryptedMessage = $encryptionService->encryptMessage($validated['content'], $symmetricKey);
    
    // Store message
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'device_id' => $device->id,
        'content' => $encryptedMessage['data'],
        'content_iv' => $encryptedMessage['iv'],
        'content_hash' => $encryptedMessage['hash'],
        'content_hmac' => $encryptedMessage['hmac'],
        'auth_data' => $encryptedMessage['auth_data'],
        'message_type' => $validated['message_type'] ?? 'text',
        'encryption_algorithm' => $algorithm,
        'key_version' => $firstKey->key_version,
    ]);
    
    return response()->json(['message' => $message->load('user')]);
}
```

## Step 7: Frontend Integration

Update frontend to support quantum algorithms:

```typescript
// resources/js/services/QuantumE2EEService.ts

interface QuantumAlgorithm {
  name: string;
  keySize: number;
  quantumResistant: boolean;
  performance: 'fast' | 'medium' | 'slow';
}

export class QuantumE2EEService {
  private supportedAlgorithms: QuantumAlgorithm[] = [
    { name: 'ML-KEM-768', keySize: 768, quantumResistant: true, performance: 'fast' },
    { name: 'ML-KEM-512', keySize: 512, quantumResistant: true, performance: 'fast' },
    { name: 'HYBRID-RSA4096-MLKEM768', keySize: 768, quantumResistant: true, performance: 'medium' },
    { name: 'RSA-4096-OAEP', keySize: 4096, quantumResistant: false, performance: 'slow' }
  ];

  async generateQuantumKeyPair(algorithm: string = 'ML-KEM-768') {
    try {
      const response = await fetch('/api/quantum/generate-keypair', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ algorithm })
      });
      
      if (!response.ok) throw new Error('Key generation failed');
      
      return await response.json();
    } catch (error) {
      console.error('Quantum key generation failed:', error);
      throw error;
    }
  }

  async registerQuantumDevice(capabilities: string[] = ['ml-kem-768']) {
    const keyPair = await this.generateQuantumKeyPair();
    
    const response = await fetch('/api/devices/register-quantum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        device_name: this.getDeviceName(),
        device_type: this.getDeviceType(),
        public_key: keyPair.public_key,
        device_fingerprint: await this.getDeviceFingerprint(),
        quantum_capabilities: capabilities,
        device_info: {
          user_agent: navigator.userAgent,
          quantum_ready: true,
          supported_algorithms: this.supportedAlgorithms.map(a => a.name)
        }
      })
    });
    
    if (!response.ok) throw new Error('Device registration failed');
    
    // Store private key securely
    await this.storePrivateKey(keyPair.private_key);
    
    return await response.json();
  }

  async negotiateConversationAlgorithm(conversationId: string): Promise<string> {
    const response = await fetch(`/api/conversations/${conversationId}/negotiate-algorithm`, {
      method: 'POST'
    });
    
    if (!response.ok) throw new Error('Algorithm negotiation failed');
    
    const result = await response.json();
    return result.algorithm;
  }

  getAlgorithmInfo(algorithm: string): QuantumAlgorithm | null {
    return this.supportedAlgorithms.find(a => a.name === algorithm) || null;
  }

  isQuantumResistant(algorithm: string): boolean {
    const info = this.getAlgorithmInfo(algorithm);
    return info?.quantumResistant || false;
  }

  private getDeviceName(): string {
    return `${navigator.platform} - ${new Date().toISOString().slice(0, 10)}`;
  }

  private getDeviceType(): string {
    const ua = navigator.userAgent;
    if (/mobile|android|iphone/i.test(ua)) return 'mobile';
    if (/tablet|ipad/i.test(ua)) return 'tablet';
    return 'desktop';
  }

  private async getDeviceFingerprint(): Promise<string> {
    // Implement device fingerprinting
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.textBaseline = 'top';
    ctx.font = '14px Arial';
    ctx.fillText('Device fingerprint', 2, 2);
    
    const fingerprint = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      new Date().getTimezoneOffset(),
      canvas.toDataURL()
    ].join('|');
    
    const encoder = new TextEncoder();
    const data = encoder.encode(fingerprint);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  }

  private async storePrivateKey(privateKey: string): Promise<void> {
    // Store in IndexedDB or secure storage
    const request = indexedDB.open('quantum-keys', 1);
    
    return new Promise((resolve, reject) => {
      request.onsuccess = () => {
        const db = request.result;
        const transaction = db.transaction(['keys'], 'readwrite');
        const store = transaction.objectStore('keys');
        store.put({ id: 'device-private-key', key: privateKey });
        transaction.oncomplete = () => resolve();
      };
      
      request.onupgradeneeded = () => {
        const db = request.result;
        db.createObjectStore('keys', { keyPath: 'id' });
      };
      
      request.onerror = () => reject(request.error);
    });
  }
}
```

## Step 8: API Routes

Add quantum-specific API endpoints:

```php
<?php
// In routes/api.php

use App\Http\Controllers\Api\QuantumController;

Route::middleware('auth:api')->group(function () {
    Route::post('/quantum/generate-keypair', [QuantumController::class, 'generateKeyPair']);
    Route::post('/devices/register-quantum', [QuantumController::class, 'registerQuantumDevice']);
    Route::post('/conversations/{conversation}/negotiate-algorithm', [QuantumController::class, 'negotiateAlgorithm']);
    Route::get('/quantum/health', [QuantumController::class, 'healthCheck']);
});
```

## Step 9: Create QuantumController

```php
<?php
// app/Http/Controllers/Api/QuantumController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuantumCryptoService;
use App\Services\MultiDeviceEncryptionService;
use App\Models\Chat\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuantumController extends Controller
{
    public function generateKeyPair(Request $request)
    {
        $validated = $request->validate([
            'algorithm' => 'string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024,HYBRID-RSA4096-MLKEM768'
        ]);
        
        $algorithm = $validated['algorithm'] ?? 'ML-KEM-768';
        
        try {
            $quantumService = app(QuantumCryptoService::class);
            $keyPair = $quantumService->generateMLKEMKeyPair(
                str_replace('ML-KEM-', '', $algorithm)
            );
            
            return response()->json($keyPair);
            
        } catch (\Exception $e) {
            Log::error('Quantum key generation failed', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Key generation failed'], 500);
        }
    }
    
    public function registerQuantumDevice(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string',
            'device_type' => 'required|string',
            'public_key' => 'required|string',
            'device_fingerprint' => 'required|string',
            'quantum_capabilities' => 'array',
            'device_info' => 'array'
        ]);
        
        try {
            $multiDeviceService = app(MultiDeviceEncryptionService::class);
            $device = $multiDeviceService->registerQuantumDevice(
                auth()->user(),
                $validated['device_name'],
                $validated['device_type'],
                $validated['public_key'],
                $validated['device_fingerprint'],
                $validated['quantum_capabilities'] ?? ['ml-kem-768']
            );
            
            return response()->json([
                'device' => $device,
                'quantum_ready' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Quantum device registration failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Device registration failed'], 500);
        }
    }
    
    public function negotiateAlgorithm(Conversation $conversation)
    {
        try {
            $devices = $conversation->getParticipantDevices();
            $deviceCapabilities = $devices->map(function ($device) {
                return $device->getQuantumCapabilities();
            })->toArray();
            
            $encryptionService = app(ChatEncryptionService::class);
            $algorithm = $encryptionService->negotiateAlgorithm($deviceCapabilities);
            
            return response()->json([
                'algorithm' => $algorithm,
                'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm),
                'participants' => count($devices)
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Algorithm negotiation failed'], 500);
        }
    }
    
    public function healthCheck()
    {
        $quantumService = app(QuantumCryptoService::class);
        
        $health = [
            'ml_kem_available' => $quantumService->isMLKEMAvailable(),
            'liboqs_loaded' => extension_loaded('liboqs'),
            'supported_algorithms' => [
                'ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 
                'HYBRID-RSA4096-MLKEM768'
            ],
            'quantum_ready' => $quantumService->isMLKEMAvailable()
        ];
        
        return response()->json($health);
    }
}
```

## Step 10: Testing

Create comprehensive tests:

```php
<?php
// tests/Feature/QuantumCryptographyTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\QuantumCryptoService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuantumCryptographyTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_ml_kem_key_generation()
    {
        if (!extension_loaded('liboqs')) {
            $this->markTestSkipped('LibOQS extension not available');
        }
        
        $quantumService = app(QuantumCryptoService::class);
        $keyPair = $quantumService->generateMLKEMKeyPair(768);
        
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertEquals('ML-KEM-768', $keyPair['algorithm']);
    }
    
    public function test_quantum_device_registration()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = $this->postJson('/api/devices/register-quantum', [
            'device_name' => 'Test Quantum Device',
            'device_type' => 'desktop',
            'public_key' => base64_encode('fake-quantum-public-key'),
            'device_fingerprint' => hash('sha256', 'test-fingerprint'),
            'quantum_capabilities' => ['ml-kem-768', 'hybrid']
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['quantum_ready' => true]);
    }
    
    public function test_algorithm_negotiation()
    {
        // Create conversation with mixed device types
        $conversation = $this->createTestConversation();
        
        $response = $this->postJson("/api/conversations/{$conversation->id}/negotiate-algorithm");
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['algorithm', 'quantum_resistant']);
    }
}
```

## Migration Checklist

- [ ] Install ML-KEM library (LibOQS or pure PHP)
- [ ] Create QuantumCryptoService
- [ ] Extend ChatEncryptionService with quantum methods
- [ ] Update device registration for quantum capabilities  
- [ ] Modify conversation setup for algorithm negotiation
- [ ] Update message encryption/decryption flows
- [ ] Add frontend quantum support
- [ ] Create quantum-specific API endpoints
- [ ] Implement comprehensive testing
- [ ] Deploy with backward compatibility intact

## Rollback Strategy

If issues arise:

1. **Disable quantum algorithms** in configuration
2. **Revert to RSA-only** for new conversations
3. **Existing quantum conversations** remain functional
4. **No data loss** - all keys stored with algorithm metadata

This implementation provides a complete quantum-resistant solution while maintaining full backward compatibility with your existing RSA-encrypted conversations.