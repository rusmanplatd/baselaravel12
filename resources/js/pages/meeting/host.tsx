import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    Clock, MapPin, Users, Video, VideoOff, Mic, MicOff, Settings, 
    Calendar, Play, Square, UserPlus, UserMinus, Shield, Monitor
} from 'lucide-react';
import { format } from 'date-fns';
import apiService from '@/services/ApiService';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

interface MeetingHostPageProps {
    meeting: {
        id: string;
        status: string;
        join_url: string;
        host_url: string;
        e2ee_enabled: boolean;
        recording_enabled: boolean;
        attendee_count: number;
        active_attendee_count: number;
        calendar_event: {
            title: string;
            starts_at: string;
            ends_at: string;
        };
    };
    attendee: {
        id: string;
        name: string;
        role: string;
        can_manage: boolean;
        can_present: boolean;
    };
    participants: {
        total_attendees: number;
        active_participants: number;
        attendees: Array<{
            id: string;
            email: string;
            name: string;
            role: string;
            invitation_status: string;
            attendance_status: string;
            can_manage: boolean;
            can_present: boolean;
            joined_at: string | null;
        }>;
        livekit_participants: Array<any>;
    };
    calendarEvent: {
        title: string;
        description: string | null;
        starts_at: string;
        ends_at: string;
        location: string | null;
        attendees: Array<{
            email: string;
            name: string;
            status: string;
        }> | null;
    };
    hostControls: {
        canStart: boolean;
        canEnd: boolean;
        canUpdateSettings: boolean;
        canManageAttendees: boolean;
    };
}

