import { useState, useEffect, useRef } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { 
  Video, 
  VideoOff, 
  Mic, 
  MicOff, 
  PhoneOff, 
  Users, 
  MessageCircle, 
  Share, 
  Settings, 
  MoreVertical,
  Grid3X3,
  Layout,
  Shield,
  Bookmark,
  Calendar,
  Clock,
  Volume2,
  VolumeX,
  Maximize,
  Minimize,
  RefreshCw,
  AlertTriangle,
  CheckCircle,
  Info,
  Camera,
  Monitor,
  Hand,
  Copy,
  LinkIcon,
  QrCode,
  FileText,
  Download
} from 'lucide-react';
import MeetingLayoutManager from './MeetingLayoutManager';
import BreakoutRoomManager from './BreakoutRoomManager';
import MeetingRecordingControls from './MeetingRecordingControls';
import MeetingSecurityDashboard from './MeetingSecurityDashboard';
import MeetingTemplatesManager from './MeetingTemplatesManager';
import apiService from '@/services/ApiService';

interface Meeting {
  id: string;
  title: string;
  description?: string;
  start_time: string;
  end_time: string;
  timezone: string;
  status: 'scheduled' | 'in_progress' | 'ended' | 'cancelled';
  host_id: string;
  host_name: string;
  participants_count: number;
  max_participants?: number;
  meeting_url: string;
  meeting_id_display: string;
  passcode?: string;
  room_name: string;
  settings: {
    waiting_room: boolean;
    password_required: boolean;
    recording_enabled: boolean;
    chat_enabled: boolean;
    screen_sharing: boolean;
    breakout_rooms: boolean;
  };
}

interface Participant {
  id: string;
  user_id: string;
  name: string;
  email: string;
  role: 'host' | 'participant';
  status: 'joined' | 'waiting' | 'left';
  audio_enabled: boolean;
  video_enabled: boolean;
  screen_sharing: boolean;
  hand_raised: boolean;
  joined_at: string;
}

interface MeetingStats {
  duration: number;
  peak_participants: number;
  total_messages: number;
  recordings_count: number;
  breakout_sessions: number;
  screen_shares: number;
}

interface MeetingInterfaceProps {
  meetingId: string;
  userId: string;
  userName: string;
  isHost: boolean;
  onMeetingEnd?: () => void;
  className?: string;
}

