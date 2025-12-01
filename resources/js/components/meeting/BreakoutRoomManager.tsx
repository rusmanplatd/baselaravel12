import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import { 
  Users, 
  Plus, 
  Settings, 
  Play, 
  Pause, 
  RotateCw,
  UserPlus,
  UserMinus,
  Clock,
  MessageCircle,
  Volume2,
  Video,
  Share,
  Activity
} from 'lucide-react';
import apiService from '@/services/ApiService';

interface BreakoutRoom {
  id: string;
  room_name: string;
  display_name: string;
  room_number: number;
  description?: string;
  status: 'created' | 'active' | 'closed' | 'archived';
  max_participants: number;
  current_participants: number;
  room_settings?: {
    audio_enabled?: boolean;
    video_enabled?: boolean;
    screen_sharing_enabled?: boolean;
    chat_enabled?: boolean;
    recording_enabled?: boolean;
  };
  participants: BreakoutParticipant[];
  created_at: string;
  opened_at?: string;
}

interface BreakoutParticipant {
  id: string;
  attendee_id: string;
  user_name: string;
  email: string;
  status: 'assigned' | 'joined' | 'left' | 'moved';
  joined_at?: string;
  duration_minutes?: number;
}

interface Participant {
  id: string;
  user_id: string;
  user_name: string;
  email: string;
  role: string;
  status: string;
}

interface BreakoutRoomManagerProps {
  meetingId: string;
  isHost?: boolean;
  participants: Participant[];
}

