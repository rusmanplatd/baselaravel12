<?php

namespace App\Http\Controllers\Api\Quantum;

use App\Http\Controllers\Controller;
use App\Services\Quantum\QuantumSMPCService;
use App\Services\Quantum\QuantumThreatDetectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class QuantumSMPCController extends Controller
{
    public function __construct(
        private QuantumSMPCService $smpcService,
        private QuantumThreatDetectionService $threatService
    ) {}

    public function registerParticipant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_fingerprint' => 'required|string|max:255',
            'quantum_capabilities' => 'required|array',
            'quantum_capabilities.supports_quantum_key_gen' => 'required|boolean',
            'quantum_capabilities.supports_quantum_signing' => 'required|boolean',
            'quantum_capabilities.supports_quantum_consensus' => 'required|boolean',
            'quantum_capabilities.quantum_random_source' => 'required|boolean',
            'quantum_capabilities.hsm_available' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $deviceFingerprint = $request->input('device_fingerprint');
            $quantumCapabilities = $request->input('quantum_capabilities');

            // Register participant
            $participant = $this->smpcService->registerParticipant(
                $userId,
                $deviceFingerprint,
                $quantumCapabilities
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'smpc_participant_registered',
                'user_id' => $userId,
                'device_fingerprint' => $deviceFingerprint,
                'quantum_capabilities' => $quantumCapabilities,
                'trust_level' => $participant['trust_level'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'participant_id' => $participant['participant_id'],
                    'public_key' => base64_encode($participant['public_key']),
                    'quantum_proof' => base64_encode($participant['quantum_proof']),
                    'trust_level' => $participant['trust_level'],
                    'registration_time' => $participant['last_activity']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('SMPC participant registration failed', [
                'user_id' => Auth::id(),
                'device_fingerprint' => $request->input('device_fingerprint'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Participant registration failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function initiateThresholdSigning(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'participant_ids' => 'required|array|min:2|max:20',
            'participant_ids.*' => 'string|exists:quantum_smpc_participants,participant_id',
            'message_hash' => 'required|string',
            'threshold' => 'required|integer|min:1',
            'signing_algorithm' => 'string|in:ml_dsa_87,slh_dsa_sha2_256s'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $participantIds = $request->input('participant_ids');
            $messageHash = hex2bin($request->input('message_hash'));
            $threshold = $request->input('threshold');
            $algorithm = $request->input('signing_algorithm', 'ml_dsa_87');

            if ($threshold > count($participantIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Threshold cannot exceed number of participants'
                ], 422);
            }

            // Initiate threshold signing protocol
            $sessionId = $this->smpcService->initiateThresholdSigning(
                $participantIds,
                $messageHash,
                $threshold,
                $algorithm,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'threshold_signing_initiated',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'participant_count' => count($participantIds),
                'threshold' => $threshold,
                'algorithm' => $algorithm
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'participant_count' => count($participantIds),
                    'threshold' => $threshold,
                    'algorithm' => $algorithm,
                    'status' => 'initializing',
                    'expected_rounds' => 3
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Threshold signing initiation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate threshold signing'
            ], 500);
        }
    }

    public function contributePartialSignature(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:quantum_smpc_sessions,session_id',
            'partial_signature' => 'required|string',
            'signature_proof' => 'required|string'
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
            $partialSignature = hex2bin($request->input('partial_signature'));
            $signatureProof = hex2bin($request->input('signature_proof'));

            // Contribute partial signature
            $result = $this->smpcService->contributePartialSignature(
                $sessionId,
                $userId,
                $partialSignature,
                $signatureProof
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'partial_signature_contributed',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'accepted' => $result['accepted'],
                'signatures_collected' => $result['signatures_collected']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'accepted' => $result['accepted'],
                    'signatures_collected' => $result['signatures_collected'],
                    'threshold' => $result['threshold'],
                    'session_complete' => $result['signatures_collected'] >= $result['threshold'],
                    'final_signature' => $result['final_signature'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Partial signature contribution failed', [
                'user_id' => Auth::id(),
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to contribute partial signature'
            ], 500);
        }
    }

    public function initiateMultiPartyKeyGeneration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'participant_ids' => 'required|array|min:2|max:10',
            'participant_ids.*' => 'string|exists:quantum_smpc_participants,participant_id',
            'key_type' => 'required|string|in:ML-KEM-1024,ML-DSA-87,SLH-DSA-SHA2-256s',
            'key_usage' => 'required|string|in:encryption,signing,key_agreement'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $participantIds = $request->input('participant_ids');
            $keyType = $request->input('key_type');
            $keyUsage = $request->input('key_usage');

            // Initiate multiparty key generation
            $sessionId = $this->smpcService->initiateMultiPartyKeyGeneration(
                $participantIds,
                $keyType,
                $keyUsage,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'multiparty_keygen_initiated',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'participant_count' => count($participantIds),
                'key_type' => $keyType,
                'key_usage' => $keyUsage
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'participant_count' => count($participantIds),
                    'key_type' => $keyType,
                    'key_usage' => $keyUsage,
                    'status' => 'initializing',
                    'expected_rounds' => 4
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Multiparty key generation initiation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate multiparty key generation'
            ], 500);
        }
    }

    public function contributeKeyShare(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:quantum_smpc_sessions,session_id',
            'key_share' => 'required|string',
            'public_key_component' => 'required|string',
            'proof_of_knowledge' => 'required|string'
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
            $keyShare = hex2bin($request->input('key_share'));
            $publicKeyComponent = hex2bin($request->input('public_key_component'));
            $proofOfKnowledge = hex2bin($request->input('proof_of_knowledge'));

            // Contribute key share
            $result = $this->smpcService->contributeKeyShare(
                $sessionId,
                $userId,
                $keyShare,
                $publicKeyComponent,
                $proofOfKnowledge
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'key_share_contributed',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'accepted' => $result['accepted'],
                'contributions_received' => $result['contributions_received']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'accepted' => $result['accepted'],
                    'contributions_received' => $result['contributions_received'],
                    'total_required' => $result['total_required'],
                    'session_complete' => $result['contributions_received'] >= $result['total_required'],
                    'final_public_key' => $result['final_public_key'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Key share contribution failed', [
                'user_id' => Auth::id(),
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to contribute key share'
            ], 500);
        }
    }

    public function initiateSecureVoting(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'voting_question' => 'required|string|max:500',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'string|max:100',
            'eligible_voters' => 'required|array|min:1|max:100',
            'eligible_voters.*' => 'string|exists:quantum_smpc_participants,participant_id',
            'voting_deadline' => 'required|date|after:now',
            'anonymous_voting' => 'boolean',
            'verifiable_results' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $votingQuestion = $request->input('voting_question');
            $options = $request->input('options');
            $eligibleVoters = $request->input('eligible_voters');
            $votingDeadline = \Carbon\Carbon::parse($request->input('voting_deadline'));
            $anonymous = $request->boolean('anonymous_voting', true);
            $verifiable = $request->boolean('verifiable_results', true);

            // Initiate secure voting
            $sessionId = $this->smpcService->initiateSecureVoting(
                $votingQuestion,
                $options,
                $eligibleVoters,
                $votingDeadline,
                $anonymous,
                $verifiable,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'secure_voting_initiated',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'voter_count' => count($eligibleVoters),
                'option_count' => count($options),
                'anonymous' => $anonymous,
                'verifiable' => $verifiable
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'voting_question' => $votingQuestion,
                    'options' => $options,
                    'eligible_voters' => count($eligibleVoters),
                    'voting_deadline' => $votingDeadline,
                    'anonymous_voting' => $anonymous,
                    'verifiable_results' => $verifiable,
                    'status' => 'active'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Secure voting initiation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate secure voting'
            ], 500);
        }
    }

    public function castSecureVote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:quantum_smpc_sessions,session_id',
            'selected_option' => 'required|integer|min:0',
            'voter_proof' => 'required|string',
            'vote_commitment' => 'required|string'
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
            $selectedOption = $request->input('selected_option');
            $voterProof = hex2bin($request->input('voter_proof'));
            $voteCommitment = hex2bin($request->input('vote_commitment'));

            // Cast secure vote
            $result = $this->smpcService->castSecureVote(
                $sessionId,
                $userId,
                $selectedOption,
                $voterProof,
                $voteCommitment
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'secure_vote_cast',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'accepted' => $result['accepted'],
                'votes_cast' => $result['votes_cast']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'vote_accepted' => $result['accepted'],
                    'votes_cast' => $result['votes_cast'],
                    'total_voters' => $result['total_voters'],
                    'vote_receipt' => $result['vote_receipt'] ?? null,
                    'voting_complete' => $result['votes_cast'] >= $result['total_voters']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Secure vote casting failed', [
                'user_id' => Auth::id(),
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cast secure vote'
            ], 500);
        }
    }

    public function initiateQuantumConsensus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'participant_ids' => 'required|array|min:3|max:15',
            'participant_ids.*' => 'string|exists:quantum_smpc_participants,participant_id',
            'proposed_value' => 'required|string',
            'consensus_algorithm' => 'string|in:quantum_byzantine,quantum_pbft,bell_consensus',
            'fault_tolerance' => 'integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $participantIds = $request->input('participant_ids');
            $proposedValue = hex2bin($request->input('proposed_value'));
            $algorithm = $request->input('consensus_algorithm', 'quantum_byzantine');
            $faultTolerance = $request->input('fault_tolerance', 1);

            // Initiate quantum consensus
            $sessionId = $this->smpcService->initiateQuantumConsensus(
                $participantIds,
                $proposedValue,
                $algorithm,
                $faultTolerance,
                $userId
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'quantum_consensus_initiated',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'participant_count' => count($participantIds),
                'algorithm' => $algorithm,
                'fault_tolerance' => $faultTolerance
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'participant_count' => count($participantIds),
                    'consensus_algorithm' => $algorithm,
                    'fault_tolerance' => $faultTolerance,
                    'byzantine_threshold' => ceil(count($participantIds) * 2 / 3),
                    'status' => 'initializing',
                    'expected_rounds' => 5
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quantum consensus initiation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate quantum consensus'
            ], 500);
        }
    }

    public function contributeQuantumMeasurement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:quantum_smpc_sessions,session_id',
            'quantum_state' => 'required|string',
            'measurement_basis' => 'required|string',
            'entanglement_proof' => 'required|string'
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
            $quantumState = hex2bin($request->input('quantum_state'));
            $measurementBasis = hex2bin($request->input('measurement_basis'));
            $entanglementProof = hex2bin($request->input('entanglement_proof'));

            // Contribute quantum measurement
            $result = $this->smpcService->contributeQuantumMeasurement(
                $sessionId,
                $userId,
                $quantumState,
                $measurementBasis,
                $entanglementProof
            );

            $this->threatService->logQuantumEvent([
                'event_type' => 'quantum_measurement_contributed',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'accepted' => $result['accepted'],
                'measurements_received' => $result['measurements_received'],
                'consensus_reached' => $result['consensus_reached']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'measurement_accepted' => $result['accepted'],
                    'measurements_received' => $result['measurements_received'],
                    'consensus_reached' => $result['consensus_reached'],
                    'quantum_fidelity' => $result['quantum_fidelity'] ?? null,
                    'bell_violation' => $result['bell_violation'] ?? null,
                    'consensus_proof' => $result['consensus_proof'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quantum measurement contribution failed', [
                'user_id' => Auth::id(),
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to contribute quantum measurement'
            ], 500);
        }
    }

    public function getProtocolStatus(Request $request, string $sessionId): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Get protocol status
            $status = $this->smpcService->getProtocolStatus($sessionId, $userId);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Protocol session not found or access denied'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get protocol status', [
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve protocol status'
            ], 500);
        }
    }

    public function abortProtocol(Request $request, string $sessionId): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['session_id' => $sessionId]), [
            'session_id' => 'required|string|exists:quantum_smpc_sessions,session_id',
            'abort_reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            $reason = $request->input('abort_reason');

            // Abort protocol
            $this->smpcService->abortProtocol($sessionId, $userId, $reason);

            $this->threatService->logQuantumEvent([
                'event_type' => 'smpc_protocol_aborted',
                'user_id' => $userId,
                'session_id' => $sessionId,
                'reason' => $reason,
                'severity' => 'warning'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Protocol session aborted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to abort protocol', [
                'session_id' => $sessionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to abort protocol session'
            ], 500);
        }
    }

    public function listProtocolSessions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'protocol_type' => 'string|in:threshold_signing,multiparty_key_gen,secure_voting,quantum_consensus',
            'status' => 'string|in:initializing,running,completed,failed,aborted',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::id();
            
            $filters = [
                'protocol_type' => $request->input('protocol_type'),
                'status' => $request->input('status'),
                'page' => $request->input('page', 1),
                'per_page' => $request->input('per_page', 20)
            ];

            // Get protocol sessions
            $sessions = $this->smpcService->listProtocolSessions($userId, $filters);

            return response()->json([
                'success' => true,
                'data' => $sessions['sessions'],
                'pagination' => $sessions['pagination']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list protocol sessions', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve protocol sessions'
            ], 500);
        }
    }

    public function getParticipantStatus(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Get participant status
            $status = $this->smpcService->getParticipantStatus($userId);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Participant not registered'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get participant status', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve participant status'
            ], 500);
        }
    }
}