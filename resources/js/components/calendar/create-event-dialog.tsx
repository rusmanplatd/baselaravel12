import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Calendar, CalendarEvent, CalendarEventForm, Attendee, Reminder } from '@/types/calendar';
import { useCalendar } from '@/hooks/useCalendar';
import { AlertCircle, Loader2, Plus, X, Users, Bell, Video } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { format } from 'date-fns';

interface CreateEventDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  availableCalendars: Calendar[];
  onEventCreated: (event: CalendarEvent) => void;
  initialDate?: Date;
}

export function CreateEventDialog({
  open,
  onOpenChange,
  availableCalendars,
  onEventCreated,
  initialDate,
}: CreateEventDialogProps) {
  const calendar = useCalendar();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const defaultDate = initialDate || new Date();
  const defaultStartTime = format(defaultDate, "yyyy-MM-dd'T'HH:mm");
  
  // Calculate default end time (1 hour after start)
  const defaultEndDate = new Date(defaultDate);
  defaultEndDate.setHours(defaultEndDate.getHours() + 1);
  const defaultEndTime = format(defaultEndDate, "yyyy-MM-dd'T'HH:mm");

  const [formData, setFormData] = useState<CalendarEventForm & { calendar_id: string }>({
    calendar_id: availableCalendars[0]?.id || '',
    title: '',
    description: '',
    starts_at: defaultStartTime,
    ends_at: defaultEndTime,
    is_all_day: false,
    location: '',
    status: 'confirmed',
    visibility: 'public',
    meeting_url: '',
    attendees: [],
    reminders: [],
  });

  const [attendeeEmail, setAttendeeEmail] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const { calendar_id, ...eventData } = formData;
      const newEvent = await calendar.createEvent(calendar_id, eventData);
      onEventCreated(newEvent);
      
      // Reset form
      setFormData({
        calendar_id: availableCalendars[0]?.id || '',
        title: '',
        description: '',
        starts_at: defaultStartTime,
        ends_at: defaultEndTime,
        is_all_day: false,
        location: '',
        status: 'confirmed',
        visibility: 'public',
        meeting_url: '',
        attendees: [],
        reminders: [],
      });
      setAttendeeEmail('');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create event');
    } finally {
      setLoading(false);
    }
  };

  const handleAllDayToggle = (isAllDay: boolean) => {
    setFormData(prev => ({
      ...prev,
      is_all_day: isAllDay,
      ends_at: isAllDay ? undefined : prev.ends_at,
    }));
  };

  const addAttendee = () => {
    if (attendeeEmail && !formData.attendees?.find(a => a.email === attendeeEmail)) {
      setFormData(prev => ({
        ...prev,
        attendees: [
          ...(prev.attendees || []),
          {
            email: attendeeEmail,
            status: 'pending',
            added_at: new Date().toISOString(),
          }
        ]
      }));
      setAttendeeEmail('');
    }
  };

  const removeAttendee = (email: string) => {
    setFormData(prev => ({
      ...prev,
      attendees: prev.attendees?.filter(a => a.email !== email) || []
    }));
  };

  const addReminder = (minutes: number) => {
    const newReminder: Reminder = { minutes, method: 'popup' };
    if (!formData.reminders?.find(r => r.minutes === minutes && r.method === 'popup')) {
      setFormData(prev => ({
        ...prev,
        reminders: [...(prev.reminders || []), newReminder]
      }));
    }
  };

  const removeReminder = (index: number) => {
    setFormData(prev => ({
      ...prev,
      reminders: prev.reminders?.filter((_, i) => i !== index) || []
    }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>Create New Event</DialogTitle>
          <DialogDescription>
            Add a new event to your calendar.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          <div className="space-y-2">
            <Label htmlFor="calendar">Calendar</Label>
            <Select
              value={formData.calendar_id}
              onValueChange={(value) => setFormData(prev => ({ ...prev, calendar_id: value }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select a calendar" />
              </SelectTrigger>
              <SelectContent>
                {availableCalendars.map((cal) => (
                  <SelectItem key={cal.id} value={cal.id}>
                    <div className="flex items-center space-x-2">
                      <div
                        className="w-3 h-3 rounded-full"
                        style={{ backgroundColor: cal.color }}
                      />
                      <span>{cal.name}</span>
                    </div>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="title">Event Title</Label>
            <Input
              id="title"
              value={formData.title}
              onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
              placeholder="Meeting with team"
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description (Optional)</Label>
            <Textarea
              id="description"
              value={formData.description}
              onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
              placeholder="Event description..."
              rows={3}
            />
          </div>

          <div className="flex items-center space-x-2">
            <Switch
              id="all-day"
              checked={formData.is_all_day}
              onCheckedChange={handleAllDayToggle}
            />
            <Label htmlFor="all-day">All day event</Label>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="starts_at">
                {formData.is_all_day ? 'Date' : 'Starts'}
              </Label>
              <Input
                id="starts_at"
                type={formData.is_all_day ? 'date' : 'datetime-local'}
                value={formData.is_all_day 
                  ? formData.starts_at.split('T')[0] 
                  : formData.starts_at
                }
                onChange={(e) => setFormData(prev => ({ 
                  ...prev, 
                  starts_at: formData.is_all_day 
                    ? e.target.value + 'T00:00' 
                    : e.target.value 
                }))}
                required
              />
            </div>

            {!formData.is_all_day && (
              <div className="space-y-2">
                <Label htmlFor="ends_at">Ends</Label>
                <Input
                  id="ends_at"
                  type="datetime-local"
                  value={formData.ends_at || ''}
                  onChange={(e) => setFormData(prev => ({ ...prev, ends_at: e.target.value }))}
                />
              </div>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="location">Location (Optional)</Label>
            <Input
              id="location"
              value={formData.location}
              onChange={(e) => setFormData(prev => ({ ...prev, location: e.target.value }))}
              placeholder="Conference room, online, etc."
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="meeting_url">
              <Video className="w-4 h-4 inline mr-1" />
              Meeting URL (Optional)
            </Label>
            <Input
              id="meeting_url"
              type="url"
              value={formData.meeting_url}
              onChange={(e) => setFormData(prev => ({ ...prev, meeting_url: e.target.value }))}
              placeholder="https://meet.google.com/..."
            />
          </div>

          {/* Attendees Section */}
          <div className="space-y-2">
            <Label>
              <Users className="w-4 h-4 inline mr-1" />
              Attendees (Optional)
            </Label>
            <div className="flex space-x-2">
              <Input
                value={attendeeEmail}
                onChange={(e) => setAttendeeEmail(e.target.value)}
                placeholder="Enter email address"
                type="email"
                onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addAttendee())}
              />
              <Button type="button" onClick={addAttendee} size="sm">
                <Plus className="w-4 h-4" />
              </Button>
            </div>
            {formData.attendees && formData.attendees.length > 0 && (
              <div className="flex flex-wrap gap-2 mt-2">
                {formData.attendees.map((attendee) => (
                  <Badge key={attendee.email} variant="secondary" className="flex items-center gap-1">
                    {attendee.email}
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-4 w-4 p-0 hover:bg-transparent"
                      onClick={() => removeAttendee(attendee.email || '')}
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  </Badge>
                ))}
              </div>
            )}
          </div>

          {/* Reminders Section */}
          <div className="space-y-2">
            <Label>
              <Bell className="w-4 h-4 inline mr-1" />
              Reminders (Optional)
            </Label>
            <div className="flex flex-wrap gap-2">
              {[5, 15, 30, 60, 1440].map((minutes) => (
                <Button
                  key={minutes}
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => addReminder(minutes)}
                  disabled={formData.reminders?.some(r => r.minutes === minutes)}
                >
                  {minutes < 60 ? `${minutes}min` : minutes === 1440 ? '1 day' : `${minutes/60}h`}
                </Button>
              ))}
            </div>
            {formData.reminders && formData.reminders.length > 0 && (
              <div className="flex flex-wrap gap-2 mt-2">
                {formData.reminders.map((reminder, index) => (
                  <Badge key={index} variant="secondary" className="flex items-center gap-1">
                    {reminder.minutes < 60 
                      ? `${reminder.minutes} min before` 
                      : reminder.minutes === 1440 
                        ? '1 day before'
                        : `${reminder.minutes/60}h before`
                    }
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-4 w-4 p-0 hover:bg-transparent"
                      onClick={() => removeReminder(index)}
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  </Badge>
                ))}
              </div>
            )}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="status">Status</Label>
              <Select
                value={formData.status}
                onValueChange={(value: any) => setFormData(prev => ({ ...prev, status: value }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="confirmed">Confirmed</SelectItem>
                  <SelectItem value="tentative">Tentative</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="visibility">Visibility</Label>
              <Select
                value={formData.visibility}
                onValueChange={(value: any) => setFormData(prev => ({ ...prev, visibility: value }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="public">Public</SelectItem>
                  <SelectItem value="private">Private</SelectItem>
                  <SelectItem value="confidential">Confidential</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="flex justify-end space-x-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={loading}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={loading || !formData.calendar_id}>
              {loading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Creating...
                </>
              ) : (
                'Create Event'
              )}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}