export default function MeetingInterface({ 
  meetingId, 
  userId, 
  userName, 
  isHost, 
  onMeetingEnd, 
  className = '' 
}: MeetingInterfaceProps) {
  const [meeting, setMeeting] = useState<Meeting | null>(null);
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [meetingStats, setMeetingStats] = useState<MeetingStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [connected, setConnected] = useState(false);
  const [localVideoEnabled, setLocalVideoEnabled] = useState(false);
  const [localAudioEnabled, setLocalAudioEnabled] = useState(true);
  const [screenSharing, setScreenSharing] = useState(false);
  const [handRaised, setHandRaised] = useState(false);
  const [chatOpen, setChatOpen] = useState(false);
  const [participantsOpen, setParticipantsOpen] = useState(false);
  const [fullscreen, setFullscreen] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [activeTab, setActiveTab] = useState('video');
  const [connectionStatus, setConnectionStatus] = useState<'connecting' | 'connected' | 'disconnected' | 'failed'>('connecting');
  const [currentLayout, setCurrentLayout] = useState('gallery');
  
  const videoRef = useRef<HTMLDivElement>(null);
  const meetingContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    loadMeeting();
    connectToMeeting();
    
    const interval = setInterval(() => {
      loadParticipants();
      loadMeetingStats();
    }, 5000);

    return () => {
      clearInterval(interval);
      disconnectFromMeeting();
    };
  }, [meetingId]);

  const loadMeeting = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}`);
      if (response.meeting) {
        setMeeting(response.meeting);
      }
    } catch (error) {
      console.error('Failed to load meeting:', error);
    }
  };

  const loadParticipants = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/participants`);
      if (response.participants) {
        setParticipants(response.participants);
      }
    } catch (error) {
      console.error('Failed to load participants:', error);
    }
  };

  const loadMeetingStats = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/stats`);
      if (response.stats) {
        setMeetingStats(response.stats);
      }
    } catch (error) {
      console.error('Failed to load meeting stats:', error);
    }
  };

  const connectToMeeting = async () => {
    setConnectionStatus('connecting');
    try {
      // Initialize LiveKit connection
      const response = await apiService.post(`/api/meetings/${meetingId}/join`, {
        user_id: userId,
        user_name: userName
      });

      if (response.success) {
        setConnected(true);
        setConnectionStatus('connected');
        await loadParticipants();
      }
    } catch (error) {
      console.error('Failed to connect to meeting:', error);
      setConnectionStatus('failed');
    } finally {
      setLoading(false);
    }
  };

  const disconnectFromMeeting = async () => {
    try {
      await apiService.post(`/api/meetings/${meetingId}/leave`, {
        user_id: userId
      });
      setConnected(false);
      setConnectionStatus('disconnected');
    } catch (error) {
      console.error('Failed to disconnect from meeting:', error);
    }
  };

  const toggleVideo = async () => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/video/toggle`, {
        enabled: !localVideoEnabled
      });
      if (response.success) {
        setLocalVideoEnabled(!localVideoEnabled);
      }
    } catch (error) {
      console.error('Failed to toggle video:', error);
    }
  };

  const toggleAudio = async () => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/audio/toggle`, {
        enabled: !localAudioEnabled
      });
      if (response.success) {
        setLocalAudioEnabled(!localAudioEnabled);
      }
    } catch (error) {
      console.error('Failed to toggle audio:', error);
    }
  };

  const toggleScreenShare = async () => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/screen-share/toggle`, {
        enabled: !screenSharing
      });
      if (response.success) {
        setScreenSharing(!screenSharing);
      }
    } catch (error) {
      console.error('Failed to toggle screen share:', error);
    }
  };

  const toggleHandRaise = async () => {
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/hand/toggle`, {
        raised: !handRaised
      });
      if (response.success) {
        setHandRaised(!handRaised);
      }
    } catch (error) {
      console.error('Failed to toggle hand raise:', error);
    }
  };

  const endMeeting = async () => {
    if (!isHost) return;
    
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/end`);
      if (response.success) {
        onMeetingEnd?.();
      }
    } catch (error) {
      console.error('Failed to end meeting:', error);
    }
  };

  const leaveMeeting = async () => {
    await disconnectFromMeeting();
    onMeetingEnd?.();
  };

  const copyMeetingInfo = async () => {
    if (!meeting) return;
    
    const info = `Meeting: ${meeting.title}
Meeting ID: ${meeting.meeting_id_display}
${meeting.passcode ? `Passcode: ${meeting.passcode}` : ''}
Join URL: ${meeting.meeting_url}`;
    
    try {
      await navigator.clipboard.writeText(info);
    } catch (error) {
      console.error('Failed to copy meeting info:', error);
    }
  };

  const toggleFullscreen = () => {
    if (!fullscreen) {
      meetingContainerRef.current?.requestFullscreen();
    } else {
      document.exitFullscreen();
    }
    setFullscreen(!fullscreen);
  };

  if (loading) {
    return (
      <Card className={className}>
        <CardContent className="flex items-center justify-center p-8">
          <div className="text-center space-y-4">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900 mx-auto"></div>
            <div>
              <h3 className="font-medium">Connecting to meeting...</h3>
              <p className="text-sm text-gray-500 mt-1">Please wait while we join you to the meeting</p>
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!meeting) {
    return (
      <Card className={className}>
        <CardContent className="flex items-center justify-center p-8">
          <Alert>
            <AlertTriangle className="h-4 w-4" />
            <AlertDescription>
              Meeting not found or you don't have permission to join this meeting.
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    );
  }

  const getConnectionStatusBadge = () => {
    switch (connectionStatus) {
      case 'connecting':
        return <Badge variant="secondary"><RefreshCw className="h-3 w-3 mr-1 animate-spin" />Connecting</Badge>;
      case 'connected':
        return <Badge variant="default"><CheckCircle className="h-3 w-3 mr-1" />Connected</Badge>;
      case 'disconnected':
        return <Badge variant="secondary">Disconnected</Badge>;
      case 'failed':
        return <Badge variant="destructive"><AlertTriangle className="h-3 w-3 mr-1" />Failed</Badge>;
      default:
        return null;
    }
  };

  const activeParticipants = participants.filter(p => p.status === 'joined');
  const waitingParticipants = participants.filter(p => p.status === 'waiting');

  return (
    <div 
      ref={meetingContainerRef}
      className={`${className} ${fullscreen ? 'fixed inset-0 z-50 bg-black' : ''}`}
    >
      {/* Header */}
      <div className="flex items-center justify-between p-4 bg-white border-b">
        <div className="flex items-center space-x-4">
          <div>
            <h1 className="text-xl font-semibold">{meeting.title}</h1>
            <div className="flex items-center space-x-3 text-sm text-gray-500">
              <span>ID: {meeting.meeting_id_display}</span>
              {getConnectionStatusBadge()}
              <span>{activeParticipants.length} participants</span>
              {meetingStats && (
                <span>{Math.floor(meetingStats.duration / 60)}m elapsed</span>
              )}
            </div>
          </div>
        </div>

        <div className="flex items-center space-x-2">
          <Dialog>
            <DialogTrigger asChild>
              <Button variant="outline" size="sm">
                <Info className="h-4 w-4" />
                Meeting Info
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Meeting Information</DialogTitle>
                <DialogDescription>Share this information with participants</DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label className="text-sm font-medium">Meeting Title</Label>
                  <p className="text-sm">{meeting.title}</p>
                </div>
                <div>
                  <Label className="text-sm font-medium">Meeting ID</Label>
                  <p className="text-sm font-mono">{meeting.meeting_id_display}</p>
                </div>
                {meeting.passcode && (
                  <div>
                    <Label className="text-sm font-medium">Passcode</Label>
                    <p className="text-sm font-mono">{meeting.passcode}</p>
                  </div>
                )}
                <div>
                  <Label className="text-sm font-medium">Join URL</Label>
                  <p className="text-sm break-all">{meeting.meeting_url}</p>
                </div>
                <div className="flex space-x-2">
                  <Button variant="outline" onClick={copyMeetingInfo} className="flex-1">
                    <Copy className="h-4 w-4" />
                    Copy Info
                  </Button>
                  <Button variant="outline" className="flex-1">
                    <QrCode className="h-4 w-4" />
                    QR Code
                  </Button>
                </div>
              </div>
            </DialogContent>
          </Dialog>

          <Button
            variant="outline"
            size="sm"
            onClick={toggleFullscreen}
          >
            {fullscreen ? <Minimize className="h-4 w-4" /> : <Maximize className="h-4 w-4" />}
          </Button>

          <Sheet open={showSettings} onOpenChange={setShowSettings}>
            <SheetTrigger asChild>
              <Button variant="outline" size="sm">
                <Settings className="h-4 w-4" />
              </Button>
            </SheetTrigger>
            <SheetContent className="w-96">
              <SheetHeader>
                <SheetTitle>Meeting Settings</SheetTitle>
                <SheetDescription>Manage meeting configuration and features</SheetDescription>
              </SheetHeader>
              <div className="mt-6">
                <Tabs defaultValue="general" className="w-full">
                  <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="security">Security</TabsTrigger>
                    <TabsTrigger value="recording">Recording</TabsTrigger>
                  </TabsList>
                  <TabsContent value="general" className="space-y-4">
                    <MeetingLayoutManager
                      meetingId={meetingId}
                      isHost={isHost}
                      onLayoutChange={(layout) => setCurrentLayout(layout.layout_type)}
                    />
                  </TabsContent>
                  <TabsContent value="security">
                    <MeetingSecurityDashboard
                      meetingId={meetingId}
                      isHost={isHost}
                    />
                  </TabsContent>
                  <TabsContent value="recording">
                    <MeetingRecordingControls
                      meetingId={meetingId}
                      isHost={isHost}
                    />
                  </TabsContent>
                </Tabs>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>

      <div className="flex flex-1">
        {/* Main Video Area */}
        <div className="flex-1 relative">
          <div 
            ref={videoRef} 
            className="w-full h-[calc(100vh-200px)] bg-gray-900 relative overflow-hidden"
          >
            {/* Video Grid/Layout */}
            <div className={`w-full h-full grid ${
              currentLayout === 'gallery' ? 'grid-cols-3 grid-rows-2' :
              currentLayout === 'speaker' ? 'grid-cols-1' :
              'grid-cols-2'
            } gap-2 p-4`}>
              {activeParticipants.map((participant) => (
                <div
                  key={participant.id}
                  className="relative bg-gray-800 rounded-lg overflow-hidden"
                >
                  {/* Video placeholder */}
                  <div className="w-full h-full flex items-center justify-center">
                    <div className="text-center text-white">
                      {participant.video_enabled ? (
                        <Camera className="h-8 w-8 mx-auto mb-2" />
                      ) : (
                        <VideoOff className="h-8 w-8 mx-auto mb-2" />
                      )}
                      <p className="text-sm">{participant.name}</p>
                    </div>
                  </div>
                  
                  {/* Participant controls overlay */}
                  <div className="absolute bottom-2 left-2 flex items-center space-x-1">
                    {!participant.audio_enabled && (
                      <Badge variant="destructive" className="text-xs">
                        <MicOff className="h-3 w-3" />
                      </Badge>
                    )}
                    {participant.screen_sharing && (
                      <Badge variant="default" className="text-xs">
                        <Monitor className="h-3 w-3" />
                      </Badge>
                    )}
                    {participant.hand_raised && (
                      <Badge variant="secondary" className="text-xs">
                        <Hand className="h-3 w-3" />
                      </Badge>
                    )}
                    {participant.role === 'host' && (
                      <Badge variant="outline" className="text-xs">
                        Host
                      </Badge>
                    )}
                  </div>

                  {/* Name overlay */}
                  <div className="absolute bottom-2 right-2">
                    <Badge variant="secondary" className="text-xs">
                      {participant.name}
                    </Badge>
                  </div>
                </div>
              ))}
            </div>

            {/* Connection status overlay */}
            {connectionStatus !== 'connected' && (
              <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                <div className="text-center text-white">
                  <RefreshCw className="h-12 w-12 animate-spin mx-auto mb-4" />
                  <h3 className="text-lg font-medium">
                    {connectionStatus === 'connecting' ? 'Connecting...' : 
                     connectionStatus === 'failed' ? 'Connection Failed' : 
                     'Disconnected'}
                  </h3>
                  {connectionStatus === 'failed' && (
                    <Button
                      variant="outline"
                      className="mt-4"
                      onClick={connectToMeeting}
                    >
                      Retry Connection
                    </Button>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Meeting Controls */}
          <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2">
            <div className="flex items-center space-x-2 bg-gray-900 rounded-full px-6 py-3">
              <Button
                variant={localAudioEnabled ? "default" : "destructive"}
                size="sm"
                onClick={toggleAudio}
                className="rounded-full w-12 h-12"
              >
                {localAudioEnabled ? <Mic className="h-5 w-5" /> : <MicOff className="h-5 w-5" />}
              </Button>

              <Button
                variant={localVideoEnabled ? "default" : "destructive"}
                size="sm"
                onClick={toggleVideo}
                className="rounded-full w-12 h-12"
              >
                {localVideoEnabled ? <Video className="h-5 w-5" /> : <VideoOff className="h-5 w-5" />}
              </Button>

              <Button
                variant={screenSharing ? "default" : "outline"}
                size="sm"
                onClick={toggleScreenShare}
                className="rounded-full w-12 h-12"
                disabled={!meeting.settings.screen_sharing}
              >
                <Share className="h-5 w-5" />
              </Button>

              <Button
                variant={handRaised ? "default" : "outline"}
                size="sm"
                onClick={toggleHandRaise}
                className="rounded-full w-12 h-12"
              >
                <Hand className="h-5 w-5" />
              </Button>

              <Separator orientation="vertical" className="h-8 bg-gray-600" />

              <Button
                variant="outline"
                size="sm"
                onClick={() => setChatOpen(!chatOpen)}
                className="rounded-full w-12 h-12"
                disabled={!meeting.settings.chat_enabled}
              >
                <MessageCircle className="h-5 w-5" />
              </Button>

              <Button
                variant="outline"
                size="sm"
                onClick={() => setParticipantsOpen(!participantsOpen)}
                className="rounded-full w-12 h-12"
              >
                <Users className="h-5 w-5" />
                <span className="ml-1 text-xs">{activeParticipants.length}</span>
              </Button>

              <Separator orientation="vertical" className="h-8 bg-gray-600" />

              <Button
                variant="destructive"
                size="sm"
                onClick={isHost ? endMeeting : leaveMeeting}
                className="rounded-full w-12 h-12"
              >
                <PhoneOff className="h-5 w-5" />
              </Button>
            </div>
          </div>
        </div>

        {/* Side Panel */}
        {(chatOpen || participantsOpen) && (
          <div className="w-80 border-l bg-white">
            <Tabs value={chatOpen ? 'chat' : 'participants'} className="h-full">
              <TabsList className="w-full">
                <TabsTrigger 
                  value="chat" 
                  className="flex-1"
                  onClick={() => { setChatOpen(true); setParticipantsOpen(false); }}
                >
                  <MessageCircle className="h-4 w-4 mr-2" />
                  Chat
                </TabsTrigger>
                <TabsTrigger 
                  value="participants" 
                  className="flex-1"
                  onClick={() => { setParticipantsOpen(true); setChatOpen(false); }}
                >
                  <Users className="h-4 w-4 mr-2" />
                  Participants ({activeParticipants.length})
                </TabsTrigger>
              </TabsList>

              <TabsContent value="chat" className="h-[calc(100%-48px)] p-0">
                <div className="h-full flex flex-col">
                  <ScrollArea className="flex-1 p-4">
                    <div className="text-center text-gray-500 text-sm">
                      Chat messages would appear here
                    </div>
                  </ScrollArea>
                  <div className="p-4 border-t">
                    <div className="flex space-x-2">
                      <Input placeholder="Type a message..." className="flex-1" />
                      <Button size="sm">Send</Button>
                    </div>
                  </div>
                </div>
              </TabsContent>

              <TabsContent value="participants" className="h-[calc(100%-48px)] p-0">
                <ScrollArea className="h-full">
                  <div className="p-4 space-y-4">
                    {waitingParticipants.length > 0 && (
                      <div>
                        <h4 className="text-sm font-medium mb-2">
                          Waiting Room ({waitingParticipants.length})
                        </h4>
                        <div className="space-y-2">
                          {waitingParticipants.map(participant => (
                            <div key={participant.id} className="flex items-center justify-between p-2 border rounded">
                              <span className="text-sm">{participant.name}</span>
                              {isHost && (
                                <div className="flex space-x-1">
                                  <Button size="sm" variant="outline">Admit</Button>
                                  <Button size="sm" variant="outline">Deny</Button>
                                </div>
                              )}
                            </div>
                          ))}
                        </div>
                        <Separator className="my-4" />
                      </div>
                    )}

                    <div>
                      <h4 className="text-sm font-medium mb-2">
                        In Meeting ({activeParticipants.length})
                      </h4>
                      <div className="space-y-2">
                        {activeParticipants.map(participant => (
                          <div key={participant.id} className="flex items-center space-x-3 p-2 rounded hover:bg-gray-50">
                            <div className="flex items-center space-x-2">
                              {participant.role === 'host' && (
                                <Badge variant="outline" className="text-xs">Host</Badge>
                              )}
                              <span className="text-sm font-medium">{participant.name}</span>
                            </div>
                            <div className="flex items-center space-x-1 ml-auto">
                              {participant.audio_enabled ? (
                                <Mic className="h-3 w-3 text-green-500" />
                              ) : (
                                <MicOff className="h-3 w-3 text-red-500" />
                              )}
                              {participant.video_enabled ? (
                                <Video className="h-3 w-3 text-green-500" />
                              ) : (
                                <VideoOff className="h-3 w-3 text-gray-400" />
                              )}
                              {participant.hand_raised && (
                                <Hand className="h-3 w-3 text-yellow-500" />
                              )}
                              {participant.screen_sharing && (
                                <Monitor className="h-3 w-3 text-blue-500" />
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </ScrollArea>
              </TabsContent>
            </Tabs>
          </div>
        )}
      </div>

      {/* Breakout Rooms Sheet */}
      {meeting.settings.breakout_rooms && (
        <Sheet>
          <SheetTrigger asChild>
            <Button
              variant="outline"
              className="fixed bottom-20 right-4"
            >
              <Users className="h-4 w-4" />
              Breakout Rooms
            </Button>
          </SheetTrigger>
          <SheetContent className="w-96">
            <SheetHeader>
              <SheetTitle>Breakout Rooms</SheetTitle>
              <SheetDescription>Manage breakout room sessions</SheetDescription>
            </SheetHeader>
            <div className="mt-6">
              <BreakoutRoomManager
                meetingId={meetingId}
                isHost={isHost}
              />
            </div>
          </SheetContent>
        </Sheet>
      )}

      {/* Templates Quick Access */}
      <Dialog>
        <DialogTrigger asChild>
          <Button
            variant="outline"
            className="fixed bottom-20 left-4"
          >
            <Bookmark className="h-4 w-4" />
            Templates
          </Button>
        </DialogTrigger>
        <DialogContent className="max-w-6xl">
          <DialogHeader>
            <DialogTitle>Meeting Templates</DialogTitle>
            <DialogDescription>Quick access to meeting templates and presets</DialogDescription>
          </DialogHeader>
          <MeetingTemplatesManager
            selectionMode={true}
            showCreateButton={false}
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}