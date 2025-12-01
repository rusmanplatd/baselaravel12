import { useState, useCallback } from 'react';
import apiService from '@/services/ApiService';

interface Meeting {
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
}

interface MeetingSettings {
    audio_enabled?: boolean;
    video_enabled?: boolean;
    screen_sharing_enabled?: boolean;
    chat_enabled?: boolean;
    waiting_room_enabled?: boolean;
    mute_on_entry?: boolean;
    camera_on_entry?: boolean;
    max_participants?: number;
    recording_enabled?: boolean;
    e2ee_enabled?: boolean;
}

interface CreateMeetingData {
    calendar_event_id: string;
    meeting_settings?: MeetingSettings;
}

interface AttendeeData {
    email: string;
    name?: string;
    role?: 'attendee' | 'presenter' | 'co-host' | 'host';
}

export function useMeeting() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const createMeetingFromCalendarEvent = useCallback(async (data: CreateMeetingData) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.post('/api/v1/meetings/from-calendar-event', data);
            
            if (response.success) {
                return response.meeting;
            } else {
                throw new Error(response.message || 'Failed to create meeting');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const getMeeting = useCallback(async (meetingId: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.get(`/api/v1/meetings/${meetingId}`);
            
            if (response.success) {
                return response.meeting;
            } else {
                throw new Error('Failed to fetch meeting');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const startMeeting = useCallback(async (meetingId: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meetingId}/start`);
            
            if (response.success) {
                return response;
            } else {
                throw new Error(response.message || 'Failed to start meeting');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const joinMeeting = useCallback(async (meetingId: string, permissions?: Record<string, any>) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meetingId}/join`, {
                permissions: permissions || {}
            });
            
            if (response.success) {
                return response.connection;
            } else {
                throw new Error(response.message || 'Failed to join meeting');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const endMeeting = useCallback(async (meetingId: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meetingId}/end`);
            
            if (response.success) {
                return response;
            } else {
                throw new Error(response.message || 'Failed to end meeting');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const cancelMeeting = useCallback(async (meetingId: string, reason?: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meetingId}/cancel`, {
                reason
            });
            
            if (response.success) {
                return response;
            } else {
                throw new Error(response.message || 'Failed to cancel meeting');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const updateMeetingSettings = useCallback(async (meetingId: string, settings: MeetingSettings) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.patch(`/api/v1/meetings/${meetingId}/settings`, {
                settings
            });
            
            if (response.success) {
                return response.meeting;
            } else {
                throw new Error(response.message || 'Failed to update settings');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const getMeetingParticipants = useCallback(async (meetingId: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.get(`/api/v1/meetings/${meetingId}/participants`);
            
            if (response.success) {
                return response.participants;
            } else {
                throw new Error('Failed to fetch participants');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const addAttendee = useCallback(async (meetingId: string, attendeeData: AttendeeData) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.post(`/api/v1/meetings/${meetingId}/attendees`, attendeeData);
            
            if (response.success) {
                return response.attendee;
            } else {
                throw new Error(response.message || 'Failed to add attendee');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const removeAttendee = useCallback(async (meetingId: string, attendeeId: string, reason?: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.delete(`/api/v1/meetings/${meetingId}/attendees`, {
                attendee_id: attendeeId,
                reason
            });
            
            if (response.success) {
                return response;
            } else {
                throw new Error(response.message || 'Failed to remove attendee');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const getUpcomingMeetings = useCallback(async (limit = 10) => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiService.get(`/api/v1/meetings/upcoming?limit=${limit}`);
            
            if (response.success) {
                return response.meetings;
            } else {
                throw new Error('Failed to fetch upcoming meetings');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const getMeetings = useCallback(async (params?: { status?: string; limit?: number; page?: number }) => {
        setLoading(true);
        setError(null);

        try {
            const searchParams = new URLSearchParams();
            if (params?.status) searchParams.append('status', params.status);
            if (params?.limit) searchParams.append('limit', params.limit.toString());
            if (params?.page) searchParams.append('page', params.page.toString());

            const response = await apiService.get(`/api/v1/meetings?${searchParams.toString()}`);
            
            if (response.success) {
                return {
                    meetings: response.meetings,
                    pagination: response.pagination
                };
            } else {
                throw new Error('Failed to fetch meetings');
            }
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || 'An error occurred';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    }, []);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    return {
        loading,
        error,
        clearError,
        createMeetingFromCalendarEvent,
        getMeeting,
        startMeeting,
        joinMeeting,
        endMeeting,
        cancelMeeting,
        updateMeetingSettings,
        getMeetingParticipants,
        addAttendee,
        removeAttendee,
        getUpcomingMeetings,
        getMeetings,
    };
}