<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class LiveKitService
{
    private string $apiKey;

    private string $apiSecret;

    private string $serverUrl;

    private Client $httpClient;

    public function __construct()
    {
        $this->apiKey = config('livekit.api_key');
        $this->apiSecret = config('livekit.api_secret');
        $this->serverUrl = config('livekit.server_url');
        $this->httpClient = new Client;
    }

    /**
     * Generate access token for LiveKit room
     */
    public function generateAccessToken(
        string $roomName,
        string $participantIdentity,
        ?string $participantName = null,
        array $grants = [],
        int $ttl = 3600
    ): string {
        $now = time();
        $payload = [
            'iss' => $this->apiKey,
            'sub' => $participantIdentity,
            'iat' => $now,
            'exp' => $now + $ttl,
            'nbf' => $now - 5, // 5 second leeway
            'jti' => bin2hex(random_bytes(16)),
            'video' => array_merge([
                'room' => $roomName,
                'roomJoin' => true,
                'roomList' => false,
                'roomRecord' => false,
                'roomAdmin' => false,
                'roomCreate' => false,
                'ingressAdmin' => false,
                'hidden' => false,
                'recorder' => false,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
                'canUpdateOwnMetadata' => true,
            ], $grants),
        ];

        if ($participantName) {
            $payload['name'] = $participantName;
        }

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Create a new LiveKit room
     */
    public function createRoom(
        string $roomName,
        int $maxParticipants = 10,
        int $emptyTimeout = 600, // 10 minutes
        array $metadata = []
    ): array {
        $token = $this->generateServerToken();

        $response = $this->makeApiRequest('POST', '/twirp/livekit.RoomService/CreateRoom', [
            'name' => $roomName,
            'max_participants' => $maxParticipants,
            'empty_timeout' => $emptyTimeout,
            'metadata' => json_encode($metadata),
        ], $token);

        return $response;
    }

    /**
     * List all rooms
     */
    public function listRooms(): array
    {
        $token = $this->generateServerToken();

        $response = $this->makeApiRequest('POST', '/twirp/livekit.RoomService/ListRooms', [], $token);

        return $response['rooms'] ?? [];
    }

    /**
     * Get room information
     */
    public function getRoom(string $roomName): ?array
    {
        $rooms = $this->listRooms();

        foreach ($rooms as $room) {
            if ($room['name'] === $roomName) {
                return $room;
            }
        }

        return null;
    }

    /**
     * Delete a room
     */
    public function deleteRoom(string $roomName): bool
    {
        $token = $this->generateServerToken();

        try {
            $this->makeApiRequest('POST', '/twirp/livekit.RoomService/DeleteRoom', [
                'room' => $roomName,
            ], $token);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete LiveKit room', [
                'room' => $roomName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List participants in a room
     */
    public function listParticipants(string $roomName): array
    {
        $token = $this->generateServerToken();

        $response = $this->makeApiRequest('POST', '/twirp/livekit.RoomService/ListParticipants', [
            'room' => $roomName,
        ], $token);

        return $response['participants'] ?? [];
    }

    /**
     * Remove a participant from a room
     */
    public function removeParticipant(string $roomName, string $participantIdentity): bool
    {
        $token = $this->generateServerToken();

        try {
            $this->makeApiRequest('POST', '/twirp/livekit.RoomService/RemoveParticipant', [
                'room' => $roomName,
                'identity' => $participantIdentity,
            ], $token);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to remove participant from LiveKit room', [
                'room' => $roomName,
                'participant' => $participantIdentity,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mute/unmute a participant's track
     */
    public function muteParticipantTrack(
        string $roomName,
        string $participantIdentity,
        string $trackSid,
        bool $muted
    ): bool {
        $token = $this->generateServerToken();

        try {
            $this->makeApiRequest('POST', '/twirp/livekit.RoomService/MutePublishedTrack', [
                'room' => $roomName,
                'identity' => $participantIdentity,
                'track_sid' => $trackSid,
                'muted' => $muted,
            ], $token);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mute/unmute participant track', [
                'room' => $roomName,
                'participant' => $participantIdentity,
                'track_sid' => $trackSid,
                'muted' => $muted,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update room metadata
     */
    public function updateRoomMetadata(string $roomName, array $metadata): bool
    {
        $token = $this->generateServerToken();

        try {
            $this->makeApiRequest('POST', '/twirp/livekit.RoomService/UpdateRoomMetadata', [
                'room' => $roomName,
                'metadata' => json_encode($metadata),
            ], $token);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update room metadata', [
                'room' => $roomName,
                'metadata' => $metadata,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update participant metadata
     */
    public function updateParticipantMetadata(
        string $roomName,
        string $participantIdentity,
        array $metadata
    ): bool {
        $token = $this->generateServerToken();

        try {
            $this->makeApiRequest('POST', '/twirp/livekit.RoomService/UpdateParticipant', [
                'room' => $roomName,
                'identity' => $participantIdentity,
                'metadata' => json_encode($metadata),
            ], $token);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update participant metadata', [
                'room' => $roomName,
                'participant' => $participantIdentity,
                'metadata' => $metadata,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate server-to-server authentication token
     */
    private function generateServerToken(): string
    {
        $now = time();
        $payload = [
            'iss' => $this->apiKey,
            'iat' => $now,
            'exp' => $now + 600, // 10 minutes
            'nbf' => $now - 5,
            'video' => [
                'roomAdmin' => true,
                'roomList' => true,
                'roomCreate' => true,
                'roomJoin' => false,
                'roomRecord' => true,
                'ingressAdmin' => true,
            ],
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Make authenticated API request to LiveKit server
     */
    private function makeApiRequest(string $method, string $endpoint, array $data = [], ?string $token = null): array
    {
        $url = rtrim($this->serverUrl, '/').$endpoint;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $response = $this->httpClient->request($method, $url, [
                'headers' => $headers,
                'json' => $data,
                'timeout' => 30,
            ]);

            $body = $response->getBody()->getContents();

            return json_decode($body, true) ?? [];

        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';

            Log::error('LiveKit API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $responseBody,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('LiveKit API request failed: '.$e->getMessage());
        }
    }

    /**
     * Create E2EE room with encryption keys
     */
    public function createE2EERoom(
        string $roomName,
        array $participantIdentities,
        string $encryptionKey,
        array $metadata = []
    ): array {
        // Add E2EE metadata to the room
        $e2eeMetadata = array_merge($metadata, [
            'encrypted' => true,
            'encryption_algorithm' => 'AES-GCM',
            'key_rotation_enabled' => true,
            'participants_required' => $participantIdentities,
        ]);

        $room = $this->createRoom($roomName, count($participantIdentities) + 2, 1800, $e2eeMetadata);

        // Store encryption key securely (this would typically go to a secure key management system)
        // For now, we'll log this as a placeholder
        Log::info('E2EE room created with encryption', [
            'room_name' => $roomName,
            'participants' => $participantIdentities,
            'encryption_enabled' => true,
        ]);

        return $room;
    }

    /**
     * Validate webhook signature from LiveKit
     */
    public function validateWebhook(string $body, string $signature, string $timestamp): bool
    {
        $expectedSignature = hash_hmac('sha256', $timestamp.$body, $this->apiSecret);

        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Get connection details for client
     */
    public function getConnectionDetails(
        string $roomName,
        string $participantIdentity,
        ?string $participantName = null,
        array $permissions = []
    ): array {
        $token = $this->generateAccessToken(
            $roomName,
            $participantIdentity,
            $participantName,
            $permissions
        );

        return [
            'server_url' => $this->serverUrl,
            'access_token' => $token,
            'room_name' => $roomName,
            'participant_identity' => $participantIdentity,
            'participant_name' => $participantName,
        ];
    }
}
