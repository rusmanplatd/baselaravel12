<?php

namespace App\Http\Controllers\Api\Quantum;

use App\Http\Controllers\Controller;
use App\Services\Quantum\QuantumKeyDistributionService;
use App\Services\Quantum\QuantumThreatDetectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class QuantumKeyDistributionController extends Controller
{
    public function __construct(
        private QuantumKeyDistributionService $qkdService,
        private QuantumThreatDetectionService $threatService
    ) {}

    public function initiateBB84Protocol(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => 'required|string|max:255',
            'key_length' => 'integer|min:128|max:1024',
            'participant_id' => 'required|string|max:255',
            'quantum_capabilities' => 'required|array',
            'security_level' => 'required|string|in:level_5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $channelId = $request->input('channel_id');
            $keyLength = $request->input('key_length', 256);
            $participantId = $request->input('participant_id');
            $capabilities = $request->input('quantum_capabilities');

            // Validate quantum capabilities
            $this->validateQuantumCapabilities($capabilities);

            // Log initiation attempt
            $this->threatService->logQuantumEvent([
                'event_type' => 'bb84_protocol_initiation',
                'user_id' => $userId,
                'channel_id' => $channelId,
                'participant_id' => $participantId,
                'key_length' => $keyLength,
                'quantum_capabilities' => $capabilities,
                'timestamp' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Initialize BB84 protocol
            $sessionData = $this->qkdService->initiateBB84Protocol(
                $channelId,
                $keyLength,
                $userId,
                $participantId,
                $capabilities
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionData['session_id'],
                    'quantum_states' => $sessionData['quantum_states'],
                    'measurement_bases' => $sessionData['measurement_bases'],
                    'phase' => $sessionData['phase'],
                    'expected_qber' => $sessionData['expected_qber'],
                    'security_parameters' => $sessionData['security_parameters']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('BB84 protocol initiation failed', [
                'user_id' => Auth::id(),
                'channel_id' => $request->input('channel_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->threatService->logQuantumEvent([
                'event_type' => 'bb84_protocol_initiation_failed',
                'user_id' => Auth::id(),
                'channel_id' => $request->input('channel_id'),
                'error' => $e->getMessage(),
                'severity' => 'high'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate BB84 protocol',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function performBasisReconciliation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'measurement_results' => 'required|array',
            'basis_choices' => 'required|array',
            'quantum_signature' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $sessionId = $request->input('session_id');
            $measurementResults = $request->input('measurement_results');
            $basisChoices = $request->input('basis_choices');
            $quantumSignature = $request->input('quantum_signature');

            // Verify quantum signature
            if (!$this->qkdService->verifyQuantumSignature($sessionId, $quantumSignature, $userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid quantum signature'
                ], 403);
            }

            // Perform basis reconciliation
            $reconciliationData = $this->qkdService->performBasisReconciliation(
                $sessionId,
                $measurementResults,
                $basisChoices,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'bb84_basis_reconciliation',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'matching_bases' => $reconciliationData['matching_bases_count'],
                'sifted_key_length' => $reconciliationData['sifted_key_length']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'matching_indices' => $reconciliationData['matching_indices'],
                    'sifted_key_length' => $reconciliationData['sifted_key_length'],
                    'reconciliation_efficiency' => $reconciliationData['efficiency'],
                    'next_phase' => 'error_detection'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Basis reconciliation failed', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Basis reconciliation failed'
            ], 500);
        }
    }

    public function performErrorDetection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'test_subset' => 'required|array',
            'test_results' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $sessionId = $request->input('session_id');
            $testSubset = $request->input('test_subset');
            $testResults = $request->input('test_results');

            // Perform error detection and QBER calculation
            $errorData = $this->qkdService->performErrorDetection(
                $sessionId,
                $testSubset,
                $testResults,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'bb84_error_detection',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'qber' => $errorData['qber'],
                'errors_detected' => $errorData['error_count'],
                'security_threshold_met' => $errorData['security_threshold_met']
            ]);

            // Check if QBER is acceptable
            if (!$errorData['security_threshold_met']) {
                $this->threatService->logQuantumEvent([
                    'event_type' => 'bb84_security_threshold_exceeded',
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'qber' => $errorData['qber'],
                    'threshold' => $errorData['threshold'],
                    'severity' => 'critical'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Security threshold exceeded',
                    'data' => [
                        'qber' => $errorData['qber'],
                        'threshold' => $errorData['threshold'],
                        'recommendation' => 'Abort protocol and investigate potential eavesdropping'
                    ]
                ], 406);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'qber' => $errorData['qber'],
                    'error_count' => $errorData['error_count'],
                    'remaining_key_length' => $errorData['remaining_key_length'],
                    'security_parameter' => $errorData['security_parameter'],
                    'next_phase' => 'privacy_amplification'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error detection failed', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error detection failed'
            ], 500);
        }
    }

    public function performPrivacyAmplification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'target_key_length' => 'required|integer|min:128|max:512',
            'hash_function_choice' => 'required|string|in:sha3,blake2,shake256'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $sessionId = $request->input('session_id');
            $targetKeyLength = $request->input('target_key_length');
            $hashFunction = $request->input('hash_function_choice');

            // Perform privacy amplification
            $amplificationData = $this->qkdService->performPrivacyAmplification(
                $sessionId,
                $targetKeyLength,
                $hashFunction,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'bb84_privacy_amplification',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'target_key_length' => $targetKeyLength,
                'final_key_length' => $amplificationData['final_key_length'],
                'hash_function' => $hashFunction,
                'security_level_achieved' => $amplificationData['security_level']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'key_id' => $amplificationData['key_id'],
                    'final_key_length' => $amplificationData['final_key_length'],
                    'security_level' => $amplificationData['security_level'],
                    'key_derivation_info' => $amplificationData['derivation_info'],
                    'protocol_completed' => true,
                    'authentication_data' => $amplificationData['auth_data']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Privacy amplification failed', [
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Privacy amplification failed'
            ], 500);
        }
    }

    public function performBellTest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entangled_pair_id' => 'required|string',
            'measurement_settings' => 'required|array',
            'test_type' => 'required|string|in:chsh,full_bell'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $pairId = $request->input('entangled_pair_id');
            $measurementSettings = $request->input('measurement_settings');
            $testType = $request->input('test_type');

            // Perform Bell inequality test
            $bellTestData = $this->qkdService->performBellInequalityTest(
                $pairId,
                $measurementSettings,
                $testType,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'bell_inequality_test',
                'user_id' => $userId,
                'entangled_pair_id' => $pairId,
                'test_type' => $testType,
                'bell_value' => $bellTestData['bell_value'],
                'threshold' => $bellTestData['threshold'],
                'violation_detected' => $bellTestData['violation_detected'],
                'quantum_correlation_verified' => $bellTestData['quantum_verified']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'bell_value' => $bellTestData['bell_value'],
                    'threshold' => $bellTestData['threshold'],
                    'violation_detected' => $bellTestData['violation_detected'],
                    'quantum_correlation_verified' => $bellTestData['quantum_verified'],
                    'correlation_coefficients' => $bellTestData['correlations'],
                    'statistical_significance' => $bellTestData['p_value'],
                    'entanglement_fidelity' => $bellTestData['fidelity']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bell test failed', [
                'entangled_pair_id' => $request->input('entangled_pair_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bell inequality test failed'
            ], 500);
        }
    }

    public function generateQuantumRandomness(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'length' => 'required|integer|min:32|max:1024',
            'entropy_source' => 'required|string|in:quantum_hardware,quantum_simulation,hybrid',
            'format' => 'string|in:hex,base64,binary'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $length = $request->input('length');
            $entropySource = $request->input('entropy_source');
            $format = $request->input('format', 'hex');

            // Generate quantum random data
            $randomData = $this->qkdService->generateQuantumRandomness(
                $length,
                $entropySource,
                $format,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'quantum_randomness_generated',
                'user_id' => $userId,
                'length' => $length,
                'entropy_source' => $entropySource,
                'format' => $format,
                'entropy_quality' => $randomData['entropy_quality']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'random_data' => $randomData['data'],
                    'entropy_quality' => $randomData['entropy_quality'],
                    'generation_method' => $randomData['method'],
                    'statistical_tests_passed' => $randomData['tests_passed'],
                    'generation_timestamp' => $randomData['timestamp']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quantum randomness generation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Quantum randomness generation failed'
            ], 500);
        }
    }

    public function getSessionStatus(Request $request, string $sessionId): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Get session status
            $status = $this->qkdService->getSessionStatus($sessionId, $userId);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get session status', [
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session status'
            ], 500);
        }
    }

    public function abortSession(Request $request, string $sessionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $reason = $request->input('reason');

            // Abort session
            $this->qkdService->abortSession($sessionId, $userId, $reason);

            $this->threatService->logQuantumEvent([
                'event_type' => 'bb84_session_aborted',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'reason' => $reason,
                'severity' => 'warning'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session aborted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to abort session', [
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to abort session'
            ], 500);
        }
    }

    private function validateQuantumCapabilities(array $capabilities): void
    {
        $required = [
            'quantum_hardware_available',
            'true_randomness_source', 
            'photon_detection_capability',
            'basis_measurement_precision',
            'quantum_channel_security'
        ];

        foreach ($required as $capability) {
            if (!isset($capabilities[$capability])) {
                throw new \InvalidArgumentException("Missing required quantum capability: {$capability}");
            }
        }

        // Validate minimum requirements
        if (!$capabilities['quantum_hardware_available'] && !$capabilities['quantum_simulation_fallback']) {
            throw new \InvalidArgumentException('No quantum capability available');
        }
    }
}