export interface Calendar {
  id: string;
  name: string;
  description?: string;
  color: string;
  timezone: string;
  visibility: 'public' | 'private' | 'shared';
  owner_type: 'User' | 'Organization' | 'Project';
  owner_id: string;
  owner_name: string;
  events_count?: number;
  permissions?: CalendarPermission[];
  settings?: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

export interface CalendarEvent {
  id: string;
  calendar_id: string;
  title: string;
  description?: string;
  starts_at: string;
  ends_at?: string;
  is_all_day: boolean;
  location?: string;
  color: string;
  status: 'confirmed' | 'tentative' | 'cancelled';
  visibility: 'public' | 'private' | 'confidential';
  is_recurring: boolean;
  recurrence_rule?: string;
  recurrence_parent_id?: string;
  duration_minutes: number;
  attendees?: Attendee[];
  reminders?: Reminder[];
  metadata?: Record<string, unknown>;
  meeting_url?: string;
  creator?: {
    id: string;
    name: string;
  };
  calendar?: {
    id: string;
    name: string;
    color: string;
  };
  created_at: string;
  updated_at: string;
}

export interface CalendarPermission {
  user_id: string;
  user_name: string;
  permission: 'read' | 'write' | 'admin';
  granted_at: string;
}

export interface Attendee {
  email: string;
  name?: string;
  status: 'pending' | 'accepted' | 'declined' | 'tentative';
  added_at: string;
  responded_at?: string;
}

export interface Reminder {
  minutes: number;
  method: 'popup' | 'email';
}

export interface CalendarForm {
  name: string;
  description?: string;
  color?: string;
  timezone?: string;
  owner_type: 'user' | 'organization' | 'project';
  owner_id: string;
  visibility?: 'public' | 'private' | 'shared';
  settings?: Record<string, unknown>;
}

export interface CalendarEventForm {
  title: string;
  description?: string;
  starts_at: string;
  ends_at?: string;
  is_all_day?: boolean;
  location?: string;
  color?: string;
  status?: 'confirmed' | 'tentative' | 'cancelled';
  visibility?: 'public' | 'private' | 'confidential';
  recurrence_rule?: string;
  attendees?: Partial<Attendee>[];
  reminders?: Reminder[];
  meeting_url?: string;
  metadata?: Record<string, unknown>;
}

export interface CalendarViewState {
  currentDate: Date;
  viewType: 'month' | 'week' | 'day' | 'agenda';
  selectedCalendars: string[];
  visibleCalendars: Calendar[];
  events: CalendarEvent[];
  loading: boolean;
  error?: string;
}

export interface CreateEventPayload extends CalendarEventForm {
  calendar_id: string;
}