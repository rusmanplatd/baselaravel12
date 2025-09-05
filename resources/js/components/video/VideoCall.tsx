import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Video,
  VideoOff,
  Mic,
  MicOff,
  Phone,
  PhoneOff,
  Settings,
  Users,
  Monitor,
  Maximize,
  Minimize,
  Volume2,
  VolumeX,
  Shield,
  AlertCircle
} from 'lucide-react';
import { VideoCallData, CallParticipant, CallStatus } from '@/types/video';
import { useVideoCallApi } from '@/hooks/useVideoCallApi';
import { useLiveKit } from '@/hooks/useLiveKit';
import { toast } from 'sonner';

interface VideoCallProps {
  conversationId: string;
  call: VideoCallData | null;
  onCallEnd?: () => void;
  className?: string;
}

const VideoCall: React.FC<VideoCallProps> = ({
  conversationId,
  call,
  onCallEnd,
  className
}) => {
  const [isVideoEnabled, setIsVideoEnabled] = useState(true);
  const [isAudioEnabled, setIsAudioEnabled] = useState(true);
  const [isSpeakerEnabled, setIsSpeakerEnabled] = useState(true);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showParticipants, setShowParticipants] = useState(false);
  const [participants, setParticipants] = useState<CallParticipant[]>([]);
  const [callStatus, setCallStatus] = useState<CallStatus>('connecting');
  const [connectionQuality, setConnectionQuality] = useState<'excellent' | 'good' | 'poor'>('good');
  const [encryptionStatus, setEncryptionStatus] = useState<'encrypted' | 'unencrypted'>('encrypted');
  
  const localVideoRef = useRef<HTMLVideoElement>(null);
  const remoteVideosRef = useRef<{ [key: string]: HTMLVideoElement }>({});
  
  const { initiateCall, joinCall, leaveCall, endCall, updateQualityMetrics } = useVideoCallApi();
  const {
    room,
    connect,
    disconnect,
    publishTrack,
    unpublishTrack,
    isConnected,
    localTracks,
    remoteTracks,
    participants: liveKitParticipants
  } = useLiveKit();

  // Initialize call connection
  useEffect(() => {
    if (call && call.livekit_room_name && call.status === 'active') {
      connectToCall();
    }
    
    return () => {
      if (isConnected) {
        disconnect();
      }
    };
  }, [call]);

  // Update participants when LiveKit participants change
  useEffect(() => {
    const updatedParticipants = liveKitParticipants.map(p => ({
      id: p.identity,
      name: p.name || p.identity,
      joined_at: new Date().toISOString(),
      left_at: null,
      status: 'joined' as const,
      video_enabled: p.videoTracks.size > 0,
      audio_enabled: p.audioTracks.size > 0,
      is_speaking: false, // Would be updated by audio level detection
      connection_quality: 'good' as const
    }));
    
    setParticipants(updatedParticipants);
  }, [liveKitParticipants]);

  const connectToCall = useCallback(async () => {
    if (!call) return;
    
    try {
      setCallStatus('connecting');
      
      // Join the call through API to get access token
      const joinResult = await joinCall(call.id);
      
      // Connect to LiveKit room
      await connect(call.livekit_room_name, joinResult.access_token);
      
      setCallStatus('connected');
      toast.success('Connected to call');
      
      // Start with video and audio enabled
      if (isVideoEnabled) {
        await publishTrack('camera');
      }
      if (isAudioEnabled) {
        await publishTrack('microphone');
      }
      
    } catch (error) {
      console.error('Failed to connect to call:', error);
      setCallStatus('failed');
      toast.error('Failed to connect to call');
    }
  }, [call, connect, joinCall, isVideoEnabled, isAudioEnabled]);

  const handleToggleVideo = async () => {
    try {
      if (isVideoEnabled) {
        await unpublishTrack('camera');
      } else {
        await publishTrack('camera');
      }
      setIsVideoEnabled(!isVideoEnabled);
    } catch (error) {
      toast.error('Failed to toggle video');
    }
  };

  const handleToggleAudio = async () => {
    try {
      if (isAudioEnabled) {
        await unpublishTrack('microphone');
      } else {
        await publishTrack('microphone');
      }
      setIsAudioEnabled(!isAudioEnabled);
    } catch (error) {
      toast.error('Failed to toggle audio');
    }
  };

  const handleEndCall = async () => {
    try {
      if (call) {
        await endCall(call.id);
      }
      
      await disconnect();
      setCallStatus('ended');
      onCallEnd?.();
      
    } catch (error) {
      console.error('Failed to end call:', error);
      toast.error('Failed to end call');
    }
  };

  const handleLeaveCall = async () => {
    try {
      if (call) {
        await leaveCall(call.id);
      }
      
      await disconnect();
      setCallStatus('left');
      onCallEnd?.();
      
    } catch (error) {
      console.error('Failed to leave call:', error);
      toast.error('Failed to leave call');
    }
  };

  const toggleFullscreen = () => {
    if (!isFullscreen) {
      document.documentElement.requestFullscreen?.();
    } else {
      document.exitFullscreen?.();
    }
    setIsFullscreen(!isFullscreen);
  };

  const getStatusColor = (status: CallStatus) => {
    switch (status) {
      case 'connected': return 'default';
      case 'connecting': return 'secondary';
      case 'failed': return 'destructive';
      case 'ended': return 'outline';
      default: return 'secondary';
    }
  };

  const getQualityColor = (quality: string) => {
    switch (quality) {
      case 'excellent': return 'text-green-500';
      case 'good': return 'text-yellow-500';
      case 'poor': return 'text-red-500';
      default: return 'text-gray-500';
    }
  };

  if (!call) {
    return null;
  }

  return (
    <div className={`relative ${isFullscreen ? 'fixed inset-0 z-50 bg-black' : ''} ${className}`}>
      <Card className={`${isFullscreen ? 'h-full border-0' : 'h-[600px]'}`}>
        <CardContent className="p-0 h-full relative">
          {/* Video Container */}
          <div className="relative h-full bg-gray-900 rounded-lg overflow-hidden">
            {/* Remote Video Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 h-full gap-1">
              {remoteTracks.map((track, index) => (
                <div key={track.sid} className="relative bg-gray-800 rounded">
                  <video
                    ref={el => {
                      if (el) remoteVideosRef.current[track.sid] = el;
                    }}
                    className="w-full h-full object-cover"
                    autoPlay
                    playsInline
                    muted={false}
                  />
                  <div className="absolute bottom-2 left-2">
                    <Badge variant="secondary">
                      {track.participant?.name || 'Participant'}
                    </Badge>
                  </div>
                  {/* Participant Controls Overlay */}
                  <div className="absolute top-2 right-2 flex gap-1">
                    {track.participant?.audioTracks.size === 0 && (
                      <MicOff className="h-4 w-4 text-red-500" />
                    )}
                    {track.participant?.videoTracks.size === 0 && (
                      <VideoOff className="h-4 w-4 text-red-500" />
                    )}
                  </div>
                </div>
              ))}
              
              {/* Show placeholder if no remote participants */}
              {remoteTracks.length === 0 && (
                <div className="flex items-center justify-center h-full bg-gray-800 rounded">
                  <div className="text-center text-gray-400">
                    <Users className="h-12 w-12 mx-auto mb-2" />
                    <p>Waiting for other participants...</p>
                  </div>
                </div>
              )}
            </div>

            {/* Local Video (Picture-in-Picture) */}
            <div className="absolute bottom-4 right-4 w-48 h-36 bg-gray-800 rounded-lg overflow-hidden border-2 border-gray-600">
              {isVideoEnabled ? (
                <video
                  ref={localVideoRef}
                  className="w-full h-full object-cover"
                  autoPlay
                  playsInline
                  muted
                />
              ) : (
                <div className="flex items-center justify-center h-full">
                  <VideoOff className="h-8 w-8 text-gray-400" />
                </div>
              )}
              <div className="absolute bottom-1 left-1">
                <Badge variant="outline" className="text-xs">
                  You
                </Badge>
              </div>
            </div>

            {/* Call Status Overlay */}
            <div className="absolute top-4 left-4 flex items-center gap-2">
              <Badge variant={getStatusColor(callStatus)}>
                {callStatus}
              </Badge>
              
              {/* Encryption Status */}
              <Badge variant="outline" className="flex items-center gap-1">
                <Shield className="h-3 w-3" />
                {encryptionStatus === 'encrypted' ? 'E2EE' : 'Unencrypted'}
              </Badge>
              
              {/* Connection Quality */}
              <Badge variant="outline" className={`flex items-center gap-1 ${getQualityColor(connectionQuality)}`}>
                <div className="w-2 h-2 rounded-full bg-current" />
                {connectionQuality}
              </Badge>
              
              {/* Participant Count */}
              <Badge variant="outline" className="flex items-center gap-1">
                <Users className="h-3 w-3" />
                {participants.length + 1}
              </Badge>
            </div>

            {/* Call Controls */}
            <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2">
              <div className="flex items-center gap-3 bg-black/50 backdrop-blur-sm rounded-full px-6 py-3">
                {/* Audio Toggle */}
                <Button
                  variant={isAudioEnabled ? "secondary" : "destructive"}
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={handleToggleAudio}
                >
                  {isAudioEnabled ? <Mic className="h-5 w-5" /> : <MicOff className="h-5 w-5" />}
                </Button>

                {/* Video Toggle */}
                <Button
                  variant={isVideoEnabled ? "secondary" : "destructive"}
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={handleToggleVideo}
                >
                  {isVideoEnabled ? <Video className="h-5 w-5" /> : <VideoOff className="h-5 w-5" />}
                </Button>

                {/* Speaker Toggle */}
                <Button
                  variant={isSpeakerEnabled ? "secondary" : "outline"}
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={() => setIsSpeakerEnabled(!isSpeakerEnabled)}
                >
                  {isSpeakerEnabled ? <Volume2 className="h-5 w-5" /> : <VolumeX className="h-5 w-5" />}
                </Button>

                {/* End Call */}
                <Button
                  variant="destructive"
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={handleEndCall}
                >
                  <PhoneOff className="h-5 w-5" />
                </Button>

                {/* Leave Call */}
                <Button
                  variant="outline"
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={handleLeaveCall}
                >
                  <Phone className="h-5 w-5 rotate-180" />
                </Button>

                {/* Settings */}
                <Button
                  variant="outline"
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={() => {/* Open settings */}}
                >
                  <Settings className="h-5 w-5" />
                </Button>

                {/* Participants List */}
                <Button
                  variant="outline"
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={() => setShowParticipants(true)}
                >
                  <Users className="h-5 w-5" />
                </Button>

                {/* Fullscreen Toggle */}
                <Button
                  variant="outline"
                  size="sm"
                  className="rounded-full w-12 h-12"
                  onClick={toggleFullscreen}
                >
                  {isFullscreen ? <Minimize className="h-5 w-5" /> : <Maximize className="h-5 w-5" />}
                </Button>
              </div>
            </div>

            {/* Connection Quality Warning */}
            {connectionQuality === 'poor' && (
              <div className="absolute top-4 right-4">
                <div className="bg-red-500/90 text-white px-3 py-2 rounded-lg flex items-center gap-2">
                  <AlertCircle className="h-4 w-4" />
                  <span className="text-sm">Poor connection</span>
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Participants Dialog */}
      <Dialog open={showParticipants} onOpenChange={setShowParticipants}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Call Participants</DialogTitle>
            <DialogDescription>
              {participants.length + 1} participants in this call
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            {/* Current User */}
            <div className="flex items-center justify-between p-3 bg-muted rounded-lg">
              <div className="flex items-center gap-3">
                <Avatar>
                  <AvatarFallback>You</AvatarFallback>
                </Avatar>
                <div>
                  <div className="font-medium">You</div>
                  <div className="text-sm text-muted-foreground">Host</div>
                </div>
              </div>
              <div className="flex gap-1">
                {!isAudioEnabled && <MicOff className="h-4 w-4 text-red-500" />}
                {!isVideoEnabled && <VideoOff className="h-4 w-4 text-red-500" />}
              </div>
            </div>
            
            {/* Other Participants */}
            {participants.map((participant) => (
              <div key={participant.id} className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center gap-3">
                  <Avatar>
                    <AvatarFallback>
                      {participant.name.charAt(0).toUpperCase()}
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <div className="font-medium">{participant.name}</div>
                    <div className="text-sm text-muted-foreground">
                      {participant.status}
                    </div>
                  </div>
                </div>
                <div className="flex gap-1">
                  {!participant.audio_enabled && <MicOff className="h-4 w-4 text-red-500" />}
                  {!participant.video_enabled && <VideoOff className="h-4 w-4 text-red-500" />}
                  <div className={`w-2 h-2 rounded-full ${getQualityColor(participant.connection_quality)} bg-current`} />
                </div>
              </div>
            ))}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default VideoCall;