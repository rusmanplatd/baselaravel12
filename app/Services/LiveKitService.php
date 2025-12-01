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
        $this->apiKey = config('livekit.api_key') ?? '';
        $this->apiSecret = config('livekit.api_secret') ?? '';
        $this->serverUrl = config('livekit.server_url') ?? 'ws://localhost:7880';
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

    // =================== ADVANCED MEETING FEATURES ===================

    /**
     * Start recording a meeting
     */
    public function startRecording(
        string $roomName,
        array $recordingConfig = []
    ): array {
        $token = $this->generateServerToken();

        $defaultConfig = [
            'audio' => true,
            'video' => true,
            'video_width' => 1920,
            'video_height' => 1080,
            'video_bitrate' => 4000000, // 4 Mbps
            'audio_bitrate' => 128000,  // 128 kbps
            'layout' => 'grid', // grid, speaker, custom
            'preset' => 'HD_720P_30', // HD_1080P_30, HD_720P_30, SD_480P_30
            'file_outputs' => [
                [
                    'file_type' => 'MP4',
                    'filepath' => "recordings/{$roomName}_" . date('Y-m-d_H-i-s') . '.mp4'
                ]
            ]
        ];

        $config = array_merge($defaultConfig, $recordingConfig);

        try {
            $response = $this->makeApiRequest('POST', '/twirp/livekit.Egress/StartRoomCompositeEgress', [
                'room_name' => $roomName,
                'layout' => $config['layout'],
                'audio' => $config['audio'],
                'video' => $config['video'],
                'file_outputs' => $config['file_outputs'],
                'preset' => $config['preset'],
                'advanced' => [
                    'video_bitrate' => $config['video_bitrate'],
                    'audio_bitrate' => $config['audio_bitrate']
                ]
            ], $token);

            Log::info('Recording started for room', [
                'room_name' => $roomName,
                'egress_id' => $response['egress_id'] ?? null,
                'config' => $config
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to start recording', [
                'room_name' => $roomName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Stop recording a meeting
     */
    public function stopRecording(string $egressId): array
    {
        $token = $this->generateServerToken();

        try {
            $response = $this->makeApiRequest('POST', '/twirp/livekit.Egress/StopEgress', [
                'egress_id' => $egressId
            ], $token);

            Log::info('Recording stopped', [
                'egress_id' => $egressId,
                'status' => $response['status'] ?? 'unknown'
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to stop recording', [
                'egress_id' => $egressId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Start live streaming a meeting to RTMP endpoint
     */
    public function startLiveStream(
        string $roomName,
        array $streamUrls,
        array $streamConfig = []
    ): array {
        $token = $this->generateServerToken();

        $defaultConfig = [
            'layout' => 'speaker',
            'audio' => true,
            'video' => true,
            'preset' => 'HD_720P_30'
        ];

        $config = array_merge($defaultConfig, $streamConfig);

        $streamOutputs = array_map(function($url) {
            return ['rtmp_url' => $url];
        }, $streamUrls);

        try {
            $response = $this->makeApiRequest('POST', '/twirp/livekit.Egress/StartRoomCompositeEgress', [
                'room_name' => $roomName,
                'layout' => $config['layout'],
                'audio' => $config['audio'],
                'video' => $config['video'],
                'stream_outputs' => $streamOutputs,
                'preset' => $config['preset']
            ], $token);

            Log::info('Live stream started for room', [
                'room_name' => $roomName,
                'egress_id' => $response['egress_id'] ?? null,
                'stream_urls' => $streamUrls
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to start live stream', [
                'room_name' => $roomName,
                'stream_urls' => $streamUrls,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get room analytics and statistics
     */
    public function getRoomAnalytics(string $roomName): array
    {
        $token = $this->generateServerToken();

        try {
            // Get current room stats
            $roomInfo = $this->getRoom($roomName);
            $participants = $this->listParticipants($roomName);

            $analytics = [
                'room_name' => $roomName,
                'timestamp' => now()->toISOString(),
                'participant_count' => count($participants),
                'room_duration' => $this->calculateRoomDuration($roomInfo),
                'participants' => array_map(function($participant) {
                    return [
                        'identity' => $participant['identity'] ?? '',
                        'name' => $participant['name'] ?? '',
                        'joined_at' => $participant['joined_at'] ?? null,
                        'tracks' => $participant['tracks'] ?? [],
                        'metadata' => json_decode($participant['metadata'] ?? '{}', true)
                    ];
                }, $participants),
                'room_metadata' => json_decode($roomInfo['metadata'] ?? '{}', true)
            ];

            return $analytics;
        } catch (\Exception $e) {
            Log::error('Failed to get room analytics', [
                'room_name' => $roomName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Set room layout template
     */
    public function setRoomLayout(
        string $roomName,
        string $layout = 'grid',
        array $layoutConfig = []
    ): bool {
        $validLayouts = ['grid', 'speaker', 'focus', 'custom'];
        
        if (!in_array($layout, $validLayouts)) {
            throw new \InvalidArgumentException("Invalid layout: $layout");
        }

        $metadata = [
            'layout' => $layout,
            'layout_config' => $layoutConfig,
            'updated_at' => now()->toISOString()
        ];

        return $this->updateRoomMetadata($roomName, $metadata);
    }

    /**
     * Create breakout rooms for a main meeting
     */

    /**
     * Generate advanced access token with custom permissions
     */
    public function generateAdvancedAccessToken(
        string $roomName,
        string $participantIdentity,
        array $permissions = [],
        array $attributes = [],
        int $ttl = 3600
    ): string {
        $now = time();
        
        $defaultGrants = [
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
        ];

        // Merge with custom permissions
        $grants = array_merge($defaultGrants, $permissions);

        $payload = [
            'iss' => $this->apiKey,
            'sub' => $participantIdentity,
            'iat' => $now,
            'exp' => $now + $ttl,
            'nbf' => $now - 5,
            'jti' => bin2hex(random_bytes(16)),
            'video' => $grants,
            'attributes' => $attributes
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Send data message to room participants
     */
    public function sendDataMessage(
        string $roomName,
        array $data,
        array $targetParticipants = []
    ): bool {
        $token = $this->generateServerToken();

        try {
            $payload = [
                'room' => $roomName,
                'data' => base64_encode(json_encode($data)),
                'kind' => 'RELIABLE'
            ];

            if (!empty($targetParticipants)) {
                $payload['destination_sids'] = $targetParticipants;
            }

            $this->makeApiRequest('POST', '/twirp/livekit.RoomService/SendData', $payload, $token);

            Log::info('Data message sent to room', [
                'room_name' => $roomName,
                'data_size' => strlen(json_encode($data)),
                'targets' => count($targetParticipants)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send data message', [
                'room_name' => $roomName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate room duration from creation time
     */
    private function calculateRoomDuration(?array $roomInfo): ?int
    {
        if (!$roomInfo || !isset($roomInfo['creation_time'])) {
            return null;
        }

        $creationTime = $roomInfo['creation_time'];
        $now = time();
        
        return max(0, $now - $creationTime);
    }

    /**
     * Get egress (recording/streaming) status
     */
    public function getEgressStatus(string $egressId): array
    {
        $token = $this->generateServerToken();

        try {
            $response = $this->makeApiRequest('POST', '/twirp/livekit.Egress/ListEgress', [
                'egress_id' => $egressId
            ], $token);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to get egress status', [
                'egress_id' => $egressId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update room configuration dynamically
     */
    public function updateRoomConfiguration(
        string $roomName,
        array $config
    ): bool {
        try {
            $metadata = [
                'room_config' => $config,
                'updated_at' => now()->toISOString()
            ];

            return $this->updateRoomMetadata($roomName, $metadata);
        } catch (\Exception $e) {
            Log::error('Failed to update room configuration', [
                'room_name' => $roomName,
                'config' => $config,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get room quality metrics
     */
    public function getRoomQualityMetrics(string $roomName): array
    {
        try {
            $participants = $this->listParticipants($roomName);
            $roomInfo = $this->getRoom($roomName);

            $qualityMetrics = [
                'room_name' => $roomName,
                'timestamp' => now()->toISOString(),
                'participant_count' => count($participants),
                'total_tracks' => 0,
                'audio_tracks' => 0,
                'video_tracks' => 0,
                'participants_quality' => []
            ];

            foreach ($participants as $participant) {
                $tracks = $participant['tracks'] ?? [];
                $qualityMetrics['total_tracks'] += count($tracks);
                
                $audioTracks = array_filter($tracks, fn($track) => ($track['type'] ?? '') === 'audio');
                $videoTracks = array_filter($tracks, fn($track) => ($track['type'] ?? '') === 'video');
                
                $qualityMetrics['audio_tracks'] += count($audioTracks);
                $qualityMetrics['video_tracks'] += count($videoTracks);

                $qualityMetrics['participants_quality'][] = [
                    'identity' => $participant['identity'] ?? '',
                    'name' => $participant['name'] ?? '',
                    'audio_tracks' => count($audioTracks),
                    'video_tracks' => count($videoTracks),
                    'connection_quality' => $participant['connection_quality'] ?? 'unknown'
                ];
            }

            return $qualityMetrics;
        } catch (\Exception $e) {
            Log::error('Failed to get room quality metrics', [
                'room_name' => $roomName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // =================== BREAKOUT ROOMS FUNCTIONALITY ===================

    /**
     * Create multiple breakout rooms for a meeting
     */
    public function createBreakoutRooms(
        string $mainRoomName,
        int $numberOfRooms,
        array $participants = [],
        array $roomConfig = []
    ): array {
        try {
            $breakoutRooms = [];
            $defaultConfig = array_merge([
                'max_participants' => 10,
                'empty_timeout' => 300, // 5 minutes
                'departure_timeout' => 60, // 1 minute
                'enable_recording' => false,
            ], $roomConfig);

            // Create breakout rooms
            for ($i = 1; $i <= $numberOfRooms; $i++) {
                $roomName = "{$mainRoomName}_breakout_{$i}";
                
                $roomOptions = [
                    'name' => $roomName,
                    'metadata' => json_encode([
                        'type' => 'breakout',
                        'main_room' => $mainRoomName,
                        'room_number' => $i,
                        'max_participants' => $defaultConfig['max_participants'],
                    ]),
                    'empty_timeout' => $defaultConfig['empty_timeout'],
                    'departure_timeout' => $defaultConfig['departure_timeout'],
                ];

                $room = $this->createRoom($roomName, $roomOptions);
                
                $breakoutRooms[] = [
                    'room_name' => $roomName,
                    'room_number' => $i,
                    'room_sid' => $room['sid'] ?? null,
                    'config' => $defaultConfig,
                    'participants' => [],
                    'status' => 'created'
                ];
            }

            // Auto-assign participants if provided
            if (!empty($participants)) {
                $breakoutRooms = $this->autoAssignParticipants($breakoutRooms, $participants);
            }

            Log::info('Breakout rooms created successfully', [
                'main_room' => $mainRoomName,
                'breakout_rooms_count' => $numberOfRooms,
                'participants_count' => count($participants)
            ]);

            return $breakoutRooms;
        } catch (\Exception $e) {
            Log::error('Failed to create breakout rooms', [
                'main_room' => $mainRoomName,
                'number_of_rooms' => $numberOfRooms,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Move participant to breakout room
     */
    public function moveParticipantToBreakoutRoom(
        string $participantIdentity,
        string $sourceRoom,
        string $targetRoom,
        array $permissions = []
    ): array {
        try {
            // Generate token for target room
            $token = $this->generateAccessToken(
                $targetRoom,
                $participantIdentity,
                null,
                $permissions
            );

            // Remove from source room
            $this->removeParticipant($sourceRoom, $participantIdentity);

            Log::info('Participant moved to breakout room', [
                'participant' => $participantIdentity,
                'source_room' => $sourceRoom,
                'target_room' => $targetRoom
            ]);

            return [
                'success' => true,
                'target_room' => $targetRoom,
                'access_token' => $token,
                'server_url' => $this->serverUrl,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to move participant to breakout room', [
                'participant' => $participantIdentity,
                'source_room' => $sourceRoom,
                'target_room' => $targetRoom,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Return participant to main room from breakout room
     */
    public function returnParticipantToMainRoom(
        string $participantIdentity,
        string $breakoutRoom,
        string $mainRoom,
        array $permissions = []
    ): array {
        return $this->moveParticipantToBreakoutRoom(
            $participantIdentity,
            $breakoutRoom,
            $mainRoom,
            $permissions
        );
    }

    /**
     * Close all breakout rooms and return participants to main room
     */
    public function closeAllBreakoutRooms(
        string $mainRoomName,
        array $breakoutRoomNames = []
    ): array {
        try {
            $results = [];
            
            foreach ($breakoutRoomNames as $breakoutRoom) {
                // Get all participants in breakout room
                $participants = $this->listParticipants($breakoutRoom);
                
                // Move each participant back to main room
                foreach ($participants as $participant) {
                    try {
                        $result = $this->returnParticipantToMainRoom(
                            $participant['identity'],
                            $breakoutRoom,
                            $mainRoomName
                        );
                        
                        $results[] = [
                            'participant' => $participant['identity'],
                            'breakout_room' => $breakoutRoom,
                            'status' => 'moved',
                            'result' => $result
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'participant' => $participant['identity'],
                            'breakout_room' => $breakoutRoom,
                            'status' => 'error',
                            'error' => $e->getMessage()
                        ];
                    }
                }

                // Delete the breakout room
                try {
                    $this->deleteRoom($breakoutRoom);
                    Log::info('Breakout room closed', ['room' => $breakoutRoom]);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete breakout room', [
                        'room' => $breakoutRoom,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'main_room' => $mainRoomName,
                'closed_rooms' => $breakoutRoomNames,
                'participant_moves' => $results
            ];
        } catch (\Exception $e) {
            Log::error('Failed to close breakout rooms', [
                'main_room' => $mainRoomName,
                'breakout_rooms' => $breakoutRoomNames,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get breakout room status and participants
     */
    public function getBreakoutRoomStatus(string $roomName): array
    {
        try {
            $room = $this->getRoom($roomName);
            $participants = $this->listParticipants($roomName);

            $metadata = [];
            if (isset($room['metadata'])) {
                $metadata = json_decode($room['metadata'], true) ?? [];
            }

            return [
                'room_name' => $roomName,
                'room_sid' => $room['sid'] ?? null,
                'metadata' => $metadata,
                'participant_count' => count($participants),
                'participants' => array_map(function($participant) {
                    return [
                        'identity' => $participant['identity'] ?? '',
                        'name' => $participant['name'] ?? '',
                        'joined_at' => $participant['joined_at'] ?? null,
                        'tracks' => $participant['tracks'] ?? [],
                    ];
                }, $participants),
                'created_at' => $room['creation_time'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get breakout room status', [
                'room_name' => $roomName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Auto-assign participants to breakout rooms
     */
    protected function autoAssignParticipants(array $breakoutRooms, array $participants): array
    {
        $participantCount = count($participants);
        $roomCount = count($breakoutRooms);
        
        if ($participantCount === 0 || $roomCount === 0) {
            return $breakoutRooms;
        }

        // Simple round-robin assignment
        $participantsPerRoom = intval($participantCount / $roomCount);
        $extraParticipants = $participantCount % $roomCount;

        $participantIndex = 0;
        
        for ($i = 0; $i < $roomCount; $i++) {
            $assignCount = $participantsPerRoom + ($i < $extraParticipants ? 1 : 0);
            
            for ($j = 0; $j < $assignCount && $participantIndex < $participantCount; $j++) {
                $breakoutRooms[$i]['participants'][] = $participants[$participantIndex];
                $participantIndex++;
            }
        }

        return $breakoutRooms;
    }

    /**
     * Broadcast message to all breakout rooms
     */
    public function broadcastToBreakoutRooms(
        array $breakoutRoomNames,
        string $message,
        array $senderInfo = []
    ): array {
        $results = [];
        
        foreach ($breakoutRoomNames as $roomName) {
            try {
                // Send data message to all participants in the room
                $result = $this->sendDataMessage($roomName, [
                    'type' => 'broadcast',
                    'message' => $message,
                    'sender' => $senderInfo,
                    'timestamp' => now()->toISOString()
                ], []); // Empty array for target participants (broadcast to all)
                
                $results[] = [
                    'room' => $roomName,
                    'status' => $result ? 'sent' : 'failed'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'room' => $roomName,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

}
