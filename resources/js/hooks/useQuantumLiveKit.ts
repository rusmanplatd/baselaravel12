import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Room,
  RoomEvent,
  Track,
  RemoteTrack,
  RemoteParticipant,
  LocalTrack,
  createLocalVideoTrack,
  createLocalAudioTrack,
  VideoPresets,
  RoomOptions,
  ConnectOptions,
  ParticipantEvent
} from 'livekit-client';
import { QuantumLiveKitKeyProvider } from '@/services/QuantumLiveKitKeyProvider';

interface QuantumLiveKitOptions {
  conversationId: string;
  userId: string;
  enableQuantumE2EE?: boolean;
  keyRotationInterval?: number;
  preferredAlgorithm?: 'ML-KEM-512' | 'ML-KEM-768' | 'ML-KEM-1024';
}

interface CallParticipant {
  id: string;
  name: string;
  avatar?: string;
  isLocal: boolean;
  isConnected: boolean;
  hasVideo: boolean;
  hasAudio: boolean;
  isSpeaking: boolean;
  connectionQuality: 'excellent' | 'good' | 'poor' | 'unknown';
  quantumEnabled: boolean;
  deviceInfo?: {
    deviceId: string;
    platform: string;
    browser: string;
    quantumCapable: boolean;
  };
}

interface CallStats {
  duration: number;
  participantCount: number;
  quantumParticipants: number;
  averageBitrate: number;
  packetsLost: number;
  keyRotations: number;
  encryptionHealth: 'excellent' | 'good' | 'poor' | 'degraded';
}

interface UseQuantumLiveKitReturn {
  // Room state
  room: Room | null;
  isConnected: boolean;
  isConnecting: boolean;
  connectionQuality: string;

  // Participants
  participants: CallParticipant[];
  localParticipant: CallParticipant | null;

  // Media tracks
  localTracks: LocalTrack[];
  remoteTracks: RemoteTrack[];

  // Quantum E2EE
  quantumKeyProvider: QuantumLiveKitKeyProvider | null;
  encryptionEnabled: boolean;
  keyStats: any;

  // Call management
  connect: (roomName: string, token: string, options?: ConnectOptions) => Promise<void>;
  disconnect: () => Promise<void>;

  // Media control
  toggleCamera: () => Promise<void>;
  toggleMicrophone: () => Promise<void>;
  startScreenShare: () => Promise<void>;
  stopScreenShare: () => Promise<void>;

  // Advanced features
  switchCamera: () => Promise<void>;
  setVideoQuality: (quality: 'high' | 'medium' | 'low') => Promise<void>;
  enableNoiseSupression: (enabled: boolean) => Promise<void>;

  // Statistics
  callStats: CallStats;
  refreshStats: () => Promise<void>;

  // Error handling
  error: string | null;
  clearError: () => void;
}

