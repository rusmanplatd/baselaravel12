import { useState, useEffect, useCallback, useRef } from 'react';
import { getUserStorageItem, setUserStorageItem } from '@/utils/localStorage';
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

// Service injection interface
interface ServiceDependencies {
  quantumService?: any;
  optimizedService?: any;
  keyProvider?: QuantumLiveKitKeyProvider;
  logger?: {
    info: (message: string, data?: any) => void;
    warn: (message: string, data?: any) => void;
    error: (message: string, data?: any) => void;
  };
}

interface QuantumLiveKitOptions {
  conversationId: string;
  userId: string;
  enableQuantumE2EE?: boolean;
  keyRotationInterval?: number;
  preferredAlgorithm?: 'ML-KEM-512' | 'ML-KEM-768' | 'ML-KEM-1024';
  serviceDependencies?: ServiceDependencies;
  fallbackToClassical?: boolean;
  debugMode?: boolean;
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
          // Load quantum service with dependency injection
          const quantumService = await loadQuantumService({
            conversationId: options.conversationId,
            dependencies: options.serviceDependencies,
            fallbackToClassical: options.fallbackToClassical ?? true,
            debugMode: options.debugMode ?? false,
            preferredAlgorithm: options.preferredAlgorithm,
          });

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
          try {
            roomOptions.e2ee = {
              keyProvider,
              worker: new Worker('/js/workers/livekit-e2ee-worker.js', { type: 'module' }),
            };
          } catch (error) {
            console.warn('E2EE worker not available, falling back to main thread encryption:', error);
            // Fallback to main thread encryption if worker fails to load
            roomOptions.e2ee = {
              keyProvider,
            };
          }
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
      const duration = callStartTimeRef.current
        ? Date.now() - callStartTimeRef.current.getTime()
        : 0;

      const participantCount = participants.length;
      const quantumParticipants = participants.filter(p => p.quantumEnabled).length;

      // Get WebRTC statistics
      const webrtcStats = await getWebRTCStats(room);
      
      // Get key rotation count from quantum provider
      let keyRotations = 0;
      if (quantumKeyProvider) {
        try {
          const keyStatsData = quantumKeyProvider.getKeyStats();
          keyRotations = keyStatsData.rotationCount || 0;
        } catch (err) {
          console.warn('Failed to get key rotation stats:', err);
        }
      }

      // Calculate encryption health
      let encryptionHealth: CallStats['encryptionHealth'] = 'unknown';
      if (quantumKeyProvider) {
        const quantumRatio = quantumParticipants / Math.max(participantCount, 1);
        const hasRecentRotation = keyRotations > 0;
        const hasGoodConnections = webrtcStats.averagePacketLoss < 0.05; // < 5% packet loss

        if (quantumRatio >= 0.8 && hasRecentRotation && hasGoodConnections) {
          encryptionHealth = 'excellent';
        } else if (quantumRatio >= 0.5 && hasGoodConnections) {
          encryptionHealth = 'good';
        } else if (quantumRatio > 0) {
          encryptionHealth = 'poor';
        } else {
          encryptionHealth = 'degraded';
        }
      } else {
        encryptionHealth = 'degraded';
      }

