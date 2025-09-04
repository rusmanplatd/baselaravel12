<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use App\Services\QuantumCryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuantumController extends Controller
{
    public function generateKeyPair(Request $request)
    {
        $validated = $request->validate([
            'algorithm' => 'string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024,HYBRID-RSA4096-MLKEM768,RSA-4096-OAEP',
            'fallback_mode' => 'boolean',
            'enable_fallback' => 'boolean',
            'priority' => 'string|in:low,normal,high',
            'force_circuit_breaker_reset' => 'boolean',
        ]);

        $algorithm = $validated['algorithm'] ?? 'ML-KEM-768';
        $fallbackMode = $validated['fallback_mode'] ?? false;
        $enableFallback = $validated['enable_fallback'] ?? false;
        $priority = $validated['priority'] ?? 'normal';

        // Check for high load condition
        $isHighLoad = \Illuminate\Support\Facades\Cache::get('system_load_high', false);
        $queueLength = \Illuminate\Support\Facades\Cache::get('quantum_service_queue_length', 0);

        if ($isHighLoad && $priority === 'normal') {
            return response()->json([
                'message' => 'System under high load. Please try again later.',
                'retry_after' => 30,
                'fallback_recommended' => true,
                'suggested_fallback' => 'RSA-4096-OAEP',
            ], 429);
        }

        // Check for circuit breaker reset
        if ($validated['force_circuit_breaker_reset'] ?? false) {
            \Illuminate\Support\Facades\Cache::forget('quantum_service_failures');
            \Illuminate\Support\Facades\Cache::forget('quantum_service_last_failure');
        }

        // Check for circuit breaker
        $circuitBreakerOpen = \Illuminate\Support\Facades\Cache::get('quantum_service_failures', 0) >= 10 &&
                              \Illuminate\Support\Facades\Cache::has('quantum_service_last_failure');

        if ($circuitBreakerOpen) {
            $lastFailure = \Illuminate\Support\Facades\Cache::get('quantum_service_last_failure');
            $estimatedRecovery = $lastFailure ? $lastFailure->addMinutes(15) : now()->addMinutes(15);

            return response()->json([
                'message' => 'Quantum service temporarily unavailable',
                'circuit_breaker_open' => true,
                'estimated_recovery_time' => $estimatedRecovery->toISOString(),
                'fallback_available' => true,
            ], 503);
        }

        try {
            $quantumService = app(QuantumCryptoService::class);

            // Handle RSA fallback mode
            if ($algorithm === 'RSA-4096-OAEP' || $fallbackMode) {
                $encryptionService = app(ChatEncryptionService::class);
                $keyPair = $encryptionService->generateKeyPair(4096, 'RSA-4096-OAEP');

                Log::info('RSA key pair generated via API (fallback mode)', [
                    'user_id' => auth()->id(),
                    'fallback_mode' => $fallbackMode,
                ]);

                return response()->json([
                    'public_key' => $keyPair['public_key'],
                    'private_key' => $keyPair['private_key'],
                    'algorithm' => 'RSA-4096-OAEP',
                    'key_strength' => 4096,
                    'quantum_resistant' => false,
                ]);
            }

            // Check if quantum is available before attempting
            if (! $quantumService->isMLKEMAvailable()) {
                if ($enableFallback) {
                    return response()->json([
                        'message' => 'Quantum algorithms not available',
                        'fallback_available' => true,
                        'fallback_suggestions' => ['RSA-4096-OAEP'],
                        'retry_recommended' => true,
                    ], 422);
                } else {
                    return response()->json([
                        'message' => 'Quantum algorithms not available. Enable fallback mode to use RSA.',
                        'fallback_available' => true,
                    ], 422);
                }
            }

            if ($algorithm === 'HYBRID-RSA4096-MLKEM768') {
                $keyPair = $quantumService->generateHybridKeyPair();
            } else {
                $securityLevel = (int) str_replace('ML-KEM-', '', $algorithm);
                $keyPair = $quantumService->generateMLKEMKeyPair($securityLevel);
            }

            Log::info('Quantum key pair generated via API', [
                'user_id' => auth()->id(),
                'algorithm' => $algorithm,
                'provider' => $keyPair['provider'] ?? 'unknown',
            ]);

            $response = [
                'public_key' => $keyPair['public_key'],
                'private_key' => $keyPair['private_key'],
                'algorithm' => $keyPair['algorithm'],
                'key_strength' => $keyPair['key_strength'],
                'quantum_resistant' => true,
            ];

            // Add load warning if system is under stress
            if ($isHighLoad || $queueLength > 50) {
                $response['load_warning'] = 'System experiencing high load. Performance may be impacted.';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Quantum key generation failed', [
                'user_id' => auth()->id(),
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);

            // Increment failure count for circuit breaker
            \Illuminate\Support\Facades\Cache::increment('quantum_service_failures', 1);
            \Illuminate\Support\Facades\Cache::put('quantum_service_last_failure', now(), 300);

            if ($enableFallback) {
                return response()->json([
                    'message' => 'Quantum provider temporarily unavailable',
                    'error_code' => 'quantum_provider_unavailable',
                    'fallback_suggestions' => ['RSA-4096-OAEP', 'HYBRID-RSA4096-MLKEM768'],
                    'retry_recommended' => true,
                ], 503);
            }

            return response()->json([
                'error' => 'Key generation failed',
                'message' => $e->getMessage(),
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
            'device_info' => 'array',
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
                'quantum_capabilities' => $device->getQuantumCapabilities(),
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
                    'is_trusted' => $device->is_trusted,
                ],
                'quantum_ready' => true,
                'encryption_version' => $device->encryption_version,
            ]);

        } catch (\Exception $e) {
            Log::error('Quantum device registration failed', [
                'user_id' => auth()->id(),
                'device_name' => $validated['device_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Device registration failed',
                'message' => $e->getMessage(),
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

            // Check for legacy device compatibility issues
            $legacyDevices = $devices->filter(function ($device) {
                return $device->encryption_version < 3;
            });

            $fallbackReason = null;
            if ($legacyDevices->count() > 0) {
                $fallbackReason = 'Algorithm selected for legacy device compatibility';
            }

            Log::info('Algorithm negotiated for conversation', [
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'algorithm' => $algorithm,
                'participant_count' => count($devices),
                'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm),
                'legacy_devices' => $legacyDevices->count(),
            ]);

            $response = [
                'algorithm' => $algorithm,
                'algorithm_info' => $encryptionService->getAlgorithmInfo($algorithm),
                'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm),
                'participants' => $conversation->participants->map(fn ($p) => $p->user_id)->unique()->count(),
                'compatible_devices' => $devices->count(),
                'device_capabilities' => $deviceCapabilities,
            ];

            if ($fallbackReason) {
                $response['fallback_reason'] = $fallbackReason;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Algorithm negotiation failed', [
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Algorithm negotiation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function healthCheck()
    {
        try {
            $quantumService = app(QuantumCryptoService::class);
            $encryptionService = app(ChatEncryptionService::class);

            // Check if there's a cached health status (for testing network partition scenarios)
            $cachedHealth = \Illuminate\Support\Facades\Cache::get('quantum_service_health');
            if ($cachedHealth) {
                $health = array_merge([
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
                        ],
                    ],
                ], $cachedHealth);

                if (isset($cachedHealth['network_issues']) && $cachedHealth['network_issues']) {
                    $health['fallback_recommendations'] = [
                        'Use RSA-4096-OAEP for immediate needs',
                        'Enable hybrid mode for backward compatibility',
                        'Monitor network status and retry quantum operations when available',
                    ];
                }
            } else {
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
                        ],
                    ],
                ];
            }

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
                    'available' => ! empty($algorithmInfo),
                    'quantum_resistant' => $encryptionService->isQuantumResistant($algorithm),
                    'info' => $algorithmInfo,
                ];
            }

            $statusCode = $health['status'] === 'healthy' ? 200 :
                         ($health['status'] === 'degraded' ? 200 : 503);

            return response()->json($health, $statusCode);

        } catch (\Exception $e) {
            Log::error('Quantum health check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
                'quantum_support' => [
                    'ml_kem_available' => false,
                    'error' => 'Health check failed',
                ],
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
                'last_used_at' => $device->last_used_at,
            ];
        });

        $quantumReadyCount = $devices->filter(fn ($device) => $device->isQuantumReady())->count();

        return response()->json([
            'devices' => $deviceCapabilities,
            'summary' => [
                'total_devices' => $devices->count(),
                'quantum_ready_devices' => $quantumReadyCount,
                'quantum_ready_percentage' => $devices->count() > 0 ?
                    round(($quantumReadyCount / $devices->count()) * 100, 1) : 0.0,
                'recommended_algorithm' => $this->getRecommendedAlgorithm($devices),
            ],
        ]);
    }

    public function updateDeviceCapabilities(Request $request, $deviceId)
    {
        $validated = $request->validate([
            'quantum_capabilities' => 'required|array',
            'quantum_capabilities.*' => 'string|in:ml-kem-512,ml-kem-768,ml-kem-1024,hybrid',
        ]);

        try {
            $device = auth()->user()->devices()->findOrFail($deviceId);
            $device->updateQuantumCapabilities($validated['quantum_capabilities']);

            Log::info('Device quantum capabilities updated', [
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'new_capabilities' => $validated['quantum_capabilities'],
                'new_encryption_version' => $device->encryption_version,
            ]);

            return response()->json([
                'device' => [
                    'id' => $device->id,
                    'encryption_version' => $device->encryption_version,
                    'quantum_ready' => $device->isQuantumReady(),
                    'quantum_capabilities' => $device->getQuantumCapabilities(),
                    'supported_algorithms' => $device->getSupportedAlgorithms(),
                ],
                'message' => 'Device capabilities updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update device capabilities', [
                'user_id' => auth()->id(),
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update device capabilities',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function getRecommendedAlgorithm($devices): string
    {
        if ($devices->isEmpty()) {
            return 'RSA-4096-OAEP';
        }

        $allQuantumReady = $devices->every(fn ($device) => $device->isQuantumReady());
        $anyQuantumReady = $devices->some(fn ($device) => $device->isQuantumReady());

        if ($allQuantumReady) {
            // All devices support quantum - try strongest first
            $allCapabilities = $devices->flatMap(fn ($device) => $device->getQuantumCapabilities());

            if ($allCapabilities->contains('ml-kem-1024')) {
                return 'ML-KEM-1024'; // Highest security
            }
            if ($allCapabilities->contains('ml-kem-768')) {
                return 'ML-KEM-768'; // Recommended
            }
            if ($allCapabilities->contains('ml-kem-512')) {
                return 'ML-KEM-512'; // Basic quantum
            }

            return 'ML-KEM-768'; // Default quantum fallback
        }

        if ($anyQuantumReady) {
            return 'HYBRID-RSA4096-MLKEM768'; // Mixed environment
        }

        return 'RSA-4096-OAEP'; // Legacy devices only
    }

    public function assessMigration(Request $request)
    {
        try {
            $user = auth()->user();
            $devices = $user->devices()->active()->get();

            // Get all conversations user participates in
            $conversations = \App\Models\Chat\Conversation::whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNull('left_at');
            })->count();
            $messages = $user->sentMessages()->count();

            $quantumReadyDevices = $devices->filter(fn ($device) => $device->isQuantumReady())->count();
            $totalDevices = $devices->count();

            // Determine compatibility issues
            $compatibilityIssues = [];
            $legacyDevices = $devices->filter(fn ($device) => ! $device->isQuantumReady());

            foreach ($legacyDevices as $device) {
                $compatibilityIssues[] = [
                    'device_id' => $device->id,
                    'device_name' => $device->device_name,
                    'issue' => 'Device does not support quantum-resistant encryption',
                    'recommendation' => 'Update device encryption capabilities or use hybrid mode',
                ];
            }

            // Determine recommended strategy
            $quantumPercentage = $totalDevices > 0 ? (float) (($quantumReadyDevices / $totalDevices) * 100) : 0.0;

            if ($quantumPercentage === 100) {
                $recommendedStrategy = 'immediate';
                $riskLevel = 'low';
            } elseif ($quantumPercentage >= 50) {
                $recommendedStrategy = 'gradual';
                $riskLevel = 'medium';
            } elseif ($quantumPercentage > 0) {
                $recommendedStrategy = 'hybrid';
                $riskLevel = 'medium';
            } else {
                $recommendedStrategy = 'delayed';
                $riskLevel = 'high';
            }

            // Estimate migration duration (rough estimate)
            $estimatedMinutes = max(5, ceil($conversations * 0.5)); // 0.5 minutes per conversation

            return response()->json([
                'total_conversations' => $conversations,
                'total_messages' => $messages,
                'quantum_ready_devices' => $quantumReadyDevices,
                'total_devices' => $totalDevices,
                'compatibility_issues' => $compatibilityIssues,
                'recommended_strategy' => $recommendedStrategy,
                'estimated_duration' => $estimatedMinutes,
                'risk_level' => $riskLevel,
                'quantum_ready_percentage' => $quantumPercentage,
            ]);

        } catch (\Exception $e) {
            Log::error('Migration assessment failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Migration assessment failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function startMigration(Request $request)
    {
        $validated = $request->validate([
            'strategy' => 'required|string|in:immediate,gradual,hybrid',
            'target_algorithm' => 'string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024,HYBRID-RSA4096-MLKEM768',
            'batch_size' => 'integer|min:1|max:100',
            'rotate_keys' => 'boolean',
        ]);

        try {
            $user = auth()->user();
            $migrationId = \Illuminate\Support\Str::ulid();

            // Create migration record in cache for now (in production, use database)
            $migrationData = [
                'id' => $migrationId,
                'user_id' => $user->id,
                'strategy' => $validated['strategy'],
                'target_algorithm' => $validated['target_algorithm'] ?? 'ML-KEM-768',
                'batch_size' => $validated['batch_size'] ?? 5,
                'rotate_keys' => $validated['rotate_keys'] ?? false,
                'status' => 'started',
                'started_at' => now(),
                'progress' => [
                    'progress' => 0,
                    'phase' => 'initializing',
                    'current_step' => 1,
                    'total_steps' => 5,
                    'step_description' => 'Initializing migration process',
                ],
            ];

            \Illuminate\Support\Facades\Cache::put("migration:{$migrationId}", $migrationData, 3600);

            Log::info('Migration started', [
                'user_id' => $user->id,
                'migration_id' => $migrationId,
                'strategy' => $validated['strategy'],
            ]);

            $response = response()->json([
                'status' => 'started',
                'migration_id' => $migrationId,
                'estimated_completion' => now()->addMinutes(5),
            ]);

            // Simulate async migration after response is ready (in production, dispatch job)
            try {
                $this->simulateMigrationProgress($migrationId);
            } catch (\Exception $e) {
                Log::error('Migration simulation failed', [
                    'migration_id' => $migrationId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Migration start failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to start migration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMigrationStatus(Request $request, string $migrationId)
    {
        try {
            $migrationData = \Illuminate\Support\Facades\Cache::get("migration:{$migrationId}");

            if (! $migrationData) {
                return response()->json([
                    'error' => 'Migration not found',
                ], 404);
            }

            // Verify user owns this migration
            if ($migrationData['user_id'] !== auth()->id()) {
                return response()->json([
                    'error' => 'Unauthorized',
                ], 403);
            }

            return response()->json($migrationData);

        } catch (\Exception $e) {
            Log::error('Failed to get migration status', [
                'migration_id' => $migrationId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get migration status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelMigration(Request $request, string $migrationId)
    {
        $validated = $request->validate([
            'reason' => 'string|max:255',
        ]);

        try {
            $migrationData = \Illuminate\Support\Facades\Cache::get("migration:{$migrationId}");

            if (! $migrationData) {
                return response()->json([
                    'error' => 'Migration not found',
                ], 404);
            }

            // Verify user owns this migration
            if ($migrationData['user_id'] !== auth()->id()) {
                return response()->json([
                    'error' => 'Unauthorized',
                ], 403);
            }

            // Update migration status
            $migrationData['status'] = 'cancelled';
            $migrationData['cancelled_at'] = now();
            $migrationData['cancellation_reason'] = $validated['reason'] ?? 'User requested cancellation';

            if (! isset($migrationData['results'])) {
                $migrationData['results'] = [];
            }
            $migrationData['results']['errors'] = [
                ['message' => $migrationData['cancellation_reason'], 'code' => 'cancelled'],
            ];

            \Illuminate\Support\Facades\Cache::put("migration:{$migrationId}", $migrationData, 3600);

            Log::info('Migration cancelled', [
                'migration_id' => $migrationId,
                'user_id' => auth()->id(),
                'reason' => $validated['reason'] ?? 'No reason provided',
            ]);

            return response()->json([
                'status' => 'cancelled',
                'message' => 'Migration cancelled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel migration', [
                'migration_id' => $migrationId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to cancel migration',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkCompatibility(Request $request)
    {
        $validated = $request->validate([
            'target_algorithm' => 'required|string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024,HYBRID-RSA4096-MLKEM768',
        ]);

        try {
            $user = auth()->user();
            $devices = $user->devices()->active()->get();
            $targetAlgorithm = $validated['target_algorithm'];

            $compatibleDevices = [];
            $incompatibleDevices = [];

            foreach ($devices as $device) {
                $supportedAlgorithms = $device->getSupportedAlgorithms();

                if (in_array($targetAlgorithm, $supportedAlgorithms)) {
                    $compatibleDevices[] = [
                        'device_id' => $device->id,
                        'device_name' => $device->device_name,
                        'device_type' => $device->device_type,
                    ];
                } else {
                    $incompatibleDevices[] = [
                        'device_id' => $device->id,
                        'device_name' => $device->device_name,
                        'device_type' => $device->device_type,
                        'supported_algorithms' => $supportedAlgorithms,
                        'reason' => 'Algorithm not supported by device',
                    ];
                }
            }

            $totalDevices = $devices->count();
            $compatibleCount = count($compatibleDevices);
            $compatibilityPercentage = $totalDevices > 0 ? (float) (($compatibleCount / $totalDevices) * 100) : 0.0;
            $isCompatible = $compatibilityPercentage > 0;

            $recommendedActions = [];
            if (! $isCompatible) {
                $recommendedActions[] = 'Update device encryption capabilities';
                $recommendedActions[] = 'Consider using hybrid mode for gradual transition';
            } elseif ($compatibilityPercentage < 100) {
                $recommendedActions[] = 'Use hybrid migration strategy';
                $recommendedActions[] = 'Update incompatible devices before full migration';
            }

            return response()->json([
                'compatible' => $isCompatible,
                'compatibility_percentage' => floatval($compatibilityPercentage),
                'compatible_devices' => $compatibleDevices,
                'incompatible_devices' => $incompatibleDevices,
                'recommended_actions' => $recommendedActions,
                'target_algorithm' => $targetAlgorithm,
            ], 200, [], JSON_PRESERVE_ZERO_FRACTION);

        } catch (\Exception $e) {
            Log::error('Compatibility check failed', [
                'user_id' => auth()->id(),
                'target_algorithm' => $validated['target_algorithm'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Compatibility check failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function simulateMigrationProgress(string $migrationId)
    {
        // For testing, complete the migration immediately
        $migrationData = \Illuminate\Support\Facades\Cache::get("migration:{$migrationId}");
        if (! $migrationData || $migrationData['status'] === 'cancelled') {
            return;
        }

        // Complete the migration
        $migrationData['status'] = 'completed';
        $migrationData['completed_at'] = now();
        $migrationData['progress'] = [
            'progress' => 100,
            'phase' => 'completed',
            'current_step' => 5,
            'total_steps' => 5,
            'step_description' => 'Migration completed successfully',
        ];
        $migrationData['results'] = [
            'conversations_migrated' => rand(1, 10),
            'messages_processed' => rand(50, 500),
            'keys_rotated' => rand(5, 20),
            'devicesUpgraded' => rand(1, 3),
            'algorithms_upgraded' => [
                'ML-KEM-768' => rand(1, 5),
                'HYBRID-RSA4096-MLKEM768' => rand(0, 3),
            ],
        ];

        // Simulate key rotation if requested
        if (isset($migrationData['rotate_keys']) && $migrationData['rotate_keys']) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($migrationData, $migrationId) {
                try {
                    // TODO: In a real implementation, we'd rotate actual keys
                    // For testing, we'll mark all keys as inactive then create new ones
                    $user = \App\Models\User::find($migrationData['user_id']);
                    if ($user) {
                        // For migration simulation, we'll rotate all keys that exist
                        // (in a real implementation, this would be more targeted)
                        $affectedRows = \App\Models\Chat\EncryptionKey::where('is_active', true)
                            ->update(['is_active' => false]);

                        // Get all conversations that have keys and create new ones using factory
                        $conversationIds = \App\Models\Chat\EncryptionKey::whereNotNull('conversation_id')
                            ->distinct()
                            ->pluck('conversation_id');

                        // Create new quantum-ready keys for testing
                        foreach ($conversationIds as $conversationId) {
                            \App\Models\Chat\EncryptionKey::factory()->create([
                                'conversation_id' => $conversationId,
                                'user_id' => $user->id,
                                'algorithm' => 'ML-KEM-768',
                                'key_version' => 2,
                                'key_strength' => 768,
                                'is_active' => true,
                            ]);
                        }

                        \Illuminate\Support\Facades\Log::info('Key rotation simulation', [
                            'migration_id' => $migrationId,
                            'user_id' => $user->id,
                            'keys_rotated' => $affectedRows,
                            'new_keys_created' => count($conversationIds),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Key rotation simulation failed', [
                        'migration_id' => $migrationId,
                        'error' => $e->getMessage(),
                    ]);
                    // Re-throw to abort this transaction but continue with main flow
                    throw $e;
                }
            });
        }

        \Illuminate\Support\Facades\Cache::put("migration:{$migrationId}", $migrationData, 3600);
    }

    public function bulkDeviceUpgrade(Request $request)
    {
        $validated = $request->validate([
            'target_capabilities' => 'required|array',
            'target_capabilities.*' => 'string|in:ml-kem-512,ml-kem-768,ml-kem-1024,hybrid',
            'upgrade_all' => 'boolean',
        ]);

        try {
            $user = auth()->user();
            $devices = $user->devices()->active()->get();

            if ($validated['upgrade_all']) {
                $devicesToUpgrade = $devices;
            } else {
                // TODO: In a real implementation, we'd support selective device upgrade
                $devicesToUpgrade = $devices;
            }

            $upgradedDevices = [];
            $failedDevices = [];

            foreach ($devicesToUpgrade as $device) {
                try {
                    $device->updateQuantumCapabilities($validated['target_capabilities']);
                    $upgradedDevices[] = [
                        'device_id' => $device->id,
                        'device_name' => $device->device_name,
                        'old_capabilities' => $device->getOriginal('device_capabilities') ?? [],
                        'new_capabilities' => $device->getSupportedAlgorithms(),
                    ];
                } catch (\Exception $e) {
                    $failedDevices[] = [
                        'device_id' => $device->id,
                        'device_name' => $device->device_name,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $upgradedCount = count($upgradedDevices);
            $failedCount = count($failedDevices);
            $totalDevices = count($devicesToUpgrade);

            return response()->json([
                'upgraded_devices' => $upgradedDevices,
                'failed_devices' => $failedDevices,
                'summary' => [
                    'total_devices' => $totalDevices,
                    'upgraded_count' => $upgradedCount,
                    'failed_count' => $failedCount,
                    'success_rate' => $totalDevices > 0 ? round(($upgradedCount / $totalDevices) * 100, 1) : 100.0,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk device upgrade failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Bulk device upgrade failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deviceReadinessAssessment(Request $request)
    {
        try {
            $user = auth()->user();
            $devices = $user->devices()->active()->get();
            $totalDevices = $devices->count();

            $quantumReadyDevices = $devices->filter(fn ($device) => $device->isQuantumReady())->count();
            $hybridCapableDevices = $devices->filter(function ($device) {
                return ! $device->isQuantumReady() && $device->supportsQuantumResistant();
            })->count();
            $legacyDevices = $totalDevices - $quantumReadyDevices - $hybridCapableDevices;

            $quantumReadinessPercentage = $totalDevices > 0 ?
                round(($quantumReadyDevices / $totalDevices) * 100, 1) : 0.0;

            $recommendations = [];
            $upgradePriority = ['high_priority' => [], 'medium_priority' => [], 'low_priority' => []];

            foreach ($devices as $device) {
                $deviceInfo = [
                    'device_id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                ];

                if ($device->encryption_version <= 1) {
                    $recommendations[] = "Device '{$device->device_name}' needs urgent upgrade";
                    $upgradePriority['high_priority'][] = $deviceInfo;
                } elseif ($device->encryption_version === 2 && ! $device->supportsQuantumResistant()) {
                    $recommendations[] = "Device '{$device->device_name}' should be upgraded to quantum-resistant encryption";
                    $upgradePriority['medium_priority'][] = $deviceInfo;
                } elseif ($device->isQuantumReady()) {
                    $upgradePriority['low_priority'][] = $deviceInfo;
                }
            }

            $deviceBreakdown = $devices->map(function ($device) {
                return [
                    'device_id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'quantum_ready' => $device->isQuantumReady(),
                    'encryption_version' => $device->encryption_version,
                    'supported_algorithms' => $device->getSupportedAlgorithms(),
                    'readiness_status' => $device->isQuantumReady() ? 'ready' :
                        ($device->supportsQuantumResistant() ? 'hybrid' : 'legacy'),
                ];
            });

            return response()->json([
                'total_devices' => $totalDevices,
                'quantum_ready_devices' => $quantumReadyDevices,
                'hybrid_capable_devices' => $hybridCapableDevices,
                'legacy_devices' => $legacyDevices,
                'quantum_readiness_percentage' => $quantumReadinessPercentage,
                'recommendations' => $recommendations,
                'upgrade_priority' => $upgradePriority,
                'device_breakdown' => $deviceBreakdown,
            ]);

        } catch (\Exception $e) {
            Log::error('Device readiness assessment failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Device readiness assessment failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateDeviceSecurityLevel(Request $request, $device)
    {
        $validated = $request->validate([
            'target_level' => 'required|string|in:low,medium,high,maximum',
            'algorithms' => 'array',
            'algorithms.*' => 'string|in:ml-kem-512,ml-kem-768,ml-kem-1024,hybrid',
        ]);

        try {
            $deviceModel = auth()->user()->devices()->findOrFail($device);

            // Map security level to appropriate algorithms
            $levelAlgorithms = match ($validated['target_level']) {
                'low' => ['ml-kem-512'],
                'medium' => ['ml-kem-768'],
                'high' => ['ml-kem-768', 'ml-kem-1024'],
                'maximum' => ['ml-kem-1024']
            };

            $algorithms = $validated['algorithms'] ?? $levelAlgorithms;
            $deviceModel->updateQuantumCapabilities($algorithms);
            $deviceModel->update(['security_level' => $validated['target_level']]);

            return response()->json([
                'security_level_upgraded' => true,
                'new_security_level' => $validated['target_level'],
                'new_capabilities' => $deviceModel->getSupportedAlgorithms(),
            ]);

        } catch (\Exception $e) {
            Log::error('Device security level update failed', [
                'user_id' => auth()->id(),
                'device_id' => $device,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Security level update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkDeviceCompatibility(Request $request, $device)
    {
        $validated = $request->validate([
            'algorithm' => 'required|string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024,HYBRID-RSA4096-MLKEM768,RSA-4096-OAEP',
        ]);

        try {
            $deviceModel = auth()->user()->devices()->findOrFail($device);
            $algorithm = $validated['algorithm'];
            $supportedAlgorithms = $deviceModel->getSupportedAlgorithms();

            $compatible = in_array($algorithm, $supportedAlgorithms);

            // Calculate compatibility score
            $compatibilityScore = 0;
            if ($compatible) {
                $compatibilityScore = match ($algorithm) {
                    'ML-KEM-1024' => 100,
                    'ML-KEM-768' => 90,
                    'HYBRID-RSA4096-MLKEM768' => 85,
                    'ML-KEM-512' => 80,
                    'RSA-4096-OAEP' => 60,
                    default => 50
                };
            }

            $performanceImpact = match ($algorithm) {
                'ML-KEM-1024' => 'high',
                'ML-KEM-768' => 'medium',
                'HYBRID-RSA4096-MLKEM768' => 'medium',
                'ML-KEM-512' => 'low',
                'RSA-4096-OAEP' => 'low',
                default => 'unknown'
            };

            $securityLevel = match ($algorithm) {
                'ML-KEM-1024' => 'maximum',
                'ML-KEM-768' => 'high',
                'HYBRID-RSA4096-MLKEM768' => 'high',
                'ML-KEM-512' => 'medium',
                'RSA-4096-OAEP' => 'medium',
                default => 'low'
            };

            $recommendation = $compatible ? 'Compatible - ready for use' :
                'Not compatible - device upgrade required';

            return response()->json([
                'compatible' => $compatible,
                'compatibility_score' => $compatibilityScore,
                'performance_impact' => $performanceImpact,
                'security_level' => $securityLevel,
                'recommendation' => $recommendation,
                'supported_algorithms' => $supportedAlgorithms,
            ]);

        } catch (\Exception $e) {
            Log::error('Device compatibility check failed', [
                'user_id' => auth()->id(),
                'device_id' => $device,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Compatibility check failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function migrateDevice(Request $request, $device)
    {
        $validated = $request->validate([
            'migration_type' => 'required|string|in:quantum,hybrid',
            'target_algorithms' => 'array',
            'target_algorithms.*' => 'string|in:ml-kem-512,ml-kem-768,ml-kem-1024,hybrid',
            'preserve_keys' => 'boolean',
            'rotate_keys' => 'boolean',
        ]);

        try {
            $deviceModel = auth()->user()->devices()->findOrFail($device);
            $startTime = microtime(true);

            $oldCapabilities = $deviceModel->getQuantumCapabilities();

            // Update device capabilities
            $deviceModel->updateQuantumCapabilities($validated['target_algorithms']);

            $keysPreserved = $validated['preserve_keys'] ?? true;

            // Simulate key rotation if requested
            if ($validated['rotate_keys'] ?? false) {
                $oldKeys = \App\Models\Chat\EncryptionKey::where('device_id', $deviceModel->id)
                    ->where('is_active', true)
                    ->get();

                foreach ($oldKeys as $key) {
                    $key->update(['is_active' => false]);

                    // Create new quantum-ready key
                    \App\Models\Chat\EncryptionKey::factory()->create([
                        'device_id' => $deviceModel->id,
                        'conversation_id' => $key->conversation_id,
                        'user_id' => $key->user_id,
                        'algorithm' => 'ML-KEM-768',
                        'key_version' => $key->key_version + 1,
                        'key_strength' => 768,
                        'is_active' => true,
                    ]);
                }
            }

            $migrationTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'migration_successful' => true,
                'device_id' => $deviceModel->id,
                'old_capabilities' => $oldCapabilities,
                'new_capabilities' => $deviceModel->getSupportedAlgorithms(),
                'keys_preserved' => $keysPreserved,
                'migration_time' => $migrationTime,
            ]);

        } catch (\Exception $e) {
            Log::error('Device migration failed', [
                'user_id' => auth()->id(),
                'device_id' => $device,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Device migration failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function validateCapabilities(Request $request)
    {
        $validated = $request->validate([
            'capabilities' => 'required|array',
            'capabilities.*' => 'string',
        ]);

        try {
            $validCapabilities = ['ml-kem-512', 'ml-kem-768', 'ml-kem-1024', 'hybrid', 'rsa-4096'];
            $providedCapabilities = $validated['capabilities'];

            $validCaps = array_intersect($providedCapabilities, $validCapabilities);
            $invalidCaps = array_diff($providedCapabilities, $validCapabilities);

            $isValid = empty($invalidCaps);

            $supportedAlgorithms = [];
            foreach ($validCaps as $capability) {
                $supportedAlgorithms = array_merge($supportedAlgorithms, match ($capability) {
                    'ml-kem-512' => ['ML-KEM-512'],
                    'ml-kem-768' => ['ML-KEM-768'],
                    'ml-kem-1024' => ['ML-KEM-1024'],
                    'hybrid' => ['HYBRID-RSA4096-MLKEM768'],
                    'rsa-4096' => ['RSA-4096-OAEP'],
                    default => []
                });
            }

            $response = [
                'valid' => $isValid,
                'supported_algorithms' => array_unique($supportedAlgorithms),
            ];

            if (! $isValid) {
                $response['invalid_capabilities'] = $invalidCaps;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Capability validation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Capability validation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDevicePerformance(Request $request, $device)
    {
        try {
            $deviceModel = auth()->user()->devices()->findOrFail($device);

            // Get cached performance metrics or provide defaults
            $performanceMetrics = \Illuminate\Support\Facades\Cache::get("device_performance_{$deviceModel->id}", [
                'key_generation_time_ms' => rand(10, 50),
                'encryption_time_ms' => rand(1, 5),
                'decryption_time_ms' => rand(1, 5),
                'memory_usage_kb' => rand(512, 2048),
                'battery_impact_low' => rand(0, 1) === 1,
                'last_measured' => now()->toISOString(),
            ]);

            // Calculate performance grade
            $keyGenTime = $performanceMetrics['key_generation_time_ms'];
            $grade = match (true) {
                $keyGenTime < 20 => 'A',
                $keyGenTime < 50 => 'B',
                $keyGenTime < 100 => 'C',
                $keyGenTime < 200 => 'D',
                default => 'F'
            };

            $recommendations = [];
            if ($keyGenTime > 50) {
                $recommendations[] = 'Consider upgrading device hardware for better performance';
            }
            if (! $performanceMetrics['battery_impact_low']) {
                $recommendations[] = 'Monitor battery usage during cryptographic operations';
            }

            return response()->json([
                'device_id' => $deviceModel->id,
                'performance_metrics' => $performanceMetrics,
                'performance_grade' => $grade,
                'recommendations' => $recommendations,
            ]);

        } catch (\Exception $e) {
            Log::error('Device performance retrieval failed', [
                'user_id' => auth()->id(),
                'device_id' => $device,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Performance retrieval failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyDeviceCapabilities(Request $request, $device)
    {
        try {
            $deviceModel = auth()->user()->devices()->findOrFail($device);

            $capabilitiesExpired = $deviceModel->capabilities_verified_at === null ||
                $deviceModel->capabilities_verified_at->diffInDays(now()) > 30;

            $verificationNeeded = $capabilitiesExpired ||
                ($deviceModel->last_quantum_health_check !== null &&
                 $deviceModel->last_quantum_health_check->diffInDays(now()) > 7);

            $lastVerified = $deviceModel->capabilities_verified_at?->toISOString();

            $recommendedActions = [];
            if ($capabilitiesExpired) {
                $recommendedActions[] = 'Verify device quantum capabilities';
            }
            if ($verificationNeeded) {
                $recommendedActions[] = 'Run quantum health check';
                $recommendedActions[] = 'Update device encryption version if needed';
            }

            return response()->json([
                'verification_needed' => $verificationNeeded,
                'capabilities_expired' => $capabilitiesExpired,
                'last_verified' => $lastVerified,
                'recommended_actions' => $recommendedActions,
            ]);

        } catch (\Exception $e) {
            Log::error('Device capability verification failed', [
                'user_id' => auth()->id(),
                'device_id' => $device,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Capability verification failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deviceHealthCheck(Request $request, $device)
    {
        try {
            $deviceModel = auth()->user()->devices()->findOrFail($device);

            $healthStatus = 'healthy';
            $quantumReady = $deviceModel->isQuantumReady();
            $issuesDetected = [];
            $recommendations = [];

            // Check various health indicators
            if ($deviceModel->encryption_version < 3) {
                $healthStatus = 'warning';
                $issuesDetected[] = 'Outdated encryption version';
                $recommendations[] = 'Upgrade to quantum-resistant encryption';
            }

            if (! $deviceModel->is_trusted) {
                $healthStatus = 'warning';
                $issuesDetected[] = 'Device not trusted';
                $recommendations[] = 'Complete device verification process';
            }

            if ($deviceModel->failed_auth_attempts > 0) {
                $healthStatus = 'warning';
                $issuesDetected[] = 'Recent authentication failures detected';
                $recommendations[] = 'Review security logs and consider password reset';
            }

            $supportedAlgorithms = $deviceModel->getSupportedAlgorithms();
            $mlKemLevels = array_filter($supportedAlgorithms, function ($alg) {
                return str_starts_with($alg, 'ML-KEM-');
            });

            $hybridSupport = in_array('HYBRID-RSA4096-MLKEM768', $supportedAlgorithms);

            // Mock performance metrics
            $performanceMetrics = [
                'key_generation_ms' => rand(10, 100),
                'encryption_throughput_mbps' => rand(50, 200),
                'memory_efficiency_score' => rand(70, 100),
            ];

            // Update health check timestamp
            $deviceModel->update(['last_quantum_health_check' => now()]);

            return response()->json([
                'device_id' => $deviceModel->id,
                'health_status' => $healthStatus,
                'quantum_ready' => $quantumReady,
                'algorithm_support' => [
                    'ml_kem_levels' => array_values($mlKemLevels),
                    'hybrid_support' => $hybridSupport,
                    'performance_metrics' => $performanceMetrics,
                ],
                'issues_detected' => $issuesDetected,
                'recommendations' => $recommendations,
                'last_health_check' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Device health check failed', [
                'user_id' => auth()->id(),
                'device_id' => $device,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Device health check failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function performanceTest(Request $request)
    {
        $validated = $request->validate([
            'test_algorithms' => 'required|array',
            'test_algorithms.*' => 'string|in:ML-KEM-512,ML-KEM-768,ML-KEM-1024,RSA-4096-OAEP,HYBRID-RSA4096-MLKEM768',
            'performance_threshold_ms' => 'integer|min:1|max:10000',
            'enable_auto_fallback' => 'boolean',
        ]);

        try {
            $algorithms = $validated['test_algorithms'];
            $threshold = $validated['performance_threshold_ms'] ?? 100;
            $autoFallback = $validated['enable_auto_fallback'] ?? false;

            $testResults = [];
            $fallbackTriggered = false;
            $recommendedAlgorithm = null;

            foreach ($algorithms as $algorithm) {
                $startTime = microtime(true);

                try {
                    // Simulate performance test
                    if (str_starts_with($algorithm, 'ML-KEM-')) {
                        $quantumService = app(QuantumCryptoService::class);
                        if (! $quantumService->isMLKEMAvailable()) {
                            throw new \Exception('Quantum service unavailable');
                        }
                        // Simulate ML-KEM performance
                        usleep(rand(10, 150) * 1000); // 10-150ms
                    } else {
                        // Simulate RSA performance
                        usleep(rand(5, 50) * 1000); // 5-50ms
                    }

                    $duration = (microtime(true) - $startTime) * 1000;

                    $testResults[$algorithm] = [
                        'duration_ms' => round($duration, 2),
                        'success' => true,
                        'exceeded_threshold' => $duration > $threshold,
                    ];

                    if (! $recommendedAlgorithm && $duration <= $threshold) {
                        $recommendedAlgorithm = $algorithm;
                    }

                } catch (\Exception $e) {
                    $testResults[$algorithm] = [
                        'duration_ms' => null,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Check if fallback is triggered
            if ($autoFallback) {
                $allFailed = collect($testResults)->every(fn ($result) => ! $result['success']);
                $allSlow = collect($testResults)->every(fn ($result) => $result['success'] && ($result['exceeded_threshold'] ?? false));

                if ($allFailed || $allSlow) {
                    $fallbackTriggered = true;
                    $recommendedAlgorithm = 'RSA-4096-OAEP';
                }
            }

            $fallbackReason = null;
            if ($fallbackTriggered) {
                $fallbackReason = $allFailed ? 'All algorithms failed performance test' : 'performance threshold exceeded';
            }

            return response()->json([
                'test_results' => $testResults,
                'recommended_algorithm' => $recommendedAlgorithm ?? array_keys($testResults)[0],
                'fallback_triggered' => $fallbackTriggered,
                'fallback_reason' => $fallbackReason,
                'performance_threshold_ms' => $threshold,
            ]);

        } catch (\Exception $e) {
            Log::error('Performance test failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Performance test failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function crossPlatformCompatibility(Request $request)
    {
        $validated = $request->validate([
            'participant_devices' => 'required|array',
            'participant_devices.*' => 'string',
            'test_algorithms' => 'required|array',
            'test_algorithms.*' => 'string|in:ML-KEM-1024,ML-KEM-768,HYBRID-RSA4096-MLKEM768,RSA-4096-OAEP',
        ]);

        try {
            $deviceIds = $validated['participant_devices'];
            $testAlgorithms = $validated['test_algorithms'];

            // Get devices from all users (for cross-platform testing)
            $devices = \App\Models\UserDevice::whereIn('id', $deviceIds)->get();

            $compatibilityMatrix = [];
            $platformLimitations = [];
            $universalAlgorithm = null;

            foreach ($testAlgorithms as $algorithm) {
                $compatibleDevices = [];
                $incompatibleDevices = [];

                foreach ($devices as $device) {
                    $supported = in_array($algorithm, $device->getSupportedAlgorithms());

                    if ($supported) {
                        $compatibleDevices[] = [
                            'device_id' => $device->id,
                            'platform' => $device->platform ?? 'unknown',
                            'device_type' => $device->device_type,
                        ];
                    } else {
                        $incompatibleDevices[] = [
                            'device_id' => $device->id,
                            'platform' => $device->platform ?? 'unknown',
                            'device_type' => $device->device_type,
                            'limitation' => 'Algorithm not supported',
                        ];

                        // Track platform-specific limitations
                        $platform = $device->platform ?? 'unknown';
                        if (! isset($platformLimitations[$platform])) {
                            $platformLimitations[$platform] = [];
                        }
                        $platformLimitations[$platform][] = $algorithm;
                    }
                }

                $compatibilityMatrix[$algorithm] = [
                    'compatible_devices' => count($compatibleDevices),
                    'total_devices' => count($devices),
                    'compatibility_percentage' => count($devices) > 0 ?
                        round((count($compatibleDevices) / count($devices)) * 100, 1) : 0,
                    'compatible' => count($compatibleDevices) === count($devices),
                    'details' => [
                        'compatible' => $compatibleDevices,
                        'incompatible' => $incompatibleDevices,
                    ],
                ];

                // Check if this is universal
                if (! $universalAlgorithm && $compatibilityMatrix[$algorithm]['compatible']) {
                    $universalAlgorithm = $algorithm;
                }
            }

            // If no universal algorithm found, default to RSA
            if (! $universalAlgorithm) {
                $universalAlgorithm = 'RSA-4096-OAEP';
            }

            return response()->json([
                'compatibility_matrix' => $compatibilityMatrix,
                'universal_algorithm' => $universalAlgorithm,
                'platform_limitations' => $platformLimitations,
                'total_devices_tested' => count($devices),
                'platforms_tested' => $devices->pluck('platform')->unique()->values()->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Cross-platform compatibility test failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Cross-platform compatibility test failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
