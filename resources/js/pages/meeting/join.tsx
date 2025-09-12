import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Clock, MapPin, Users, Video, VideoOff, Mic, MicOff, Settings, Calendar } from 'lucide-react';
import { format } from 'date-fns';
import apiService from '@/services/ApiService';

interface MeetingJoinPageProps {
    meeting: {
        id: string;
        status: string;
        join_url: string;
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
        invitation_status: string;
        attendance_status: string;
    } | null;
    canJoin: boolean;
    meetingStarted: boolean;
    calendarEvent: {
        title: string;
        description: string | null;
        starts_at: string;
        ends_at: string;
        location: string | null;
    };
}

export default function MeetingJoinPage({
    meeting,
    attendee,
    canJoin,
    meetingStarted,
    calendarEvent
}: MeetingJoinPageProps) {
    const [isJoining, setIsJoining] = useState(false);
    const [videoEnabled, setVideoEnabled] = useState(true);
    const [audioEnabled, setAudioEnabled] = useState(true);
    const [devicePermissions, setDevicePermissions] = useState<{
        camera: boolean;
        microphone: boolean;
    }>({ camera: false, microphone: false });
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        checkDevicePermissions();
    }, []);

    const checkDevicePermissions = async () => {
        try {
            // Check camera permission
            try {
                const videoStream = await navigator.mediaDevices.getUserMedia({ video: true });
                videoStream.getTracks().forEach(track => track.stop());
                setDevicePermissions(prev => ({ ...prev, camera: true }));
            } catch (e) {
                setVideoEnabled(false);
            }

            // Check microphone permission
            try {
                const audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                audioStream.getTracks().forEach(track => track.stop());
                setDevicePermissions(prev => ({ ...prev, microphone: true }));
            } catch (e) {
                setAudioEnabled(false);
            }
        } catch (error) {
            console.error('Error checking device permissions:', error);
        }
    };

    const handleJoinMeeting = async () => {
        if (!canJoin) return;

        setIsJoining(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meeting.id}/join`, {
                permissions: {
                    canPublish: true,
                    canSubscribe: true,
                    canPublishData: true,
                }
            });

            if (response.success) {
                // Redirect to the meeting room with connection details
                const connectionParams = new URLSearchParams({
                    server_url: response.connection.server_url,
                    access_token: response.connection.access_token,
                    room_name: response.connection.room_name,
                    participant_identity: response.connection.participant_identity,
                    participant_name: response.connection.participant_name,
                    e2ee_enabled: response.connection.e2ee_enabled ? '1' : '0',
                    video_enabled: videoEnabled ? '1' : '0',
                    audio_enabled: audioEnabled ? '1' : '0',
                }).toString();

                window.location.href = `/meeting-room?${connectionParams}`;
            } else {
                setError('Failed to join meeting. Please try again.');
            }
        } catch (error) {
            console.error('Error joining meeting:', error);
            setError('An error occurred while joining the meeting.');
        } finally {
            setIsJoining(false);
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

    const getMeetingStatusText = (status: string) => {
        switch (status) {
            case 'active': return 'Meeting in progress';
            case 'scheduled': return 'Scheduled';
            case 'ended': return 'Meeting ended';
            case 'cancelled': return 'Meeting cancelled';
            default: return status;
        }
    };

    const timeUntilMeeting = () => {
        const now = new Date();
        const startTime = new Date(calendarEvent.starts_at);
        const diffMs = startTime.getTime() - now.getTime();
        
        if (diffMs <= 0) return null;
        
        const diffMins = Math.floor(diffMs / (1000 * 60));
        if (diffMins < 60) return `${diffMins} minutes`;
        
        const diffHours = Math.floor(diffMins / 60);
        const remainingMins = diffMins % 60;
        
        if (diffHours < 24) {
            return remainingMins > 0 ? `${diffHours}h ${remainingMins}m` : `${diffHours}h`;
        }
        
        const diffDays = Math.floor(diffHours / 24);
        return `${diffDays} days`;
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
            <Head title={`Join Meeting: ${calendarEvent.title}`} />
            
            <div className="max-w-2xl w-full space-y-6">
                {/* Meeting Info Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <Calendar className="h-5 w-5 text-blue-600" />
                                <CardTitle className="text-xl">{calendarEvent.title}</CardTitle>
                            </div>
                            <Badge variant={getStatusBadgeVariant(meeting.status)}>
                                {getMeetingStatusText(meeting.status)}
                            </Badge>
                        </div>
                        {calendarEvent.description && (
                            <CardDescription>{calendarEvent.description}</CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div className="flex items-center space-x-2">
                                <Clock className="h-4 w-4 text-gray-500" />
                                <div>
                                    <div>{format(new Date(calendarEvent.starts_at), 'PPP')}</div>
                                    <div className="text-gray-600">
                                        {format(new Date(calendarEvent.starts_at), 'p')} - {format(new Date(calendarEvent.ends_at), 'p')}
                                    </div>
                                </div>
                            </div>
                            
                            {calendarEvent.location && (
                                <div className="flex items-center space-x-2">
                                    <MapPin className="h-4 w-4 text-gray-500" />
                                    <div>{calendarEvent.location}</div>
                                </div>
                            )}
                            
                            <div className="flex items-center space-x-2">
                                <Users className="h-4 w-4 text-gray-500" />
                                <div>
                                    {meeting.active_attendee_count} active, {meeting.attendee_count} total
                                </div>
                            </div>

                            {meeting.e2ee_enabled && (
                                <div className="flex items-center space-x-2">
                                    <div className="h-2 w-2 bg-green-500 rounded-full" />
                                    <div className="text-green-700">End-to-end encrypted</div>
                                </div>
                            )}
                        </div>

                        {!meetingStarted && timeUntilMeeting() && (
                            <Alert>
                                <Clock className="h-4 w-4" />
                                <AlertDescription>
                                    Meeting starts in {timeUntilMeeting()}
                                </AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>

                {/* Device Settings Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Device Settings</CardTitle>
                        <CardDescription>
                            Configure your camera and microphone before joining
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-center space-x-4">
                            <Button
                                variant={videoEnabled ? "default" : "outline"}
                                size="lg"
                                onClick={() => setVideoEnabled(!videoEnabled)}
                                disabled={!devicePermissions.camera}
                                className="flex items-center space-x-2"
                            >
                                {videoEnabled ? <Video className="h-5 w-5" /> : <VideoOff className="h-5 w-5" />}
                                <span>{videoEnabled ? 'Camera On' : 'Camera Off'}</span>
                            </Button>
                            
                            <Button
                                variant={audioEnabled ? "default" : "outline"}
                                size="lg"
                                onClick={() => setAudioEnabled(!audioEnabled)}
                                disabled={!devicePermissions.microphone}
                                className="flex items-center space-x-2"
                            >
                                {audioEnabled ? <Mic className="h-5 w-5" /> : <MicOff className="h-5 w-5" />}
                                <span>{audioEnabled ? 'Mic On' : 'Mic Off'}</span>
                            </Button>
                        </div>

                        {(!devicePermissions.camera || !devicePermissions.microphone) && (
                            <Alert className="mt-4">
                                <AlertDescription>
                                    Please allow camera and microphone permissions to use all meeting features.
                                </AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>

                {/* Join Button */}
                <div className="text-center">
                    {error && (
                        <Alert className="mb-4" variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {canJoin ? (
                        <Button
                            onClick={handleJoinMeeting}
                            disabled={isJoining}
                            size="lg"
                            className="px-8 py-3 text-lg"
                        >
                            {isJoining ? 'Joining...' : 'Join Meeting'}
                        </Button>
                    ) : (
                        <Alert>
                            <AlertDescription>
                                {meeting.status === 'ended' && 'This meeting has ended.'}
                                {meeting.status === 'cancelled' && 'This meeting has been cancelled.'}
                                {!['ended', 'cancelled'].includes(meeting.status) && 'This meeting is not available to join right now.'}
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                {/* Attendee Info */}
                {attendee && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center text-sm text-gray-600">
                                Joining as: <span className="font-medium">{attendee.name}</span>
                                {attendee.role !== 'attendee' && (
                                    <Badge variant="secondary" className="ml-2">
                                        {attendee.role}
                                    </Badge>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}