export const useQuantumLiveKit = (options: QuantumLiveKitOptions): UseQuantumLiveKitReturn => {
  const [room, setRoom] = useState<Room | null>(null);
  const [isConnected, setIsConnected] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const [connectionQuality, setConnectionQuality] = useState('unknown');

  const [participants, setParticipants] = useState<CallParticipant[]>([]);
  const [localParticipant, setLocalParticipant] = useState<CallParticipant | null>(null);

  const [localTracks, setLocalTracks] = useState<LocalTrack[]>([]);
  const [remoteTracks, setRemoteTracks] = useState<RemoteTrack[]>([]);

  const [quantumKeyProvider, setQuantumKeyProvider] = useState<QuantumLiveKitKeyProvider | null>(null);
  const [encryptionEnabled, setEncryptionEnabled] = useState(false);
  const [keyStats, setKeyStats] = useState<any>({});

  const [callStats, setCallStats] = useState<CallStats>({
    duration: 0,
    participantCount: 0,
    quantumParticipants: 0,
    averageBitrate: 0,
    packetsLost: 0,
    keyRotations: 0,
    encryptionHealth: 'unknown' as const,
  });

  const [error, setError] = useState<string | null>(null);

  const localVideoTrackRef = useRef<LocalTrack | null>(null);
  const localAudioTrackRef = useRef<LocalTrack | null>(null);
  const localScreenTrackRef = useRef<LocalTrack | null>(null);
  const callStartTimeRef = useRef<Date | null>(null);
  const statsIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // Initialize room and quantum key provider
  useEffect(() => {
    const initializeRoom = async () => {
      try {
        // Create quantum key provider if E2EE is enabled
        let keyProvider: QuantumLiveKitKeyProvider | null = null;

        if (options.enableQuantumE2EE) {
          // Load quantum service (would be injected in real implementation)
          const quantumService = await loadQuantumService();

          keyProvider = new QuantumLiveKitKeyProvider({
            conversationId: options.conversationId,
            userId: options.userId,
            deviceId: getDeviceId(),
            quantumService,
            keyRotationInterval: options.keyRotationInterval,
          });

          await keyProvider.initialize();
          setQuantumKeyProvider(keyProvider);
          setEncryptionEnabled(true);
        }

        // Create room with E2EE options
        const roomOptions: RoomOptions = {
          adaptiveStream: true,
          dynacast: true,
          videoCaptureDefaults: {
            resolution: VideoPresets.h720.resolution,
            facingMode: 'user',
          },
          audioCaptureDefaults: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
          },
        };

        // Add E2EE configuration if available
        if (keyProvider) {
          roomOptions.e2ee = {
            keyProvider,
            worker: new Worker('/livekit-e2ee-worker.js'), // Would need to be created
          };
        }

        const newRoom = new Room(roomOptions);

        // Set up event listeners
        setupRoomEventListeners(newRoom, keyProvider);

        setRoom(newRoom);

      } catch (err) {
        console.error('Failed to initialize room:', err);
        setError(err instanceof Error ? err.message : 'Failed to initialize room');
      }
    };

    initializeRoom();

    return () => {
      // Cleanup
      if (room) {
        room.disconnect();
      }
      if (statsIntervalRef.current) {
        clearInterval(statsIntervalRef.current);
      }
    };
  }, [options.conversationId, options.userId, options.enableQuantumE2EE]);

  // Set up room event listeners
  const setupRoomEventListeners = useCallback((
    room: Room,
    keyProvider: QuantumLiveKitKeyProvider | null
  ) => {
    room.on(RoomEvent.Connected, () => {
      setIsConnected(true);
      setIsConnecting(false);
      setError(null);
      callStartTimeRef.current = new Date();

      // Start statistics collection
      startStatsCollection();
    });

    room.on(RoomEvent.Disconnected, (reason) => {
      setIsConnected(false);
      setIsConnecting(false);
      setLocalTracks([]);
      setRemoteTracks([]);
      setParticipants([]);
      setLocalParticipant(null);
      callStartTimeRef.current = null;

      if (statsIntervalRef.current) {
        clearInterval(statsIntervalRef.current);
      }

      if (reason) {
        setError(`Disconnected: ${reason}`);
      }
    });

    room.on(RoomEvent.ParticipantConnected, async (participant: RemoteParticipant) => {
      // Handle quantum key exchange for new participant
      if (keyProvider) {
        try {
          await keyProvider.onParticipantConnected(participant.sid, participant.metadata);
        } catch (err) {
          console.error('Key exchange failed for participant:', participant.sid, err);
        }
      }

      updateParticipantList();
    });

    room.on(RoomEvent.ParticipantDisconnected, async (participant: RemoteParticipant) => {
      if (keyProvider) {
        await keyProvider.onParticipantDisconnected(participant.sid);
      }

      updateParticipantList();
    });

    room.on(RoomEvent.TrackSubscribed, (track: RemoteTrack, participant: RemoteParticipant) => {
      if (track.kind === Track.Kind.Video || track.kind === Track.Kind.Audio) {
        setRemoteTracks(prev => [...prev, track]);
      }
    });

    room.on(RoomEvent.TrackUnsubscribed, (track: RemoteTrack) => {
      setRemoteTracks(prev => prev.filter(t => t.sid !== track.sid));
    });

    room.on(RoomEvent.ConnectionQualityChanged, (quality, participant) => {
      if (!participant) {
        // Local participant quality
        setConnectionQuality(quality);
      }
      updateParticipantList();
    });

    room.on(RoomEvent.LocalTrackPublished, (publication, participant) => {
      if (publication.track) {
        setLocalTracks(prev => [...prev, publication.track as LocalTrack]);
      }
    });

    room.on(RoomEvent.LocalTrackUnpublished, (publication, participant) => {
      if (publication.track) {
        setLocalTracks(prev => prev.filter(t => t.sid !== publication.track?.sid));
      }
    });

    // Quantum-specific events
    if (keyProvider) {
      // Set up key rotation notifications
      setInterval(() => {
        const stats = keyProvider.getKeyStats();
        setKeyStats(stats);
      }, 10000); // Update every 10 seconds
    }
  }, []);

  // Connect to room
  const connect = useCallback(async (roomName: string, token: string, options: ConnectOptions = {}) => {
    if (!room || isConnecting) return;

    try {
      setIsConnecting(true);
      setError(null);

      const liveKitUrl = import.meta.env.VITE_LIVEKIT_URL || 'ws://localhost:7880';

      // Add quantum-capable metadata
      const connectOptions: ConnectOptions = {
        ...options,
        autoSubscribe: true,
        maxRetries: 3,
        metadata: JSON.stringify({
          userId: options.userId,
          deviceId: getDeviceId(),
          quantumCapable: !!quantumKeyProvider,
          supportedAlgorithms: quantumKeyProvider
            ? ['ML-KEM-768', 'ML-KEM-1024', 'HYBRID-RSA-MLKEM']
            : ['ECDH-P384'],
          platform: navigator.platform,
          browser: getBrowserInfo(),
        }),
      };

      await room.connect(liveKitUrl, token, connectOptions);

    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to connect';
      setError(errorMessage);
      setIsConnecting(false);
      throw err;
    }
  }, [room, isConnecting, quantumKeyProvider, options.userId]);

  // Disconnect from room
  const disconnect = useCallback(async () => {
    if (!room) return;

    try {
      // Clean up local tracks
      await Promise.all([
        localVideoTrackRef.current?.stop(),
        localAudioTrackRef.current?.stop(),
        localScreenTrackRef.current?.stop(),
      ]);

      localVideoTrackRef.current = null;
      localAudioTrackRef.current = null;
      localScreenTrackRef.current = null;

      await room.disconnect();
    } catch (err) {
      console.error('Error disconnecting:', err);
    }
  }, [room]);

  // Toggle camera
  const toggleCamera = useCallback(async () => {
    if (!room || !isConnected) return;

    try {
      if (localVideoTrackRef.current) {
        // Turn off camera
        await room.localParticipant.unpublishTrack(localVideoTrackRef.current);
        await localVideoTrackRef.current.stop();
        localVideoTrackRef.current = null;
      } else {
        // Turn on camera
        const videoTrack = await createLocalVideoTrack({
          resolution: VideoPresets.h720.resolution,
          facingMode: 'user',
        });

        localVideoTrackRef.current = videoTrack;
        await room.localParticipant.publishTrack(videoTrack, {
          name: 'camera',
          source: Track.Source.Camera,
        });
      }

      updateParticipantList();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to toggle camera');
    }
  }, [room, isConnected]);

  // Toggle microphone
  const toggleMicrophone = useCallback(async () => {
    if (!room || !isConnected) return;

    try {
      if (localAudioTrackRef.current) {
        // Turn off microphone
        await room.localParticipant.unpublishTrack(localAudioTrackRef.current);
        await localAudioTrackRef.current.stop();
        localAudioTrackRef.current = null;
      } else {
        // Turn on microphone
        const audioTrack = await createLocalAudioTrack({
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        });

        localAudioTrackRef.current = audioTrack;
        await room.localParticipant.publishTrack(audioTrack, {
          name: 'microphone',
          source: Track.Source.Microphone,
        });
      }

      updateParticipantList();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to toggle microphone');
    }
  }, [room, isConnected]);

  // Start screen share
  const startScreenShare = useCallback(async () => {
    if (!room || !isConnected || localScreenTrackRef.current) return;

    try {
      const screenStream = await navigator.mediaDevices.getDisplayMedia({
        video: true,
        audio: true,
      });

      const videoTrack = screenStream.getVideoTracks()[0];
      if (videoTrack) {
        // Create LiveKit track from MediaStreamTrack
        const screenTrack = new LocalTrack(
          videoTrack,
          Track.Kind.Video,
          { source: Track.Source.ScreenShare }
        );

        localScreenTrackRef.current = screenTrack;

        await room.localParticipant.publishTrack(screenTrack, {
          name: 'screen',
          source: Track.Source.ScreenShare,
        });
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to start screen share');
    }
  }, [room, isConnected]);

  // Stop screen share
  const stopScreenShare = useCallback(async () => {
    if (!room || !localScreenTrackRef.current) return;

    try {
      await room.localParticipant.unpublishTrack(localScreenTrackRef.current);
      await localScreenTrackRef.current.stop();
      localScreenTrackRef.current = null;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to stop screen share');
    }
  }, [room]);

  // Switch camera (front/back on mobile)
  const switchCamera = useCallback(async () => {
    if (!localVideoTrackRef.current || !room) return;

    try {
      await localVideoTrackRef.current.switchCamera();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to switch camera');
    }
  }, [room]);

  // Set video quality
  const setVideoQuality = useCallback(async (quality: 'high' | 'medium' | 'low') => {
    if (!room) return;

    const qualitySettings = {
      high: VideoPresets.h1080,
      medium: VideoPresets.h720,
      low: VideoPresets.h360,
    };

    try {
      await room.localParticipant.setVideoQuality(qualitySettings[quality]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to set video quality');
    }
  }, [room]);

  // Enable noise suppression
  const enableNoiseSupression = useCallback(async (enabled: boolean) => {
    if (!localAudioTrackRef.current) return;

    try {
      await localAudioTrackRef.current.setProcessor(
        enabled ? { kind: 'noise_suppression' } : undefined
      );
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to toggle noise suppression');
    }
  }, []);

  // Update participant list
  const updateParticipantList = useCallback(() => {
    if (!room) return;

    const allParticipants: CallParticipant[] = [];

    // Local participant
    const local = room.localParticipant;
    const localData: CallParticipant = {
      id: local.sid,
      name: local.identity || local.name || 'You',
      isLocal: true,
      isConnected: true,
      hasVideo: !!localVideoTrackRef.current,
      hasAudio: !!localAudioTrackRef.current,
      isSpeaking: local.isSpeaking,
      connectionQuality: connectionQuality as any,
      quantumEnabled: !!quantumKeyProvider,
      deviceInfo: {
        deviceId: getDeviceId(),
        platform: navigator.platform,
        browser: getBrowserInfo(),
        quantumCapable: !!quantumKeyProvider,
      },
    };

    setLocalParticipant(localData);
    allParticipants.push(localData);

    // Remote participants
    room.remoteParticipants.forEach((participant) => {
      const metadata = parseParticipantMetadata(participant.metadata);

      allParticipants.push({
        id: participant.sid,
        name: participant.identity || participant.name || 'Unknown',
        isLocal: false,
        isConnected: participant.connectionState === 'connected',
        hasVideo: Array.from(participant.videoTracks.values()).some(t => t.isSubscribed),
        hasAudio: Array.from(participant.audioTracks.values()).some(t => t.isSubscribed),
        isSpeaking: participant.isSpeaking,
        connectionQuality: participant.connectionQuality as any,
        quantumEnabled: metadata.quantumCapable || false,
        deviceInfo: {
          deviceId: metadata.deviceId || 'unknown',
          platform: metadata.platform || 'unknown',
          browser: metadata.browser || 'unknown',
          quantumCapable: metadata.quantumCapable || false,
        },
      });
    });

    setParticipants(allParticipants);
  }, [room, connectionQuality, quantumKeyProvider]);

  // Start statistics collection
  const startStatsCollection = useCallback(() => {
    if (statsIntervalRef.current) return;

    statsIntervalRef.current = setInterval(() => {
      refreshStats();
    }, 5000); // Update every 5 seconds
  }, []);

  // Refresh statistics
  const refreshStats = useCallback(async () => {
    if (!room || !isConnected) return;

    try {
      const stats = await room.getStats();
      const duration = callStartTimeRef.current
        ? Date.now() - callStartTimeRef.current.getTime()
        : 0;

      const participantCount = participants.length;
      const quantumParticipants = participants.filter(p => p.quantumEnabled).length;

      // Calculate encryption health
      let encryptionHealth: CallStats['encryptionHealth'] = 'unknown';
      if (quantumKeyProvider) {
        const keyStatsData = keyStats;
        const quantumRatio = quantumParticipants / participantCount;

        if (quantumRatio >= 0.8) encryptionHealth = 'excellent';
        else if (quantumRatio >= 0.5) encryptionHealth = 'good';
        else if (quantumRatio > 0) encryptionHealth = 'poor';
        else encryptionHealth = 'degraded';
      } else {
        encryptionHealth = 'degraded';
      }

      setCallStats({
        duration,
        participantCount,
        quantumParticipants,
        averageBitrate: 0, // Would calculate from WebRTC stats
        packetsLost: 0, // Would calculate from WebRTC stats
        keyRotations: 0, // Would track from key provider
        encryptionHealth,
      });
    } catch (err) {
      console.error('Failed to refresh stats:', err);
    }
  }, [room, isConnected, participants, quantumKeyProvider, keyStats]);

  // Clear error
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  return {
    // Room state
    room,
    isConnected,
    isConnecting,
    connectionQuality,

    // Participants
    participants,
    localParticipant,

    // Media tracks
    localTracks,
    remoteTracks,

    // Quantum E2EE
    quantumKeyProvider,
    encryptionEnabled,
    keyStats,

    // Call management
    connect,
    disconnect,

    // Media control
    toggleCamera,
    toggleMicrophone,
    startScreenShare,
    stopScreenShare,

    // Advanced features
    switchCamera,
    setVideoQuality,
    enableNoiseSupression,

    // Statistics
    callStats,
    refreshStats,

    // Error handling
    error,
    clearError,
  };
};

// Helper functions
async function loadQuantumService(): Promise<any> {
  // TODO: In a real implementation, this would load your quantum crypto service
  return {
    generateKeyPair: async (algorithm: string) => {
      // Mock implementation
      return {
        public: new Uint8Array(32),
        private: new Uint8Array(32),
      };
    },
    encapsulate: async (publicKey: Uint8Array, algorithm: string) => {
      return {
        ciphertext: new Uint8Array(32),
        shared_secret: new Uint8Array(32),
      };
    },
  };
}

function getDeviceId(): string {
  let deviceId = localStorage.getItem('device_id');
  if (!deviceId) {
    deviceId = crypto.randomUUID();
    localStorage.setItem('device_id', deviceId);
  }
  return deviceId;
}

function getBrowserInfo(): string {
  const ua = navigator.userAgent;
  if (ua.includes('Chrome')) return 'Chrome';
  if (ua.includes('Firefox')) return 'Firefox';
  if (ua.includes('Safari')) return 'Safari';
  if (ua.includes('Edge')) return 'Edge';
  return 'Unknown';
}

function parseParticipantMetadata(metadata: string | undefined): any {
  try {
    return metadata ? JSON.parse(metadata) : {};
  } catch {
    return {};
  }
}
