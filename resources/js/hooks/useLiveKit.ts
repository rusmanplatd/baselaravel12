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
  E2EEOptions,
  KeyProvider,
  BaseKeyProvider,
  ParticipantEvent
} from 'livekit-client';

interface LiveKitHookReturn {
  room: Room | null;
  isConnected: boolean;
  isConnecting: boolean;
  connect: (roomName: string, token: string, options?: ConnectOptions) => Promise<void>;
  disconnect: () => Promise<void>;
  publishTrack: (source: 'camera' | 'microphone' | 'screen') => Promise<void>;
  unpublishTrack: (source: 'camera' | 'microphone' | 'screen') => Promise<void>;
  localTracks: LocalTrack[];
  remoteTracks: RemoteTrack[];
  participants: RemoteParticipant[];
  error: string | null;
}

export const useLiveKit = (): LiveKitHookReturn => {
  const [room, setRoom] = useState<Room | null>(null);
  const [isConnected, setIsConnected] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const [localTracks, setLocalTracks] = useState<LocalTrack[]>([]);
  const [remoteTracks, setRemoteTracks] = useState<RemoteTrack[]>([]);
  const [participants, setParticipants] = useState<RemoteParticipant[]>([]);
  const [error, setError] = useState<string | null>(null);
  
  const localVideoTrackRef = useRef<LocalTrack | null>(null);
  const localAudioTrackRef = useRef<LocalTrack | null>(null);

  // Initialize room
  useEffect(() => {
    const newRoom = new Room({
      // Enable auto-manage for audio and video
      adaptiveStream: true,
      dynacast: true,
      // End-to-end encryption settings
      e2ee: {
        keyProvider: undefined, // Would be configured with actual E2EE key provider
        worker: undefined,
      },
    } as RoomOptions);

    setRoom(newRoom);

    // Room event listeners
    newRoom.on(RoomEvent.Connected, () => {
      setIsConnected(true);
      setIsConnecting(false);
      setError(null);
    });

    newRoom.on(RoomEvent.Disconnected, () => {
      setIsConnected(false);
      setIsConnecting(false);
      setLocalTracks([]);
      setRemoteTracks([]);
      setParticipants([]);
    });

    newRoom.on(RoomEvent.ParticipantConnected, (participant: RemoteParticipant) => {
      setParticipants(prev => [...prev, participant]);
    });

    newRoom.on(RoomEvent.ParticipantDisconnected, (participant: RemoteParticipant) => {
      setParticipants(prev => prev.filter(p => p.sid !== participant.sid));
    });

    newRoom.on(RoomEvent.TrackSubscribed, (track: RemoteTrack) => {
      if (track.kind === Track.Kind.Video || track.kind === Track.Kind.Audio) {
        setRemoteTracks(prev => [...prev, track]);
      }
    });

    newRoom.on(RoomEvent.TrackUnsubscribed, (track: RemoteTrack) => {
      setRemoteTracks(prev => prev.filter(t => t.sid !== track.sid));
    });

    newRoom.on(RoomEvent.ConnectionQualityChanged, (quality, participant) => {
      // Handle connection quality changes
      console.log('Connection quality changed:', quality, participant?.identity);
    });

    newRoom.on(RoomEvent.Disconnected, (reason) => {
      if (reason) {
        setError(`Disconnected: ${reason}`);
      }
    });

    return () => {
      newRoom.disconnect();
      setRoom(null);
    };
  }, []);

  const connect = useCallback(async (roomName: string, token: string, options?: ConnectOptions) => {
    if (!room || isConnecting) return;

    try {
      setIsConnecting(true);
      setError(null);

      const liveKitUrl = import.meta.env.VITE_LIVEKIT_URL || 'ws://localhost:7880';
      
      await room.connect(liveKitUrl, token, {
        autoSubscribe: true,
        maxRetries: 3,
        ...options,
      });
      
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to connect';
      setError(errorMessage);
      setIsConnecting(false);
      throw err;
    }
  }, [room, isConnecting]);

  const disconnect = useCallback(async () => {
    if (!room) return;

    try {
      // Unpublish all local tracks
      if (localVideoTrackRef.current) {
        room.localParticipant.unpublishTrack(localVideoTrackRef.current);
        await localVideoTrackRef.current.stop();
        localVideoTrackRef.current = null;
      }

      if (localAudioTrackRef.current) {
        room.localParticipant.unpublishTrack(localAudioTrackRef.current);
        await localAudioTrackRef.current.stop();
        localAudioTrackRef.current = null;
      }

      await room.disconnect();
    } catch (err) {
      console.error('Error disconnecting:', err);
    }
  }, [room]);

  const publishTrack = useCallback(async (source: 'camera' | 'microphone' | 'screen') => {
    if (!room || !isConnected) return;

    try {
      let track: LocalTrack;

      switch (source) {
        case 'camera':
          if (localVideoTrackRef.current) return; // Already published
          
          track = await createLocalVideoTrack({
            resolution: VideoPresets.h720.resolution,
            facingMode: 'user',
          });
          
          localVideoTrackRef.current = track;
          await room.localParticipant.publishTrack(track, {
            name: 'camera',
            source: Track.Source.Camera,
          });
          break;

        case 'microphone':
          if (localAudioTrackRef.current) return; // Already published
          
          track = await createLocalAudioTrack({
            echoCancellation: true,
            noiseSuppression: true,
          });
          
          localAudioTrackRef.current = track;
          await room.localParticipant.publishTrack(track, {
            name: 'microphone',
            source: Track.Source.Microphone,
          });
          break;

        case 'screen':
          // Screen sharing implementation would go here
          track = await navigator.mediaDevices.getDisplayMedia({
            video: true,
            audio: true,
          }).then(stream => {
            const videoTrack = stream.getVideoTracks()[0];
            return new LocalTrack(
              new MediaStreamTrack(), // Placeholder - would use actual track
              Track.Kind.Video,
              { source: Track.Source.ScreenShare }
            );
          });
          
          await room.localParticipant.publishTrack(track, {
            name: 'screen',
            source: Track.Source.ScreenShare,
          });
          break;
      }

      setLocalTracks(prev => [...prev, track]);
      
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to publish track';
      setError(errorMessage);
      throw err;
    }
  }, [room, isConnected]);

  const unpublishTrack = useCallback(async (source: 'camera' | 'microphone' | 'screen') => {
    if (!room || !isConnected) return;

    try {
      let trackRef: { current: LocalTrack | null };

      switch (source) {
        case 'camera':
          trackRef = localVideoTrackRef;
          break;
        case 'microphone':
          trackRef = localAudioTrackRef;
          break;
        case 'screen':
          // Find screen share track
          const screenTrack = localTracks.find(t => 
            t.source === Track.Source.ScreenShare
          );
          if (screenTrack) {
            room.localParticipant.unpublishTrack(screenTrack);
            await screenTrack.stop();
            setLocalTracks(prev => prev.filter(t => t.sid !== screenTrack.sid));
          }
          return;
      }

      if (trackRef.current) {
        room.localParticipant.unpublishTrack(trackRef.current);
        await trackRef.current.stop();
        setLocalTracks(prev => prev.filter(t => t.sid !== trackRef.current?.sid));
        trackRef.current = null;
      }
      
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to unpublish track';
      setError(errorMessage);
      throw err;
    }
  }, [room, isConnected, localTracks]);

  return {
    room,
    isConnected,
    isConnecting,
    connect,
    disconnect,
    publishTrack,
    unpublishTrack,
    localTracks,
    remoteTracks,
    participants,
    error,
  };
};