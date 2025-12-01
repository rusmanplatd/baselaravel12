import { useState, useCallback, useEffect } from 'react';
import { CalendarService } from '@/services/CalendarService';
import {
  Calendar,
  CalendarEvent,
  CalendarForm,
  CalendarEventForm,
  CalendarViewState,
} from '@/types/calendar';
import { format, startOfMonth, endOfMonth, addDays, subDays } from 'date-fns';

export const useCalendar = (initialDate?: Date) => {
  const [state, setState] = useState<CalendarViewState>({
    currentDate: initialDate || new Date(),
    viewType: 'month',
    selectedCalendars: [],
    visibleCalendars: [],
    events: [],
    loading: false,
    error: undefined,
  });

  // Calendar management
  const fetchCalendars = useCallback(async (params?: {
    owner_type?: 'user' | 'organization' | 'project';
    owner_id?: string;
    visibility?: 'public' | 'private' | 'shared';
  }) => {
    try {
      setState(prev => ({ ...prev, loading: true, error: undefined }));
      const { calendars } = await CalendarService.getCalendars(params);
      setState(prev => ({ 
        ...prev, 
        visibleCalendars: calendars,
        selectedCalendars: prev.selectedCalendars.length === 0 ? calendars.map(c => c.id) : prev.selectedCalendars,
        loading: false 
      }));
      return calendars;
    } catch (error) {
      setState(prev => ({ ...prev, loading: false, error: 'Failed to fetch calendars' }));
      throw error;
    }
  }, []);

  const createCalendar = useCallback(async (data: CalendarForm) => {
    try {
      const { calendar } = await CalendarService.createCalendar(data);
      setState(prev => ({
        ...prev,
        visibleCalendars: [...prev.visibleCalendars, calendar],
        selectedCalendars: [...prev.selectedCalendars, calendar.id],
      }));
      return calendar;
    } catch (error) {
      throw error;
    }
  }, []);

  const updateCalendar = useCallback(async (id: string, data: Partial<CalendarForm>) => {
    try {
      const { calendar } = await CalendarService.updateCalendar(id, data);
      setState(prev => ({
        ...prev,
        visibleCalendars: prev.visibleCalendars.map(c => c.id === id ? calendar : c),
      }));
      return calendar;
    } catch (error) {
      throw error;
    }
  }, []);

  const deleteCalendar = useCallback(async (id: string) => {
    try {
      await CalendarService.deleteCalendar(id);
      setState(prev => ({
        ...prev,
        visibleCalendars: prev.visibleCalendars.filter(c => c.id !== id),
        selectedCalendars: prev.selectedCalendars.filter(cId => cId !== id),
        events: prev.events.filter(e => e.calendar_id !== id),
      }));
    } catch (error) {
      throw error;
    }
  }, []);

  // Event management
  const fetchEvents = useCallback(async (dateRange?: { start: Date; end: Date }) => {
    if (state.selectedCalendars.length === 0) {
      setState(prev => ({ ...prev, events: [] }));
      return [];
    }

    try {
      setState(prev => ({ ...prev, loading: true, error: undefined }));

      const range = dateRange || {
        start: subDays(startOfMonth(state.currentDate), 7),
        end: addDays(endOfMonth(state.currentDate), 7),
      };

      const { events } = await CalendarService.getEventsInRange({
        start_date: format(range.start, 'yyyy-MM-dd'),
        end_date: format(range.end, 'yyyy-MM-dd'),
        calendar_ids: state.selectedCalendars,
      });

      setState(prev => ({ ...prev, events, loading: false }));
      return events;
    } catch (error) {
      setState(prev => ({ ...prev, loading: false, error: 'Failed to fetch events' }));
      throw error;
    }
  }, [state.selectedCalendars, state.currentDate]);

  const createEvent = useCallback(async (calendarId: string, data: CalendarEventForm) => {
    try {
      const { event } = await CalendarService.createEvent(calendarId, data);
      setState(prev => ({ ...prev, events: [...prev.events, event] }));
      return event;
    } catch (error) {
      throw error;
    }
  }, []);

  const updateEvent = useCallback(async (calendarId: string, eventId: string, data: Partial<CalendarEventForm>) => {
    try {
      const { event } = await CalendarService.updateEvent(calendarId, eventId, data);
      setState(prev => ({
        ...prev,
        events: prev.events.map(e => e.id === eventId ? event : e),
      }));
      return event;
    } catch (error) {
      throw error;
    }
  }, []);

  const deleteEvent = useCallback(async (calendarId: string, eventId: string) => {
    try {
      await CalendarService.deleteEvent(calendarId, eventId);
      setState(prev => ({
        ...prev,
        events: prev.events.filter(e => e.id !== eventId),
      }));
    } catch (error) {
      throw error;
    }
  }, []);

  // View management
  const setCurrentDate = useCallback((date: Date) => {
    setState(prev => ({ ...prev, currentDate: date }));
  }, []);

  const setViewType = useCallback((viewType: 'month' | 'week' | 'day' | 'agenda') => {
    setState(prev => ({ ...prev, viewType }));
  }, []);

  const toggleCalendar = useCallback((calendarId: string) => {
    setState(prev => ({
      ...prev,
      selectedCalendars: prev.selectedCalendars.includes(calendarId)
        ? prev.selectedCalendars.filter(id => id !== calendarId)
        : [...prev.selectedCalendars, calendarId],
    }));
  }, []);

  const selectAllCalendars = useCallback(() => {
    setState(prev => ({
      ...prev,
      selectedCalendars: prev.visibleCalendars.map(c => c.id),
    }));
  }, []);

  const deselectAllCalendars = useCallback(() => {
    setState(prev => ({ ...prev, selectedCalendars: [] }));
  }, []);

  // Auto-fetch events when relevant state changes
  useEffect(() => {
    if (state.selectedCalendars.length > 0) {
      fetchEvents();
    }
  }, [state.selectedCalendars, state.currentDate, state.viewType]);

  return {
    // State
    currentDate: state.currentDate,
    viewType: state.viewType,
    selectedCalendars: state.selectedCalendars,
    visibleCalendars: state.visibleCalendars,
    events: state.events,
    loading: state.loading,
    error: state.error,

    // Calendar management
    fetchCalendars,
    createCalendar,
    updateCalendar,
    deleteCalendar,

    // Event management
    fetchEvents,
    createEvent,
    updateEvent,
    deleteEvent,

    // View management
    setCurrentDate,
    setViewType,
    toggleCalendar,
    selectAllCalendars,
    deselectAllCalendars,

    // Utility functions
    refreshData: () => {
      fetchCalendars();
      fetchEvents();
    },
  };
};