export function BreakoutRoomManager({ meetingId, isHost = false, participants }: BreakoutRoomManagerProps) {
  const [breakoutRooms, setBreakoutRooms] = useState<BreakoutRoom[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [selectedRoom, setSelectedRoom] = useState<BreakoutRoom | null>(null);
  
  // Room creation settings
  const [createConfig, setCreateConfig] = useState({
    numberOfRooms: 2,
    maxParticipants: 10,
    autoAssign: true,
    roomSettings: {
      audio_enabled: true,
      video_enabled: true,
      screen_sharing_enabled: true,
      chat_enabled: true,
      recording_enabled: false
    }
  });

  useEffect(() => {
    loadBreakoutRooms();
    
    // Poll for updates every 10 seconds when rooms are active
    const interval = setInterval(() => {
      if (breakoutRooms.some(room => room.status === 'active')) {
        loadBreakoutRooms();
      }
    }, 10000);

    return () => clearInterval(interval);
  }, [meetingId]);

  const loadBreakoutRooms = async () => {
    try {
      const response = await apiService.get(`/api/meetings/${meetingId}/breakout-rooms`);
      setBreakoutRooms(response.data);
    } catch (error) {
      console.error('Failed to load breakout rooms:', error);
    } finally {
      setLoading(false);
    }
  };

  const createBreakoutRooms = async () => {
    if (!isHost) return;
    
    setCreating(true);
    try {
      const response = await apiService.post(`/api/meetings/${meetingId}/breakout-rooms`, {
        number_of_rooms: createConfig.numberOfRooms,
        max_participants: createConfig.maxParticipants,
        room_settings: createConfig.roomSettings,
        auto_assign: createConfig.autoAssign
      });

      if (response.success) {
        await loadBreakoutRooms();
        setShowCreateDialog(false);
      }
    } catch (error) {
      console.error('Failed to create breakout rooms:', error);
    } finally {
      setCreating(false);
    }
  };

  const openRoom = async (roomId: string) => {
    if (!isHost) return;
    
    try {
      await apiService.post(`/api/meetings/${meetingId}/breakout-rooms/${roomId}/open`);
      await loadBreakoutRooms();
    } catch (error) {
      console.error('Failed to open room:', error);
    }
  };

  const closeRoom = async (roomId: string) => {
    if (!isHost) return;
    
    try {
      await apiService.post(`/api/meetings/${meetingId}/breakout-rooms/${roomId}/close`);
      await loadBreakoutRooms();
    } catch (error) {
      console.error('Failed to close room:', error);
    }
  };

  const assignParticipant = async (roomId: string, participantId: string) => {
    if (!isHost) return;
    
    try {
      await apiService.post(`/api/meetings/${meetingId}/breakout-rooms/${roomId}/participants`, {
        participant_id: participantId
      });
      await loadBreakoutRooms();
    } catch (error) {
      console.error('Failed to assign participant:', error);
    }
  };

  const removeParticipant = async (roomId: string, participantId: string) => {
    if (!isHost) return;
    
    try {
      await apiService.delete(`/api/meetings/${meetingId}/breakout-rooms/${roomId}/participants/${participantId}`);
      await loadBreakoutRooms();
    } catch (error) {
      console.error('Failed to remove participant:', error);
    }
  };

  const broadcastMessage = async (message: string) => {
    if (!isHost || !message.trim()) return;
    
    try {
      await apiService.post(`/api/meetings/${meetingId}/breakout-rooms/broadcast`, {
        message: message.trim()
      });
    } catch (error) {
      console.error('Failed to broadcast message:', error);
    }
  };

  const closeAllRooms = async () => {
    if (!isHost) return;
    
    try {
      await apiService.post(`/api/meetings/${meetingId}/breakout-rooms/close-all`);
      await loadBreakoutRooms();
    } catch (error) {
      console.error('Failed to close all rooms:', error);
    }
  };

  const getParticipantStatusColor = (status: string) => {
    switch (status) {
      case 'joined': return 'bg-green-500';
      case 'assigned': return 'bg-yellow-500';
      case 'left': return 'bg-gray-500';
      default: return 'bg-gray-400';
    }
  };

  const getRoomStatusBadge = (room: BreakoutRoom) => {
    switch (room.status) {
      case 'active':
        return <Badge className="bg-green-500">Active</Badge>;
      case 'created':
        return <Badge variant="outline">Ready</Badge>;
      case 'closed':
        return <Badge variant="secondary">Closed</Badge>;
      default:
        return <Badge variant="outline">{room.status}</Badge>;
    }
  };

  const unassignedParticipants = participants.filter(p => 
    !breakoutRooms.some(room => 
      room.participants.some(rp => rp.attendee_id === p.id && rp.status !== 'left')
    )
  );

  if (loading) {
    return (
      <Card>
        <CardContent className="p-6">
          <div className="animate-pulse space-y-4">
            <div className="h-4 bg-gray-200 rounded w-1/4"></div>
            <div className="space-y-2">
              <div className="h-16 bg-gray-200 rounded"></div>
              <div className="h-16 bg-gray-200 rounded"></div>
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Users className="h-5 w-5" />
            Breakout Rooms ({breakoutRooms.length})
          </CardTitle>
          {isHost && (
            <div className="flex gap-2">
              {breakoutRooms.length > 0 && breakoutRooms.some(r => r.status === 'active') && (
                <Button variant="outline" size="sm" onClick={closeAllRooms}>
                  Close All Rooms
                </Button>
              )}
              <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                <DialogTrigger asChild>
                  <Button size="sm">
                    <Plus className="h-4 w-4 mr-2" />
                    Create Rooms
                  </Button>
                </DialogTrigger>
                <DialogContent className="max-w-md">
                  <DialogHeader>
                    <DialogTitle>Create Breakout Rooms</DialogTitle>
                  </DialogHeader>
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label>Number of Rooms</Label>
                      <Input
                        type="number"
                        min="2"
                        max="20"
                        value={createConfig.numberOfRooms}
                        onChange={(e) => setCreateConfig(prev => ({ 
                          ...prev, 
                          numberOfRooms: parseInt(e.target.value) || 2 
                        }))}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label>Max Participants per Room</Label>
                      <Input
                        type="number"
                        min="1"
                        max="50"
                        value={createConfig.maxParticipants}
                        onChange={(e) => setCreateConfig(prev => ({ 
                          ...prev, 
                          maxParticipants: parseInt(e.target.value) || 10 
                        }))}
                      />
                    </div>
                    <div className="space-y-3">
                      <Label>Room Settings</Label>
                      <div className="space-y-2">
                        {[
                          { key: 'audio_enabled', label: 'Audio Enabled', icon: Volume2 },
                          { key: 'video_enabled', label: 'Video Enabled', icon: Video },
                          { key: 'screen_sharing_enabled', label: 'Screen Sharing', icon: Share },
                          { key: 'chat_enabled', label: 'Chat Enabled', icon: MessageCircle },
                          { key: 'recording_enabled', label: 'Recording Enabled', icon: Activity }
                        ].map(({ key, label, icon: Icon }) => (
                          <div key={key} className="flex items-center space-x-2">
                            <Checkbox
                              id={key}
                              checked={createConfig.roomSettings[key as keyof typeof createConfig.roomSettings]}
                              onCheckedChange={(checked) => 
                                setCreateConfig(prev => ({
                                  ...prev,
                                  roomSettings: {
                                    ...prev.roomSettings,
                                    [key]: checked
                                  }
                                }))
                              }
                            />
                            <Label htmlFor={key} className="flex items-center gap-2">
                              <Icon className="h-4 w-4" />
                              {label}
                            </Label>
                          </div>
                        ))}
                      </div>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Checkbox
                        id="autoAssign"
                        checked={createConfig.autoAssign}
                        onCheckedChange={(checked) => 
                          setCreateConfig(prev => ({ ...prev, autoAssign: checked as boolean }))
                        }
                      />
                      <Label htmlFor="autoAssign">Automatically assign participants</Label>
                    </div>
                    <div className="flex gap-2 pt-4">
                      <Button onClick={createBreakoutRooms} disabled={creating} className="flex-1">
                        {creating ? 'Creating...' : 'Create Rooms'}
                      </Button>
                      <Button variant="outline" onClick={() => setShowCreateDialog(false)}>
                        Cancel
                      </Button>
                    </div>
                  </div>
                </DialogContent>
              </Dialog>
            </div>
          )}
        </div>
      </CardHeader>
      <CardContent>
        {breakoutRooms.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            <Users className="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p className="mb-2">No breakout rooms created yet</p>
            {isHost && <p className="text-sm">Create breakout rooms to split participants into smaller groups</p>}
          </div>
        ) : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {breakoutRooms.map((room) => (
                <Card key={room.id}>
                  <CardContent className="p-4">
                    <div className="flex items-center justify-between mb-3">
                      <div>
                        <h4 className="font-medium">{room.display_name}</h4>
                        <p className="text-sm text-gray-600">
                          {room.current_participants}/{room.max_participants} participants
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        {getRoomStatusBadge(room)}
                        {isHost && (
                          <div className="flex gap-1">
                            {room.status === 'created' && (
                              <Button size="sm" variant="outline" onClick={() => openRoom(room.id)}>
                                <Play className="h-3 w-3" />
                              </Button>
                            )}
                            {room.status === 'active' && (
                              <Button size="sm" variant="outline" onClick={() => closeRoom(room.id)}>
                                <Pause className="h-3 w-3" />
                              </Button>
                            )}
                            <Button size="sm" variant="outline" onClick={() => setSelectedRoom(room)}>
                              <Settings className="h-3 w-3" />
                            </Button>
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="space-y-2">
                      <div className="flex items-center gap-2 text-xs text-gray-600">
                        <Clock className="h-3 w-3" />
                        {room.opened_at ? (
                          `Active since ${new Date(room.opened_at).toLocaleTimeString()}`
                        ) : (
                          `Created ${new Date(room.created_at).toLocaleTimeString()}`
                        )}
                      </div>

                      <div className="space-y-1">
                        {room.participants.slice(0, 3).map((participant) => (
                          <div key={participant.id} className="flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                              <div className={`w-2 h-2 rounded-full ${getParticipantStatusColor(participant.status)}`} />
                              <span className="truncate">{participant.user_name}</span>
                            </div>
                            <span className="text-gray-500 capitalize">{participant.status}</span>
                          </div>
                        ))}
                        {room.participants.length > 3 && (
                          <div className="text-xs text-gray-500">
                            +{room.participants.length - 3} more participants
                          </div>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>

            {/* Unassigned Participants */}
            {unassignedParticipants.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Unassigned Participants ({unassignedParticipants.length})</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    {unassignedParticipants.map((participant) => (
                      <div key={participant.id} className="flex items-center justify-between p-2 border rounded">
                        <div className="flex items-center gap-2">
                          <div className="w-2 h-2 rounded-full bg-gray-400" />
                          <span className="text-sm truncate">{participant.user_name}</span>
                        </div>
                        {isHost && (
                          <Select onValueChange={(roomId) => assignParticipant(roomId, participant.id)}>
                            <SelectTrigger className="w-20 h-6">
                              <SelectValue placeholder="Assign" />
                            </SelectTrigger>
                            <SelectContent>
                              {breakoutRooms
                                .filter(room => room.current_participants < room.max_participants)
                                .map((room) => (
                                  <SelectItem key={room.id} value={room.id}>
                                    Room {room.room_number}
                                  </SelectItem>
                                ))
                              }
                            </SelectContent>
                          </Select>
                        )}
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {/* Room Details Dialog */}
        <Dialog open={!!selectedRoom} onOpenChange={() => setSelectedRoom(null)}>
          <DialogContent className="max-w-lg">
            <DialogHeader>
              <DialogTitle>
                {selectedRoom?.display_name} - Details
              </DialogTitle>
            </DialogHeader>
            {selectedRoom && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <span>Status</span>
                  {getRoomStatusBadge(selectedRoom)}
                </div>
                <div className="flex items-center justify-between">
                  <span>Participants</span>
                  <span>{selectedRoom.current_participants}/{selectedRoom.max_participants}</span>
                </div>

                <Separator />

                <div>
                  <h4 className="font-medium mb-2">Participants</h4>
                  <ScrollArea className="h-32">
                    <div className="space-y-2">
                      {selectedRoom.participants.map((participant) => (
                        <div key={participant.id} className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <div className={`w-2 h-2 rounded-full ${getParticipantStatusColor(participant.status)}`} />
                            <div>
                              <div className="text-sm font-medium">{participant.user_name}</div>
                              <div className="text-xs text-gray-500">{participant.email}</div>
                            </div>
                          </div>
                          <div className="flex items-center gap-2">
                            <Badge variant="outline" size="sm">
                              {participant.status}
                            </Badge>
                            {isHost && participant.status !== 'left' && (
                              <Button 
                                size="sm" 
                                variant="outline"
                                onClick={() => removeParticipant(selectedRoom.id, participant.attendee_id)}
                              >
                                <UserMinus className="h-3 w-3" />
                              </Button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </ScrollArea>
                </div>

                {isHost && unassignedParticipants.length > 0 && (
                  <>
                    <Separator />
                    <div>
                      <h4 className="font-medium mb-2">Add Participants</h4>
                      <div className="space-y-2">
                        {unassignedParticipants.slice(0, 5).map((participant) => (
                          <div key={participant.id} className="flex items-center justify-between">
                            <span className="text-sm">{participant.user_name}</span>
                            <Button 
                              size="sm" 
                              variant="outline"
                              onClick={() => assignParticipant(selectedRoom.id, participant.id)}
                            >
                              <UserPlus className="h-3 w-3" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </>
                )}
              </div>
            )}
          </DialogContent>
        </Dialog>
      </CardContent>
    </Card>
  );
}