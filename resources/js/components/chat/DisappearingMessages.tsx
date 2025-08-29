import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { EyeSlashIcon, ClockIcon, FireIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';
import { formatDistanceToNow, differenceInSeconds } from 'date-fns';

interface DisappearingMessage {
  id: string;
  content: string;
  expiresAt: Date;
  timeRemaining: number;
  isExpired: boolean;
}

interface DisappearingMessagesProps {
  conversationId: string;
  onSetDisappearingTimer: (minutes: number) => void;
  onMessageExpired: (messageId: string) => void;
  currentTimer?: number; // in minutes
  messages: DisappearingMessage[];
}

const TIMER_OPTIONS = [
  { label: 'Off', value: 0 },
  { label: '30 seconds', value: 0.5 },
  { label: '1 minute', value: 1 },
  { label: '5 minutes', value: 5 },
  { label: '15 minutes', value: 15 },
  { label: '30 minutes', value: 30 },
  { label: '1 hour', value: 60 },
  { label: '6 hours', value: 360 },
  { label: '24 hours', value: 1440 },
  { label: '7 days', value: 10080 },
];

export default function DisappearingMessages({
  conversationId,
  onSetDisappearingTimer,
  onMessageExpired,
  currentTimer = 0,
  messages = []
}: DisappearingMessagesProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedTimer, setSelectedTimer] = useState(currentTimer);
  const [messageTimers, setMessageTimers] = useState<Record<string, number>>({});

  // Update message timers every second
  useEffect(() => {
    const interval = setInterval(() => {
      const now = new Date();
      const updatedTimers: Record<string, number> = {};

      messages.forEach((message) => {
        const remaining = Math.max(0, differenceInSeconds(message.expiresAt, now));
        updatedTimers[message.id] = remaining;

        // Handle expiration
        if (remaining === 0 && !message.isExpired) {
          onMessageExpired(message.id);
        }
      });

      setMessageTimers(updatedTimers);
    }, 1000);

    return () => clearInterval(interval);
  }, [messages, onMessageExpired]);

  const handleTimerChange = () => {
    onSetDisappearingTimer(selectedTimer);
    setIsOpen(false);
  };

  const formatTimeRemaining = (seconds: number): string => {
    if (seconds <= 0) return 'Expired';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
  };

  const getProgressPercentage = (message: DisappearingMessage): number => {
    const remaining = messageTimers[message.id] || 0;
    const total = differenceInSeconds(message.expiresAt, new Date(message.expiresAt.getTime() - (currentTimer * 60 * 1000)));
    return Math.max(0, (remaining / total) * 100);
  };

  const getTimerColor = (seconds: number): string => {
    if (seconds <= 30) return 'text-red-600';
    if (seconds <= 120) return 'text-orange-600';
    return 'text-green-600';
  };

  const currentTimerLabel = TIMER_OPTIONS.find(opt => opt.value === currentTimer)?.label || 'Off';

  return (
    <>
      {/* Timer Status Badge */}
      {currentTimer > 0 && (
        <Badge variant="secondary" className="flex items-center gap-1 text-xs">
          <ClockIcon className="h-3 w-3" />
          Disappearing: {currentTimerLabel}
        </Badge>
      )}

      {/* Settings Dialog */}
      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogTrigger asChild>
          <Button variant="ghost" size="sm" className="flex items-center gap-2">
            <EyeSlashIcon className="h-4 w-4" />
            Disappearing Messages
          </Button>
        </DialogTrigger>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FireIcon className="h-5 w-5 text-orange-500" />
              Disappearing Messages
            </DialogTitle>
            <DialogDescription>
              Set a timer for messages to automatically delete after being read. This applies to new messages only.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">Timer Duration</label>
              <Select 
                value={selectedTimer.toString()} 
                onValueChange={(value) => setSelectedTimer(parseFloat(value))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TIMER_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value.toString()}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {selectedTimer > 0 && (
              <div className="bg-muted p-3 rounded-lg space-y-2">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <ShieldCheckIcon className="h-4 w-4" />
                  <span>End-to-end encrypted</span>
                </div>
                <div className="text-xs text-muted-foreground">
                  Messages will be automatically deleted {selectedTimer < 1 ? 'in seconds' : selectedTimer < 60 ? 'in minutes' : selectedTimer < 1440 ? 'in hours' : 'in days'} after being delivered.
                </div>
              </div>
            )}

            <div className="flex justify-end space-x-2">
              <Button variant="outline" onClick={() => setIsOpen(false)}>
                Cancel
              </Button>
              <Button onClick={handleTimerChange}>
                {selectedTimer === currentTimer ? 'Close' : 'Save'}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Active Disappearing Messages List */}
      {messages.length > 0 && (
        <div className="space-y-2 max-h-96 overflow-y-auto">
          <div className="text-xs font-medium text-muted-foreground mb-2">
            Messages with timers ({messages.filter(m => !m.isExpired).length} active)
          </div>
          {messages.map((message) => {
            const remaining = messageTimers[message.id] || 0;
            const progress = getProgressPercentage(message);
            const isExpiring = remaining <= 30;

            return (
              <div
                key={message.id}
                className={`bg-muted/50 rounded-lg p-3 space-y-2 ${
                  message.isExpired ? 'opacity-50' : ''
                } ${isExpiring ? 'border border-red-200 bg-red-50' : ''}`}
              >
                <div className="text-sm truncate">{message.content}</div>
                
                {!message.isExpired ? (
                  <div className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className={getTimerColor(remaining)}>
                        {formatTimeRemaining(remaining)}
                      </span>
                      <ClockIcon className="h-3 w-3 text-muted-foreground" />
                    </div>
                    <Progress 
                      value={progress} 
                      className={`h-1 ${isExpiring ? 'bg-red-200' : ''}`}
                    />
                  </div>
                ) : (
                  <div className="text-xs text-muted-foreground flex items-center gap-1">
                    <FireIcon className="h-3 w-3" />
                    Message deleted
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </>
  );
}

export { DisappearingMessages };