      setCallStats({
        duration,
        participantCount,
        quantumParticipants,
        averageBitrate: webrtcStats.averageBitrate,
        packetsLost: webrtcStats.totalPacketsLost,
        keyRotations,
        encryptionHealth,
      });
    } catch (err) {
      console.error('Failed to refresh stats:', err);
    }
  }, [room, isConnected, participants, quantumKeyProvider]);

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
async function loadQuantumService(config: {
  conversationId: string;
  dependencies?: ServiceDependencies;
  fallbackToClassical?: boolean;
  debugMode?: boolean;
  preferredAlgorithm?: string;
}): Promise<any> {
  try {
    const { conversationId, dependencies, fallbackToClassical, debugMode, preferredAlgorithm } = config;
    
    // Create logger
    const logger = dependencies?.logger || createDefaultLogger(debugMode);
    
    logger.info('Loading quantum service', { conversationId, preferredAlgorithm });

    // Use injected services or import dynamically
    let optimizedService = dependencies?.optimizedService;
    let quantumService = dependencies?.quantumService;
    
    if (!optimizedService || !quantumService) {
      logger.info('Loading quantum services dynamically');
      
      const [QuantumE2EEService, OptimizedE2EEService] = await Promise.all([
        import('@/services/QuantumE2EEService').then(m => m.QuantumE2EEService),
        import('@/services/OptimizedE2EEService').then(m => m.OptimizedE2EEService)
      ]);

      // Create service instances if not injected
      if (!optimizedService) {
        optimizedService = new OptimizedE2EEService();
      }
      
      if (!quantumService) {
        quantumService = new QuantumE2EEService();
      }
    }

    // Check device capabilities
    const capabilities = optimizedService.getDeviceCapabilities();
    
    if (!capabilities?.quantum_ready) {
      if (fallbackToClassical) {
        logger.warn('Device not quantum-ready, falling back to classical encryption', { capabilities });
      } else {
        throw new Error('Device not quantum-ready for LiveKit E2EE');
      }
    } else {
      logger.info('Device is quantum-ready', { capabilities });
    }

    // Return unified service interface
    return {
      generateKeyPair: async (algorithm: string) => {
        try {
          const response = await fetch('/api/v1/quantum/generate-keypair', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              algorithm: algorithm || 'ML-KEM-768',
              security_level: algorithm === 'ML-KEM-1024' ? 1024 : 
                             algorithm === 'ML-KEM-512' ? 512 : 768
            })
          });

          if (!response.ok) {
            throw new Error(`Key generation failed: ${response.status}`);
          }

          const data = await response.json();
          return {
            public: new Uint8Array(atob(data.public_key).split('').map(c => c.charCodeAt(0))),
            private: new Uint8Array(atob(data.private_key).split('').map(c => c.charCodeAt(0))),
            algorithm: data.algorithm,
            keyId: data.key_id,
          };
        } catch (error) {
          console.error('Quantum key generation failed:', error);
          throw error;
        }
      },

      encapsulate: async (publicKey: Uint8Array, algorithm: string) => {
        try {
          const response = await fetch('/api/v1/quantum/encapsulate', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              public_key: btoa(String.fromCharCode(...publicKey)),
              algorithm: algorithm || 'ML-KEM-768'
            })
          });

          if (!response.ok) {
            throw new Error(`Key encapsulation failed: ${response.status}`);
          }

          const data = await response.json();
          return {
            ciphertext: new Uint8Array(atob(data.ciphertext).split('').map(c => c.charCodeAt(0))),
            shared_secret: new Uint8Array(atob(data.shared_secret).split('').map(c => c.charCodeAt(0))),
            algorithm: data.algorithm,
          };
        } catch (error) {
          console.error('Quantum key encapsulation failed:', error);
          throw error;
        }
      },

      decapsulate: async (ciphertext: Uint8Array, privateKey: Uint8Array, algorithm: string) => {
        try {
          const response = await fetch('/api/v1/quantum/decapsulate', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              ciphertext: btoa(String.fromCharCode(...ciphertext)),
              private_key: btoa(String.fromCharCode(...privateKey)),
              algorithm: algorithm || 'ML-KEM-768'
            })
          });

          if (!response.ok) {
            throw new Error(`Key decapsulation failed: ${response.status}`);
          }

          const data = await response.json();
          return {
            shared_secret: new Uint8Array(atob(data.shared_secret).split('').map(c => c.charCodeAt(0))),
            algorithm: data.algorithm,
          };
        } catch (error) {
          console.error('Quantum key decapsulation failed:', error);
          throw error;
        }
      },

      // Integration with existing services
      optimizedService,
      quantumService,
      capabilities,
      logger,

      // Health monitoring
      getHealthStatus: async () => {
        try {
          const response = await fetch('/api/v1/quantum/health');
          const health = response.ok ? await response.json() : { status: 'degraded' };
          logger.info('Health status checked', health);
          return health;
        } catch (error) {
          logger.warn('Health check failed', { error: error instanceof Error ? error.message : 'Unknown' });
          return { status: 'unavailable' };
        }
      },

      // Performance monitoring
      getPerformanceMetrics: () => {
        const metrics = optimizedService.getPerformanceStats();
        if (debugMode) {
          logger.info('Performance metrics retrieved', metrics);
        }
        return metrics;
      },
      
      // Logging integration
      getLogs: (level?: string) => optimizedService.getErrorLogs(level),
      
      // Service configuration
      getConfig: () => ({
        conversationId,
        quantumReady: capabilities?.quantum_ready,
        fallbackEnabled: fallbackToClassical,
        debugMode,
        preferredAlgorithm,
      }),
      
      // Graceful degradation
      degradeToClassical: () => {
        logger.warn('Degrading to classical encryption');
        return {
          ...result,
          capabilities: { ...capabilities, quantum_ready: false },
          quantumReady: false,
        };
      },
    };
  } catch (error) {
    const logger = config.dependencies?.logger || createDefaultLogger(config.debugMode);
    logger.error('Failed to load quantum service', { 
      error: error instanceof Error ? error.message : 'Unknown error',
      conversationId: config.conversationId 
    });
    
    // If fallback is enabled, return a classical-only service
    if (config.fallbackToClassical) {
      logger.warn('Falling back to classical-only service');
      return createFallbackService(config);
    }
    
    throw new Error(`Quantum service initialization failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
  }
}

function getDeviceId(): string {
  let deviceId = getUserStorageItem('device_id');
  if (!deviceId) {
    deviceId = crypto.randomUUID();
    setUserStorageItem('device_id', deviceId);
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

/**
 * Get comprehensive WebRTC statistics from LiveKit room
 */
async function getWebRTCStats(room: Room): Promise<{
  averageBitrate: number;
  totalPacketsLost: number;
  averagePacketLoss: number;
  jitter: number;
  roundTripTime: number;
}> {
  try {
    // Get sender and receiver statistics
    const senderStats = await getSenderStats(room);
    const receiverStats = await getReceiverStats(room);

    // Calculate aggregated statistics
    const totalBitrate = senderStats.reduce((sum, stat) => sum + (stat.bitrate || 0), 0) +
                        receiverStats.reduce((sum, stat) => sum + (stat.bitrate || 0), 0);
    
    const totalPacketsLost = senderStats.reduce((sum, stat) => sum + (stat.packetsLost || 0), 0) +
                            receiverStats.reduce((sum, stat) => sum + (stat.packetsLost || 0), 0);
    
    const totalPacketsSent = senderStats.reduce((sum, stat) => sum + (stat.packetsSent || 0), 0);
    const totalPacketsReceived = receiverStats.reduce((sum, stat) => sum + (stat.packetsReceived || 0), 0);
    
    const averagePacketLoss = totalPacketsSent > 0 ? 
      totalPacketsLost / (totalPacketsSent + totalPacketsReceived) : 0;

    const averageJitter = receiverStats.length > 0 ?
      receiverStats.reduce((sum, stat) => sum + (stat.jitter || 0), 0) / receiverStats.length : 0;

    const averageRtt = senderStats.length > 0 ?
      senderStats.reduce((sum, stat) => sum + (stat.roundTripTime || 0), 0) / senderStats.length : 0;

    return {
      averageBitrate: Math.round(totalBitrate),
      totalPacketsLost,
      averagePacketLoss: Math.round(averagePacketLoss * 10000) / 100, // Convert to percentage with 2 decimals
      jitter: Math.round(averageJitter * 1000) / 1000, // Round to 3 decimal places
      roundTripTime: Math.round(averageRtt * 1000) / 1000, // Round to 3 decimal places
    };
  } catch (error) {
    console.warn('Failed to get WebRTC stats:', error);
    return {
      averageBitrate: 0,
      totalPacketsLost: 0,
      averagePacketLoss: 0,
      jitter: 0,
      roundTripTime: 0,
    };
  }
}

/**
 * Get sender statistics from all local tracks
 */
async function getSenderStats(room: Room): Promise<any[]> {
  const stats: any[] = [];
  
  try {
    const localTracks = Array.from(room.localParticipant.tracks.values());
    
    for (const trackPub of localTracks) {
      if (trackPub.track && trackPub.track.sender) {
        const senderStats = await trackPub.track.sender.getStats();
        
        for (const report of senderStats.values()) {
          if (report.type === 'outbound-rtp') {
            stats.push({
              trackId: trackPub.trackSid,
              kind: report.mediaType,
              bitrate: calculateBitrate(report),
              packetsLost: report.packetsLost || 0,
              packetsSent: report.packetsSent || 0,
              roundTripTime: report.roundTripTime,
              targetBitrate: report.targetBitrate,
              encoderImplementation: report.encoderImplementation,
            });
          }
        }
      }
    }
  } catch (error) {
    console.warn('Failed to get sender stats:', error);
  }
  
  return stats;
}

/**
 * Get receiver statistics from all remote tracks
 */
async function getReceiverStats(room: Room): Promise<any[]> {
  const stats: any[] = [];
  
  try {
    for (const participant of room.remoteParticipants.values()) {
      const remoteTracks = Array.from(participant.tracks.values());
      
      for (const trackPub of remoteTracks) {
        if (trackPub.track && trackPub.track.receiver) {
          const receiverStats = await trackPub.track.receiver.getStats();
          
          for (const report of receiverStats.values()) {
            if (report.type === 'inbound-rtp') {
              stats.push({
                trackId: trackPub.trackSid,
                participantId: participant.sid,
                kind: report.mediaType,
                bitrate: calculateBitrate(report),
                packetsLost: report.packetsLost || 0,
                packetsReceived: report.packetsReceived || 0,
                jitter: report.jitter,
                decoderImplementation: report.decoderImplementation,
                framesDecoded: report.framesDecoded,
                framesDropped: report.framesDropped,
              });
            }
          }
        }
      }
    }
  } catch (error) {
    console.warn('Failed to get receiver stats:', error);
  }
  
  return stats;
}

/**
 * Calculate bitrate from RTC stats report
 */
function calculateBitrate(report: any): number {
  if (!report.timestamp || !report.bytesReceived && !report.bytesSent) {
    return 0;
  }
  
  const bytes = report.bytesReceived || report.bytesSent || 0;
  const bits = bytes * 8;
  
  // Store previous values for rate calculation
  const key = `${report.ssrc}_${report.type}`;
  const now = report.timestamp;
  const prev = (globalThis as any).__webrtcStatsCache?.[key];
  
  if (!prev) {
    // Initialize cache
    if (!(globalThis as any).__webrtcStatsCache) {
      (globalThis as any).__webrtcStatsCache = {};
    }
    (globalThis as any).__webrtcStatsCache[key] = { bits, timestamp: now };
    return 0;
  }
  
  const timeDelta = (now - prev.timestamp) / 1000; // Convert to seconds
  const bitsDelta = bits - prev.bits;
  
  // Update cache
  (globalThis as any).__webrtcStatsCache[key] = { bits, timestamp: now };
  
  return timeDelta > 0 ? Math.round(bitsDelta / timeDelta) : 0;
}

/**
 * Create default logger
 */
function createDefaultLogger(debugMode: boolean = false) {
  const prefix = '[QuantumLiveKit]';
  
  return {
    info: (message: string, data?: any) => {
      if (debugMode) {
        console.log(`${prefix} ${message}`, data || '');
      }
    },
    warn: (message: string, data?: any) => {
      console.warn(`${prefix} ${message}`, data || '');
    },
    error: (message: string, data?: any) => {
      console.error(`${prefix} ${message}`, data || '');
    },
  };
}

/**
 * Create fallback service for classical encryption only
 */
function createFallbackService(config: {
  conversationId: string;
  dependencies?: ServiceDependencies;
  debugMode?: boolean;
}): any {
  const logger = config.dependencies?.logger || createDefaultLogger(config.debugMode);
  
  logger.info('Creating fallback classical-only service', { conversationId: config.conversationId });
  
  return {
    generateKeyPair: async (algorithm: string) => {
      // Generate AES key for classical encryption
      const key = crypto.getRandomValues(new Uint8Array(32));
      
      return {
        public: key,
        private: key,
        algorithm: 'AES-256-GCM',
        keyId: crypto.randomUUID(),
      };
    },

    encapsulate: async (publicKey: Uint8Array, algorithm: string) => {
      // For classical fallback, just return the shared key
      const shared_secret = crypto.getRandomValues(new Uint8Array(32));
      
      return {
        ciphertext: publicKey, // Just echo back for compatibility
        shared_secret,
        algorithm: 'AES-256-GCM',
      };
    },

    decapsulate: async (ciphertext: Uint8Array, privateKey: Uint8Array, algorithm: string) => {
      // For classical fallback, derive shared secret from private key
      const shared_secret = privateKey.slice(0, 32);
      
      return {
        shared_secret,
        algorithm: 'AES-256-GCM',
      };
    },

    // Fallback service properties
    optimizedService: null,
    quantumService: null,
    capabilities: {
      quantum_ready: false,
      supported_algorithms: ['AES-256-GCM'],
      hardware_security: false,
      performance_tier: 'low',
    },
    logger,

    getHealthStatus: async () => ({
      status: 'classical',
      quantum_ready: false,
      fallback_active: true,
    }),

    getPerformanceMetrics: () => ({
      averageEncryptionTime: 0,
      averageDecryptionTime: 0,
      totalOperations: 0,
      keyRotations: 0,
      fallbackMode: true,
    }),

    getLogs: () => [],

    getConfig: () => ({
      conversationId: config.conversationId,
      quantumReady: false,
      fallbackEnabled: true,
      debugMode: config.debugMode || false,
      mode: 'fallback',
    }),

    degradeToClassical: () => {
      logger.info('Already in classical fallback mode');
      return this;
    },
  };
}
