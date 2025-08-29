import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { CalendarIcon, ClockIcon, SendIcon } from '@heroicons/react/24/outline';
import { format, addMinutes, startOfTomorrow } from 'date-fns';
import { cn } from '@/lib/utils';

interface MessageSchedulerProps {
  onScheduleMessage: (content: string, scheduledAt: Date, options?: {
    deleteAfter?: number;
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    requiresConfirmation?: boolean;
  }) => void;
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  initialContent?: string;
}

const QUICK_SCHEDULE_OPTIONS = [
  { label: 'In 5 minutes', value: 5 },
  { label: 'In 15 minutes', value: 15 },
  { label: 'In 30 minutes', value: 30 },
  { label: 'In 1 hour', value: 60 },
  { label: 'In 2 hours', value: 120 },
  { label: 'Tomorrow 9 AM', value: 'tomorrow_9am' },
];

const PRIORITY_OPTIONS = [
  { label: 'Low', value: 'low', description: 'Silent delivery' },
  { label: 'Normal', value: 'normal', description: 'Standard notification' },
  { label: 'High', value: 'high', description: 'Important notification' },
  { label: 'Urgent', value: 'urgent', description: 'Critical alert' },
];

const DISAPPEAR_OPTIONS = [
  { label: 'Never', value: 0 },
  { label: '5 minutes', value: 5 },
  { label: '1 hour', value: 60 },
  { label: '24 hours', value: 1440 },
  { label: '7 days', value: 10080 },
];

export default function MessageScheduler({ 
  onScheduleMessage, 
  isOpen, 
  onOpenChange, 
  initialContent = '' 
}: MessageSchedulerProps) {
  const [content, setContent] = useState(initialContent);
  const [scheduledDate, setScheduledDate] = useState<Date | undefined>();
  const [scheduledTime, setScheduledTime] = useState('');
  const [priority, setPriority] = useState<'low' | 'normal' | 'high' | 'urgent'>('normal');
  const [deleteAfter, setDeleteAfter] = useState(0);
  const [requiresConfirmation, setRequiresConfirmation] = useState(false);

  const handleQuickSchedule = (option: typeof QUICK_SCHEDULE_OPTIONS[0]) => {
    const now = new Date();
    let scheduledAt: Date;

    if (option.value === 'tomorrow_9am') {
      scheduledAt = new Date(startOfTomorrow());
      scheduledAt.setHours(9, 0, 0, 0);
    } else {
      scheduledAt = addMinutes(now, option.value as number);
    }

    setScheduledDate(scheduledAt);
    setScheduledTime(format(scheduledAt, 'HH:mm'));
  };

  const handleSubmit = () => {
    if (!content.trim() || !scheduledDate || !scheduledTime) {
      return;
    }

    const [hours, minutes] = scheduledTime.split(':').map(Number);
    const finalScheduledDate = new Date(scheduledDate);
    finalScheduledDate.setHours(hours, minutes, 0, 0);

    // Validate future date
    if (finalScheduledDate <= new Date()) {
      return;
    }

    onScheduleMessage(content, finalScheduledDate, {
      deleteAfter: deleteAfter > 0 ? deleteAfter : undefined,
      priority,
      requiresConfirmation,
    });

    // Reset form
    setContent('');
    setScheduledDate(undefined);
    setScheduledTime('');
    setPriority('normal');
    setDeleteAfter(0);
    setRequiresConfirmation(false);
    onOpenChange(false);
  };

  const isValid = content.trim() && scheduledDate && scheduledTime && 
    (() => {
      const [hours, minutes] = scheduledTime.split(':').map(Number);
      const testDate = new Date(scheduledDate);
      testDate.setHours(hours, minutes, 0, 0);
      return testDate > new Date();
    })();

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ClockIcon className="h-5 w-5" />
            Schedule Encrypted Message
          </DialogTitle>
          <DialogDescription>
            Schedule a message to be sent later. Messages are encrypted immediately and delivered at the scheduled time.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          {/* Message Content */}
          <div className="space-y-2">
            <Label htmlFor="scheduled-message">Message</Label>
            <textarea
              id="scheduled-message"
              className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              placeholder="Type your message..."
              value={content}
              onChange={(e) => setContent(e.target.value)}
            />
          </div>

          {/* Quick Schedule Options */}
          <div className="space-y-2">
            <Label>Quick Schedule</Label>
            <div className="grid grid-cols-2 gap-2">
              {QUICK_SCHEDULE_OPTIONS.map((option) => (
                <Button
                  key={option.label}
                  variant="outline"
                  size="sm"
                  onClick={() => handleQuickSchedule(option)}
                  className="text-xs"
                >
                  {option.label}
                </Button>
              ))}
            </div>
          </div>

          {/* Custom Date & Time */}
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Date</Label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      'w-full justify-start text-left font-normal',
                      !scheduledDate && 'text-muted-foreground'
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {scheduledDate ? format(scheduledDate, 'PPP') : 'Pick a date'}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0">
                  <Calendar
                    mode="single"
                    selected={scheduledDate}
                    onSelect={setScheduledDate}
                    disabled={(date) => date < new Date() || date < new Date('1900-01-01')}
                    initialFocus
                  />
                </PopoverContent>
              </Popover>
            </div>

            <div className="space-y-2">
              <Label htmlFor="scheduled-time">Time</Label>
              <Input
                id="scheduled-time"
                type="time"
                value={scheduledTime}
                onChange={(e) => setScheduledTime(e.target.value)}
              />
            </div>
          </div>

          {/* Message Options */}
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Priority</Label>
                <Select value={priority} onValueChange={(value: any) => setPriority(value)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {PRIORITY_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>
                        <div>
                          <div className="font-medium">{option.label}</div>
                          <div className="text-xs text-muted-foreground">{option.description}</div>
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label>Auto-delete after</Label>
                <Select value={deleteAfter.toString()} onValueChange={(value) => setDeleteAfter(parseInt(value))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {DISAPPEAR_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value.toString()}>
                        {option.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="requires-confirmation"
                checked={requiresConfirmation}
                onChange={(e) => setRequiresConfirmation(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <Label htmlFor="requires-confirmation" className="text-sm">
                Require delivery confirmation
              </Label>
            </div>
          </div>

          {/* Schedule Button */}
          <div className="flex justify-end space-x-2">
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button 
              onClick={handleSubmit} 
              disabled={!isValid}
              className="bg-blue-600 hover:bg-blue-700"
            >
              <SendIcon className="h-4 w-4 mr-2" />
              Schedule Message
            </Button>
          </div>

          {scheduledDate && scheduledTime && (
            <div className="text-sm text-muted-foreground bg-muted p-2 rounded">
              Will be delivered: {format(
                (() => {
                  const [hours, minutes] = scheduledTime.split(':').map(Number);
                  const date = new Date(scheduledDate);
                  date.setHours(hours, minutes, 0, 0);
                  return date;
                })(),
                'PPpp'
              )}
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}