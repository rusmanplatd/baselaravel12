<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuantumCryptoService;
use App\Services\MultiDeviceEncryptionService;
use App\Services\ChatEncryptionService;
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
            
            if ($algorithm === 'HYBRID-RSA4096-MLKEM768') {
                $keyPair = $quantumService->generateHybridKeyPair();
            } else {
                $securityLevel = (int) str_replace('ML-KEM-', '', $algorithm);
                $keyPair = $quantumService->generateMLKEMKeyPair($securityLevel);
            }
            
            Log::info('Quantum key pair generated via API', [
                'user_id' => auth()->id(),
                'algorithm' => $algorithm,
                'provider' => $keyPair['provider'] ?? 'unknown'
            ]);
            
            return response()->json([
                'public_key' => $keyPair['public_key'],
                'private_key' => $keyPair['private_key'],
                'algorithm' => $keyPair['algorithm'],
                'key_strength' => $keyPair['key_strength'],
                'quantum_resistant' => true
            ]);
            
        } catch (\Exception $e) {
            Log::error('Quantum key generation failed', [
                'user_id' => auth()->id(),
                'algorithm' => $algorithm,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Key generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function registerQuantumDevice(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|string|in:desktop,mobile,tablet,web',
            'public_key' => 'required|string',
            'device_fingerprint' => 'required|string|max:255',
            'quantum_capabilities' => 'array',
            'quantum_capabilities.*' => 'string|in:ml-kem-512,ml-kem-768,ml-kem-1024,hybrid',
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
            
            // Update device info if provided
            if (isset($validated['device_info'])) {
                $deviceInfo = array_merge(
                    $device->device_info ?? [],
                    $validated['device_info'],
                    ['quantum_ready' => true, 'registered_via_api' => true]
                );
                
                $device->update(['device_info' => $deviceInfo]);
            }
            
            Log::info('Quantum device registered via API', [
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'quantum_capabilities' => $device->getQuantumCapabilities()
            ]);
            
            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'name' => $device->device_name,
                    'type' => $device->device_type,
                    'encryption_version' => $device->encryption_version,
                    'quantum_ready' => $device->isQuantumReady(),
                    'supported_algorithms' => $device->getSupportedAlgorithms(),
                    'security_level' => $device->security_level,
                    'is_trusted' => $device->is_trusted
                ],
                'quantum_ready' => true,
                'encryption_version' => $device->encryption_version
            ]);
            
        } catch (\Exception $e) {
            Log::error('Quantum device registration failed', [
                'user_id' => auth()->id(),
                'device_name' => $validated['device_name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Device registration failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function negotiateAlgorithm(Conversation $conversation)
    {
        try {
            // Check if user has permission to access this conversation
            $this->authorize('view', $conversation);
            
            $devices = $conversation->participants()
                ->with(['user.devices' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->get()
                ->pluck('user.devices')
                ->flatten();
            
            $deviceCapabilities = $devices->map(function ($device) {
                return $device->getSupportedAlgorithms();
            })->toArray();
            
            $encryptionService = app(ChatEncryptionService::class);
            $algorithm = $encryptionService->negotiateAlgorithm($deviceCapabilities);
            
            Log::info('Algorithm negotiated for conversation', [
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'algorithm' => $algorithm,
                'participant_count' => count($devices),
                'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm)
            ]);
            
            return response()->json([
                'algorithm' => $algorithm,
                'algorithm_info' => $encryptionService->getAlgorithmInfo($algorithm),
                'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm),
                'participants' => count($devices),
                'compatible_devices' => $devices->count(),
                'device_capabilities' => $deviceCapabilities
            ]);
            
        } catch (\Exception $e) {
            Log::error('Algorithm negotiation failed', [
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Algorithm negotiation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function healthCheck()
    {
        try {
            $quantumService = app(QuantumCryptoService::class);
            $encryptionService = app(ChatEncryptionService::class);
            
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'quantum_support' => [
                    'ml_kem_available' => $quantumService->isMLKEMAvailable(),
                    'provider' => null,
                    'supported_algorithms' => $quantumService->getSupportedAlgorithms(),
                ],
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'extensions' => [
                        'openssl' => extension_loaded('openssl'),
                        'liboqs' => extension_loaded('liboqs'),
                        'sodium' => extension_loaded('sodium'),
                    ]
                ]
            ];
            
            // Get provider info if available
            try {
                // Use reflection to access private method for health check
                $reflection = new \ReflectionClass($quantumService);
                $method = $reflection->getMethod('getMLKEMProvider');
                $method->setAccessible(true);
                $provider = $method->invoke($quantumService);
                
                $health['quantum_support']['provider'] = $provider->getProviderName();
                $health['quantum_support']['supported_levels'] = $provider->getSupportedLevels();
            } catch (\Exception $e) {
                $health['quantum_support']['provider_error'] = $e->getMessage();
            }
            
            // Test key generation if ML-KEM is available
            if ($health['quantum_support']['ml_kem_available']) {
                try {
                    $testKeyPair = $quantumService->generateMLKEMKeyPair(768);
                    $health['quantum_support']['key_generation_test'] = 'passed';
                    
                    // Test validation
                    $isValid = $quantumService->validateQuantumKeyPair(
                        $testKeyPair['public_key'],
                        $testKeyPair['private_key'],
                        'ML-KEM-768'
                    );
                    $health['quantum_support']['key_validation_test'] = $isValid ? 'passed' : 'failed';
                    
                } catch (\Exception $e) {
                    $health['quantum_support']['key_generation_test'] = 'failed';
                    $health['quantum_support']['test_error'] = $e->getMessage();
                    $health['status'] = 'degraded';
                }
            }
            
            // Check algorithm support
            $health['algorithms'] = [];
            foreach (['RSA-4096-OAEP', 'ML-KEM-512', 'ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA4096-MLKEM768'] as $algorithm) {
                $algorithmInfo = $encryptionService->getAlgorithmInfo($algorithm);
                $health['algorithms'][$algorithm] = [
                    'available' => !empty($algorithmInfo),
                    'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm),
                    'info' => $algorithmInfo
                ];
            }
            
            $statusCode = $health['status'] === 'healthy' ? 200 : 
                         ($health['status'] === 'degraded' ? 200 : 503);
            
            return response()->json($health, $statusCode);
            
        } catch (\Exception $e) {
            Log::error('Quantum health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
                'quantum_support' => [
                    'ml_kem_available' => false,
                    'error' => 'Health check failed'
                ]
            ], 503);
        }
    }
    
    public function getDeviceCapabilities(Request $request)
    {
        $user = auth()->user();
        $devices = $user->devices()->active()->get();
        
        $deviceCapabilities = $devices->map(function ($device) {
            return [
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'device_type' => $device->device_type,
                'encryption_version' => $device->encryption_version,
                'quantum_ready' => $device->isQuantumReady(),
                'quantum_capabilities' => $device->getQuantumCapabilities(),
                'supported_algorithms' => $device->getSupportedAlgorithms(),
                'security_level' => $device->security_level,
                'is_trusted' => $device->is_trusted,
                'last_used_at' => $device->last_used_at
            ];
        });
        
        $quantumReadyCount = $devices->filter(fn($device) => $device->isQuantumReady())->count();
        
        return response()->json([
            'devices' => $deviceCapabilities,
            'summary' => [
                'total_devices' => $devices->count(),
                'quantum_ready_devices' => $quantumReadyCount,
                'quantum_ready_percentage' => $devices->count() > 0 ? 
                    round(($quantumReadyCount / $devices->count()) * 100, 1) : 0,
                'recommended_algorithm' => $this->getRecommendedAlgorithm($devices)
            ]
        ]);
    }
    
    public function updateDeviceCapabilities(Request $request, $deviceId)
    {
        $validated = $request->validate([
            'quantum_capabilities' => 'required|array',
            'quantum_capabilities.*' => 'string|in:ml-kem-512,ml-kem-768,ml-kem-1024,hybrid'
        ]);
        
        try {
            $device = auth()->user()->devices()->findOrFail($deviceId);
            $device->updateQuantumCapabilities($validated['quantum_capabilities']);
            
            Log::info('Device quantum capabilities updated', [
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'new_capabilities' => $validated['quantum_capabilities'],
                'new_encryption_version' => $device->encryption_version
            ]);
            
            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'encryption_version' => $device->encryption_version,
                    'quantum_ready' => $device->isQuantumReady(),
                    'quantum_capabilities' => $device->getQuantumCapabilities(),
                    'supported_algorithms' => $device->getSupportedAlgorithms()
                ],
                'message' => 'Device capabilities updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update device capabilities', [
                'user_id' => auth()->id(),
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to update device capabilities',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getRecommendedAlgorithm($devices): string
    {
        if ($devices->isEmpty()) {
            return 'RSA-4096-OAEP';
        }
        
        $allQuantumReady = $devices->every(fn($device) => $device->isQuantumReady());
        $anyQuantumReady = $devices->some(fn($device) => $device->isQuantumReady());
        
        if ($allQuantumReady) {
            return 'ML-KEM-768'; // All devices support quantum resistance
        }
        
        if ($anyQuantumReady) {
            return 'HYBRID-RSA4096-MLKEM768'; // Mixed environment
        }
        
        return 'RSA-4096-OAEP'; // Legacy devices only
    }
}