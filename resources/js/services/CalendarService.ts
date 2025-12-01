import apiService from './ApiService';
import { Calendar, CalendarEvent, CalendarEventForm, CalendarForm } from '@/types/calendar';

export class CalendarService {
  static async getCalendars(params?: {
    owner_type?: 'user' | 'organization' | 'project';
    owner_id?: string;
    visibility?: 'public' | 'private' | 'shared';
  }): Promise<{ calendars: Calendar[] }> {
    return apiService.get('/api/v1/calendars', { params });
  }

  static async createCalendar(data: CalendarForm): Promise<{ calendar: Calendar }> {
    return apiService.post('/api/v1/calendars', data);
  }

  static async getCalendar(id: string): Promise<{ calendar: Calendar }> {
    return apiService.get(`/api/v1/calendars/${id}`);
  }

  static async updateCalendar(id: string, data: Partial<CalendarForm>): Promise<{ calendar: Calendar }> {
    return apiService.put(`/api/v1/calendars/${id}`, data);
  }

  static async deleteCalendar(id: string): Promise<{ message: string }> {
    return apiService.delete(`/api/v1/calendars/${id}`);
  }

  static async shareCalendar(
    id: string,
    data: { user_id: string; permission: 'read' | 'write' | 'admin' }
  ): Promise<{ message: string; permission: any }> {
    return apiService.post(`/api/v1/calendars/${id}/share`, data);
  }

  static async revokeAccess(id: string, userId: string): Promise<{ message: string }> {
    return apiService.post(`/api/v1/calendars/${id}/revoke-access`, { user_id: userId });
  }

  static async getEvents(
    calendarId: string,
    params?: {
      start_date?: string;
      end_date?: string;
      status?: 'confirmed' | 'tentative' | 'cancelled';
      visibility?: 'public' | 'private' | 'confidential';
    }
  ): Promise<{ events: CalendarEvent[] }> {
    return apiService.get(`/api/v1/calendars/${calendarId}/events`, { params });
  }

  static async createEvent(calendarId: string, data: CalendarEventForm): Promise<{ event: CalendarEvent }> {
    return apiService.post(`/api/v1/calendars/${calendarId}/events`, data);
  }

  static async getEvent(calendarId: string, eventId: string): Promise<{ event: CalendarEvent }> {
    return apiService.get(`/api/v1/calendars/${calendarId}/events/${eventId}`);
  }

  static async updateEvent(
    calendarId: string,
    eventId: string,
    data: Partial<CalendarEventForm>
  ): Promise<{ event: CalendarEvent }> {
    return apiService.put(`/api/v1/calendars/${calendarId}/events/${eventId}`, data);
  }

  static async deleteEvent(calendarId: string, eventId: string): Promise<{ message: string }> {
    return apiService.delete(`/api/v1/calendars/${calendarId}/events/${eventId}`);
  }

  static async getEventsInRange(params: {
    start_date: string;
    end_date: string;
    calendar_ids?: string[];
  }): Promise<{ events: CalendarEvent[]; period: { start: string; end: string } }> {
    return apiService.get('/api/v1/events', { params });
  }

  static async updateAttendeeStatus(
    calendarId: string,
    eventId: string,
    data: { email: string; status: 'accepted' | 'declined' | 'tentative' }
  ): Promise<{ message: string }> {
    return apiService.post(`/api/v1/calendars/${calendarId}/events/${eventId}/attendee-status`, data);
  }
}

export default CalendarService;