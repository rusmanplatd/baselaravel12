import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Calendar, CalendarEvent, CalendarEventForm } from '@/types/calendar';
import { useCalendar } from '@/hooks/useCalendar';
import { AlertCircle, Loader2 } from 'lucide-react';
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
  });

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
      });
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