export default function MeetingHostPage({
    meeting,
    attendee,
    participants,
    calendarEvent,
    hostControls
}: MeetingHostPageProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [meetingData, setMeetingData] = useState(meeting);
    const [participantData, setParticipantData] = useState(participants);
    const [showAddAttendeeDialog, setShowAddAttendeeDialog] = useState(false);
    const [newAttendeeEmail, setNewAttendeeEmail] = useState('');
    const [newAttendeeName, setNewAttendeeName] = useState('');

    // Settings state
    const [settings, setSettings] = useState({
        recording_enabled: meeting.recording_enabled,
        waiting_room_enabled: false,
        mute_on_entry: false,
        camera_on_entry: true,
        screen_sharing_enabled: true,
        chat_enabled: true,
    });

    useEffect(() => {
        // Poll for participant updates every 10 seconds when meeting is active
        if (meetingData.status === 'active') {
            const interval = setInterval(refreshParticipants, 10000);
            return () => clearInterval(interval);
        }
    }, [meetingData.status]);

    const refreshParticipants = async () => {
        try {
            const response = await apiService.get(`/api/v1/meetings/${meeting.id}/participants`);
            if (response.success) {
                setParticipantData(response.participants);
            }
        } catch (error) {
            console.error('Error refreshing participants:', error);
        }
    };

    const handleStartMeeting = async () => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meeting.id}/start`);
            if (response.success) {
                setMeetingData({ ...meetingData, status: 'active' });
                
                // Join the meeting as host
                const joinParams = new URLSearchParams({
                    server_url: response.connection?.server_url || '',
                    access_token: response.connection?.access_token || '',
                    room_name: response.connection?.room_name || '',
                    participant_identity: response.connection?.participant_identity || '',
                    participant_name: attendee.name,
                    e2ee_enabled: meetingData.e2ee_enabled ? '1' : '0',
                    is_host: '1',
                }).toString();

                window.open(`/meeting-room?${joinParams}`, '_blank');
            }
        } catch (error) {
            console.error('Error starting meeting:', error);
            setError('Failed to start meeting');
        } finally {
            setIsLoading(false);
        }
    };

    const handleEndMeeting = async () => {
        if (!confirm('Are you sure you want to end this meeting for all participants?')) {
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meeting.id}/end`);
            if (response.success) {
                setMeetingData({ ...meetingData, status: 'ended' });
                router.reload();
            }
        } catch (error) {
            console.error('Error ending meeting:', error);
            setError('Failed to end meeting');
        } finally {
            setIsLoading(false);
        }
    };

    const handleUpdateSettings = async () => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await apiService.patch(`/api/v1/meetings/${meeting.id}/settings`, {
                settings
            });
            if (response.success) {
                setMeetingData(response.meeting);
                alert('Settings updated successfully');
            }
        } catch (error) {
            console.error('Error updating settings:', error);
            setError('Failed to update settings');
        } finally {
            setIsLoading(false);
        }
    };

    const handleAddAttendee = async () => {
        if (!newAttendeeEmail) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meeting.id}/attendees`, {
                email: newAttendeeEmail,
                name: newAttendeeName,
                role: 'attendee'
            });
            
            if (response.success) {
                setShowAddAttendeeDialog(false);
                setNewAttendeeEmail('');
                setNewAttendeeName('');
                refreshParticipants();
            }
        } catch (error) {
            console.error('Error adding attendee:', error);
            setError('Failed to add attendee');
        } finally {
            setIsLoading(false);
        }
    };

    const handleRemoveAttendee = async (attendeeId: string) => {
        if (!confirm('Are you sure you want to remove this attendee?')) {
            return;
        }

        try {
            const response = await apiService.delete(`/api/v1/meetings/${meeting.id}/attendees`, {
                attendee_id: attendeeId
            });
            
            if (response.success) {
                refreshParticipants();
            }
        } catch (error) {
            console.error('Error removing attendee:', error);
            setError('Failed to remove attendee');
        }
    };

    const getStatusBadgeVariant = (status: string) => {
        switch (status) {
            case 'active': return 'default';
            case 'scheduled': return 'secondary';
            case 'ended': return 'destructive';
            case 'cancelled': return 'destructive';
            default: return 'outline';
        }
    };

    const getAttendanceStatusBadge = (status: string) => {
        switch (status) {
            case 'joined': return { variant: 'default' as const, text: 'Joined' };
            case 'left': return { variant: 'secondary' as const, text: 'Left' };
            case 'not_joined': return { variant: 'outline' as const, text: 'Not joined' };
            default: return { variant: 'outline' as const, text: status };
        }
    };

    return (
        <div className="min-h-screen bg-gray-50 p-4">
            <Head title={`Host Meeting: ${calendarEvent.title}`} />
            
            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <Shield className="h-5 w-5 text-blue-600" />
                                <CardTitle className="text-xl">Host Dashboard</CardTitle>
                            </div>
                            <Badge variant={getStatusBadgeVariant(meetingData.status)}>
                                {meetingData.status}
                            </Badge>
                        </div>
                        <CardDescription>
                            Managing: {calendarEvent.title}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-4">
                            {hostControls.canStart && (
                                <Button
                                    onClick={handleStartMeeting}
                                    disabled={isLoading}
                                    size="lg"
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    <Play className="h-4 w-4 mr-2" />
                                    Start Meeting
                                </Button>
                            )}
                            
                            {hostControls.canEnd && (
                                <Button
                                    onClick={handleEndMeeting}
                                    disabled={isLoading}
                                    variant="destructive"
                                    size="lg"
                                >
                                    <Square className="h-4 w-4 mr-2" />
                                    End Meeting
                                </Button>
                            )}

                            {meetingData.status === 'active' && (
                                <Button
                                    onClick={() => window.open(meetingData.join_url, '_blank')}
                                    variant="outline"
                                >
                                    <Monitor className="h-4 w-4 mr-2" />
                                    Join Meeting
                                </Button>
                            )}
                        </div>

                        {error && (
                            <Alert className="mt-4" variant="destructive">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>

                <Tabs defaultValue="overview" className="space-y-6">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="participants">Participants</TabsTrigger>
                        <TabsTrigger value="settings">Settings</TabsTrigger>
                        <TabsTrigger value="info">Meeting Info</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Participants</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>Total Attendees:</span>
                                            <span className="font-semibold">{participantData.total_attendees}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Currently Active:</span>
                                            <span className="font-semibold text-green-600">{participantData.active_participants}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Meeting Status</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>Status:</span>
                                            <Badge variant={getStatusBadgeVariant(meetingData.status)}>
                                                {meetingData.status}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>Recording:</span>
                                            <Badge variant={meetingData.recording_enabled ? 'default' : 'secondary'}>
                                                {meetingData.recording_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span>E2EE:</span>
                                            <Badge variant={meetingData.e2ee_enabled ? 'default' : 'secondary'}>
                                                {meetingData.e2ee_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Quick Actions</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            className="w-full"
                                            onClick={() => navigator.clipboard.writeText(meetingData.join_url)}
                                        >
                                            Copy Join Link
                                        </Button>
                                        <Dialog open={showAddAttendeeDialog} onOpenChange={setShowAddAttendeeDialog}>
                                            <DialogTrigger asChild>
                                                <Button variant="outline" size="sm" className="w-full">
                                                    <UserPlus className="h-4 w-4 mr-2" />
                                                    Add Attendee
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader>
                                                    <DialogTitle>Add Attendee</DialogTitle>
                                                </DialogHeader>
                                                <div className="space-y-4">
                                                    <div>
                                                        <Label htmlFor="email">Email</Label>
                                                        <Input
                                                            id="email"
                                                            type="email"
                                                            value={newAttendeeEmail}
                                                            onChange={(e) => setNewAttendeeEmail(e.target.value)}
                                                            placeholder="attendee@example.com"
                                                        />
                                                    </div>
                                                    <div>
                                                        <Label htmlFor="name">Name (optional)</Label>
                                                        <Input
                                                            id="name"
                                                            value={newAttendeeName}
                                                            onChange={(e) => setNewAttendeeName(e.target.value)}
                                                            placeholder="Attendee Name"
                                                        />
                                                    </div>
                                                    <div className="flex justify-end space-x-2">
                                                        <Button
                                                            variant="outline"
                                                            onClick={() => setShowAddAttendeeDialog(false)}
                                                        >
                                                            Cancel
                                                        </Button>
                                                        <Button onClick={handleAddAttendee} disabled={!newAttendeeEmail}>
                                                            Add
                                                        </Button>
                                                    </div>
                                                </div>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    <TabsContent value="participants" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Attendees</CardTitle>
                                <CardDescription>
                                    Manage meeting participants and their roles
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {participantData.attendees.map((participant) => {
                                        const statusBadge = getAttendanceStatusBadge(participant.attendance_status);
                                        return (
                                            <div
                                                key={participant.id}
                                                className="flex items-center justify-between p-3 border rounded-lg"
                                            >
                                                <div className="flex items-center space-x-3">
                                                    <div>
                                                        <div className="font-medium">{participant.name}</div>
                                                        <div className="text-sm text-gray-500">{participant.email}</div>
                                                    </div>
                                                    <div className="flex space-x-2">
                                                        <Badge variant="outline">{participant.role}</Badge>
                                                        <Badge variant={statusBadge.variant}>
                                                            {statusBadge.text}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div className="flex space-x-2">
                                                    {hostControls.canManageAttendees && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => handleRemoveAttendee(participant.id)}
                                                        >
                                                            <UserMinus className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="settings" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Meeting Settings</CardTitle>
                                <CardDescription>
                                    Configure meeting behavior and features
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-2">
                                            <Switch
                                                id="recording"
                                                checked={settings.recording_enabled}
                                                onCheckedChange={(checked) => setSettings(s => ({ ...s, recording_enabled: checked }))}
                                            />
                                            <Label htmlFor="recording">Enable Recording</Label>
                                        </div>
                                        
                                        <div className="flex items-center space-x-2">
                                            <Switch
                                                id="waiting-room"
                                                checked={settings.waiting_room_enabled}
                                                onCheckedChange={(checked) => setSettings(s => ({ ...s, waiting_room_enabled: checked }))}
                                            />
                                            <Label htmlFor="waiting-room">Enable Waiting Room</Label>
                                        </div>

                                        <div className="flex items-center space-x-2">
                                            <Switch
                                                id="mute-on-entry"
                                                checked={settings.mute_on_entry}
                                                onCheckedChange={(checked) => setSettings(s => ({ ...s, mute_on_entry: checked }))}
                                            />
                                            <Label htmlFor="mute-on-entry">Mute on Entry</Label>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-2">
                                            <Switch
                                                id="camera-on-entry"
                                                checked={settings.camera_on_entry}
                                                onCheckedChange={(checked) => setSettings(s => ({ ...s, camera_on_entry: checked }))}
                                            />
                                            <Label htmlFor="camera-on-entry">Camera on Entry</Label>
                                        </div>

                                        <div className="flex items-center space-x-2">
                                            <Switch
                                                id="screen-sharing"
                                                checked={settings.screen_sharing_enabled}
                                                onCheckedChange={(checked) => setSettings(s => ({ ...s, screen_sharing_enabled: checked }))}
                                            />
                                            <Label htmlFor="screen-sharing">Enable Screen Sharing</Label>
                                        </div>

                                        <div className="flex items-center space-x-2">
                                            <Switch
                                                id="chat"
                                                checked={settings.chat_enabled}
                                                onCheckedChange={(checked) => setSettings(s => ({ ...s, chat_enabled: checked }))}
                                            />
                                            <Label htmlFor="chat">Enable Chat</Label>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <Button onClick={handleUpdateSettings} disabled={isLoading}>
                                        Save Settings
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="info" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Meeting Information</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <h4 className="font-medium">Title</h4>
                                        <p>{calendarEvent.title}</p>
                                    </div>
                                    
                                    {calendarEvent.description && (
                                        <div>
                                            <h4 className="font-medium">Description</h4>
                                            <p className="text-gray-600">{calendarEvent.description}</p>
                                        </div>
                                    )}
                                    
                                    <div>
                                        <h4 className="font-medium">Schedule</h4>
                                        <div className="flex items-center space-x-2">
                                            <Clock className="h-4 w-4 text-gray-500" />
                                            <div>
                                                <div>{format(new Date(calendarEvent.starts_at), 'PPP')}</div>
                                                <div className="text-sm text-gray-600">
                                                    {format(new Date(calendarEvent.starts_at), 'p')} - {format(new Date(calendarEvent.ends_at), 'p')}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {calendarEvent.location && (
                                        <div>
                                            <h4 className="font-medium">Location</h4>
                                            <div className="flex items-center space-x-2">
                                                <MapPin className="h-4 w-4 text-gray-500" />
                                                <span>{calendarEvent.location}</span>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    );
}