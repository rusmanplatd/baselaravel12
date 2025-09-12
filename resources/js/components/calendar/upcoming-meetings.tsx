import React, { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Video, Clock, Users, Calendar, ExternalLink, Loader2 } from 'lucide-react';
import { format, isToday, isTomorrow, isYesterday, differenceInMinutes } from 'date-fns';
import { useMeeting } from '@/hooks/useMeeting';

interface UpcomingMeetingsProps {
    limit?: number;
    className?: string;
    showJoinButton?: boolean;
}

export function UpcomingMeetings({ 
    limit = 5, 
    className = '',
    showJoinButton = true
}: UpcomingMeetingsProps) {
    const meeting = useMeeting();
    const [meetings, setMeetings] = useState<any[]>([]);

    useEffect(() => {
        loadUpcomingMeetings();
    }, [limit]);

    const loadUpcomingMeetings = async () => {
        try {
            const upcomingMeetings = await meeting.getUpcomingMeetings(limit);
            setMeetings(upcomingMeetings);
        } catch (error) {
            console.error('Failed to load upcoming meetings:', error);
        }
    };

    const getDateLabel = (date: Date) => {
        if (isToday(date)) return 'Today';
        if (isTomorrow(date)) return 'Tomorrow';
        if (isYesterday(date)) return 'Yesterday';
        return format(date, 'MMM d, yyyy');
    };

    const getTimeUntilMeeting = (startTime: Date) => {
        const now = new Date();
        const diffMinutes = differenceInMinutes(startTime, now);
        
        if (diffMinutes <= 0) return 'Starting now';
        if (diffMinutes < 60) return `${diffMinutes} min`;
        
        const hours = Math.floor(diffMinutes / 60);
        const remainingMinutes = diffMinutes % 60;
        
        if (hours < 24) {
            return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
        }
        
        const days = Math.floor(hours / 24);
        return `${days}d`;
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'active': return 'default';
            case 'scheduled': return 'secondary';
            default: return 'outline';
        }
    };

    const handleJoinMeeting = (meetingId: string, joinUrl: string) => {
        window.open(joinUrl, '_blank');
    };

    if (meeting.loading) {
        return (
            <Card className={className}>
                <CardHeader>
                    <CardTitle className="flex items-center space-x-2">
                        <Video className="h-5 w-5" />
                        <span>Upcoming Meetings</span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin" />
                </CardContent>
            </Card>
        );
    }

    if (meeting.error) {
        return (
            <Card className={className}>
                <CardHeader>
                    <CardTitle className="flex items-center space-x-2">
                        <Video className="h-5 w-5" />
                        <span>Upcoming Meetings</span>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Alert variant="destructive">
                        <AlertDescription>{meeting.error}</AlertDescription>
                    </Alert>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                    <Video className="h-5 w-5" />
                    <span>Upcoming Meetings</span>
                </CardTitle>
                <CardDescription>
                    Your next {limit} scheduled meetings
                </CardDescription>
            </CardHeader>
            <CardContent>
                {meetings.length === 0 ? (
                    <div className="text-center py-8 text-gray-500">
                        <Video className="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p className="text-sm">No upcoming meetings</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {meetings.map((mtg) => {
                            const startTime = new Date(mtg.calendar_event.starts_at);
                            const endTime = new Date(mtg.calendar_event.ends_at);
                            const timeUntil = getTimeUntilMeeting(startTime);
                            const canJoin = mtg.status === 'active' || differenceInMinutes(startTime, new Date()) <= 15;
                            
                            return (
                                <div key={mtg.id} className="border rounded-lg p-4 space-y-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1 min-w-0">
                                            <h4 className="font-medium text-sm truncate">
                                                {mtg.calendar_event.title}
                                            </h4>
                                            <div className="flex items-center space-x-4 text-xs text-gray-500 mt-1">
                                                <div className="flex items-center space-x-1">
                                                    <Calendar className="h-3 w-3" />
                                                    <span>{getDateLabel(startTime)}</span>
                                                </div>
                                                <div className="flex items-center space-x-1">
                                                    <Clock className="h-3 w-3" />
                                                    <span>{format(startTime, 'h:mm a')} - {format(endTime, 'h:mm a')}</span>
                                                </div>
                                                <div className="flex items-center space-x-1">
                                                    <Users className="h-3 w-3" />
                                                    <span>{mtg.attendee_count} attendees</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center space-x-2 flex-shrink-0">
                                            <Badge variant={getStatusVariant(mtg.status)} className="text-xs">
                                                {mtg.status}
                                            </Badge>
                                            {mtg.e2ee_enabled && (
                                                <Badge variant="outline" className="text-xs">E2EE</Badge>
                                            )}
                                        </div>
                                    </div>
                                    
                                    <div className="flex items-center justify-between">
                                        <div className="text-xs text-gray-600">
                                            {mtg.status === 'active' ? (
                                                <span className="text-green-600 font-medium">Meeting in progress</span>
                                            ) : (
                                                <span>Starts in {timeUntil}</span>
                                            )}
                                        </div>
                                        
                                        {showJoinButton && (
                                            <div className="flex space-x-2">
                                                {canJoin && (
                                                    <Button
                                                        size="sm"
                                                        variant={mtg.status === 'active' ? 'default' : 'outline'}
                                                        onClick={() => handleJoinMeeting(mtg.id, mtg.join_url)}
                                                        className="text-xs"
                                                    >
                                                        {mtg.status === 'active' ? 'Join Now' : 'Join Early'}
                                                    </Button>
                                                )}
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => window.open(mtg.host_url, '_blank')}
                                                    className="text-xs"
                                                >
                                                    <ExternalLink className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                        
                        {meetings.length === limit && (
                            <div className="text-center pt-4">
                                <Button variant="outline" size="sm">
                                    View All Meetings
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}