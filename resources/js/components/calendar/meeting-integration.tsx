import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Video, Settings, Users, Shield, Loader2 } from 'lucide-react';
import { useMeeting } from '@/hooks/useMeeting';

interface MeetingIntegrationProps {
    calendarEventId?: string;
    onMeetingCreated?: (meeting: any) => void;
    className?: string;
}

export function MeetingIntegration({ 
    calendarEventId, 
    onMeetingCreated,
    className = '' 
}: MeetingIntegrationProps) {
    const meeting = useMeeting();
    const [enabled, setEnabled] = useState(false);
    const [meetingData, setMeetingData] = useState<any>(null);
    
    const [settings, setSettings] = useState({
        audio_enabled: true,
        video_enabled: true,
        screen_sharing_enabled: true,
        chat_enabled: true,
        waiting_room_enabled: false,
        mute_on_entry: false,
        camera_on_entry: true,
        max_participants: 50,
        recording_enabled: false,
        e2ee_enabled: true,
    });

    const handleCreateMeeting = async () => {
        if (!calendarEventId) {
            console.error('No calendar event ID provided');
            return;
        }

        try {
            const createdMeeting = await meeting.createMeetingFromCalendarEvent({
                calendar_event_id: calendarEventId,
                meeting_settings: settings,
            });

            setMeetingData(createdMeeting);
            onMeetingCreated?.(createdMeeting);
        } catch (error) {
            console.error('Failed to create meeting:', error);
        }
    };

    const copyJoinLink = () => {
        if (meetingData?.join_url) {
            navigator.clipboard.writeText(meetingData.join_url);
            // You could add a toast notification here
        }
    };

    if (meetingData) {
        return (
            <Card className={className}>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            <Video className="h-5 w-5 text-green-600" />
                            <CardTitle className="text-lg">Meeting Created</CardTitle>
                        </div>
                        <Badge variant="default">Active</Badge>
                    </div>
                    <CardDescription>
                        Online meeting has been set up for this event
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <Label className="text-xs text-gray-500">Meeting ID</Label>
                            <div className="font-mono text-sm">{meetingData.meeting_id}</div>
                        </div>
                        
                        <div>
                            <Label className="text-xs text-gray-500">Status</Label>
                            <div className="capitalize">{meetingData.status}</div>
                        </div>
                        
                        <div>
                            <Label className="text-xs text-gray-500">Max Participants</Label>
                            <div>{settings.max_participants}</div>
                        </div>
                        
                        <div>
                            <Label className="text-xs text-gray-500">Security</Label>
                            <div className="flex items-center space-x-2">
                                {meetingData.e2ee_enabled && (
                                    <div className="flex items-center space-x-1">
                                        <Shield className="h-3 w-3 text-green-600" />
                                        <span className="text-xs text-green-700">E2EE</span>
                                    </div>
                                )}
                                {meetingData.recording_enabled && (
                                    <Badge variant="secondary" className="text-xs">Recording</Badge>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex space-x-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            onClick={copyJoinLink}
                            className="flex-1"
                        >
                            Copy Join Link
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => window.open(meetingData.host_url, '_blank')}
                        >
                            Host Dashboard
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!enabled) {
        return (
            <Card className={className}>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            <Video className="h-5 w-5 text-gray-500" />
                            <CardTitle className="text-lg">Online Meeting</CardTitle>
                        </div>
                        <Switch
                            checked={enabled}
                            onCheckedChange={setEnabled}
                        />
                    </div>
                    <CardDescription>
                        Add video conferencing to this event
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Video className="h-5 w-5 text-blue-600" />
                        <CardTitle className="text-lg">Online Meeting</CardTitle>
                    </div>
                    <Switch
                        checked={enabled}
                        onCheckedChange={setEnabled}
                    />
                </div>
                <CardDescription>
                    Configure video conferencing settings
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {meeting.error && (
                    <Alert variant="destructive">
                        <AlertDescription>{meeting.error}</AlertDescription>
                    </Alert>
                )}

                {/* Basic Settings */}
                <div className="space-y-4">
                    <h4 className="font-medium flex items-center space-x-2">
                        <Settings className="h-4 w-4" />
                        <span>Basic Settings</span>
                    </h4>
                    
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="audio"
                                checked={settings.audio_enabled}
                                onCheckedChange={(checked) => setSettings(s => ({ ...s, audio_enabled: checked }))}
                            />
                            <Label htmlFor="audio" className="text-sm">Audio enabled</Label>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="video"
                                checked={settings.video_enabled}
                                onCheckedChange={(checked) => setSettings(s => ({ ...s, video_enabled: checked }))}
                            />
                            <Label htmlFor="video" className="text-sm">Video enabled</Label>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="screen-sharing"
                                checked={settings.screen_sharing_enabled}
                                onCheckedChange={(checked) => setSettings(s => ({ ...s, screen_sharing_enabled: checked }))}
                            />
                            <Label htmlFor="screen-sharing" className="text-sm">Screen sharing</Label>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="chat"
                                checked={settings.chat_enabled}
                                onCheckedChange={(checked) => setSettings(s => ({ ...s, chat_enabled: checked }))}
                            />
                            <Label htmlFor="chat" className="text-sm">Chat enabled</Label>
                        </div>
                    </div>
                </div>

                {/* Participant Settings */}
                <div className="space-y-4">
                    <h4 className="font-medium flex items-center space-x-2">
                        <Users className="h-4 w-4" />
                        <span>Participant Settings</span>
                    </h4>
                    
                    <div className="grid grid-cols-1 gap-4">
                        <div>
                            <Label htmlFor="max-participants" className="text-sm">Maximum participants</Label>
                            <Select
                                value={settings.max_participants.toString()}
                                onValueChange={(value) => setSettings(s => ({ ...s, max_participants: parseInt(value) }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10 participants</SelectItem>
                                    <SelectItem value="25">25 participants</SelectItem>
                                    <SelectItem value="50">50 participants</SelectItem>
                                    <SelectItem value="100">100 participants</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        
                        <div className="grid grid-cols-2 gap-4">
                            <div className="flex items-center space-x-2">
                                <Switch
                                    id="waiting-room"
                                    checked={settings.waiting_room_enabled}
                                    onCheckedChange={(checked) => setSettings(s => ({ ...s, waiting_room_enabled: checked }))}
                                />
                                <Label htmlFor="waiting-room" className="text-sm">Waiting room</Label>
                            </div>
                            
                            <div className="flex items-center space-x-2">
                                <Switch
                                    id="mute-on-entry"
                                    checked={settings.mute_on_entry}
                                    onCheckedChange={(checked) => setSettings(s => ({ ...s, mute_on_entry: checked }))}
                                />
                                <Label htmlFor="mute-on-entry" className="text-sm">Mute on entry</Label>
                            </div>
                            
                            <div className="flex items-center space-x-2">
                                <Switch
                                    id="camera-on-entry"
                                    checked={settings.camera_on_entry}
                                    onCheckedChange={(checked) => setSettings(s => ({ ...s, camera_on_entry: checked }))}
                                />
                                <Label htmlFor="camera-on-entry" className="text-sm">Camera on entry</Label>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Security Settings */}
                <div className="space-y-4">
                    <h4 className="font-medium flex items-center space-x-2">
                        <Shield className="h-4 w-4" />
                        <span>Security & Recording</span>
                    </h4>
                    
                    <div className="grid grid-cols-1 gap-4">
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="e2ee"
                                checked={settings.e2ee_enabled}
                                onCheckedChange={(checked) => setSettings(s => ({ ...s, e2ee_enabled: checked }))}
                            />
                            <Label htmlFor="e2ee" className="text-sm">End-to-end encryption</Label>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="recording"
                                checked={settings.recording_enabled}
                                onCheckedChange={(checked) => setSettings(s => ({ ...s, recording_enabled: checked }))}
                            />
                            <Label htmlFor="recording" className="text-sm">Enable recording</Label>
                        </div>
                    </div>
                </div>

                {calendarEventId && (
                    <Button 
                        onClick={handleCreateMeeting} 
                        disabled={meeting.loading}
                        className="w-full"
                    >
                        {meeting.loading ? (
                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                        ) : (
                            <Video className="h-4 w-4 mr-2" />
                        )}
                        Create Meeting
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}