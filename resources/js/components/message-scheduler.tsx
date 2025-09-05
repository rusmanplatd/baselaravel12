import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Calendar, Clock, Send, Trash2, Edit, Pause, Play, AlertCircle } from 'lucide-react';
import { toast } from 'sonner';

interface ScheduledMessage {
  id: string;
  conversation_id: string;
  content: string;
  content_type: 'text' | 'markdown' | 'html';
  scheduled_for: string;
  status: 'scheduled' | 'sending' | 'sent' | 'failed' | 'cancelled';
  retry_count: number;
  error_message?: string;
  created_at: string;
  updated_at: string;
}

interface MessageSchedulerProps {
  conversationId: string;
  onScheduled?: (message: ScheduledMessage) => void;
  defaultContent?: string;
  defaultScheduleTime?: Date;
}

export const MessageScheduler: React.FC<MessageSchedulerProps> = ({
  conversationId,
  onScheduled,
  defaultContent = '',
  defaultScheduleTime,
}) => {
  const [content, setContent] = useState(defaultContent);
  const [contentType, setContentType] = useState<'text' | 'markdown' | 'html'>('text');
  const [scheduleTime, setScheduleTime] = useState<string>('');
  const [timezone, setTimezone] = useState(Intl.DateTimeFormat().resolvedOptions().timeZone);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (defaultScheduleTime) {
      const isoString = defaultScheduleTime.toISOString().slice(0, 16);
      setScheduleTime(isoString);
    }
  }, [defaultScheduleTime]);

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!content.trim()) {
      newErrors.content = 'Message content is required';
    } else if (content.length > 10000) {
      newErrors.content = 'Message content cannot exceed 10,000 characters';
    }

    if (!scheduleTime) {
      newErrors.scheduleTime = 'Schedule time is required';
    } else {
      const scheduledDate = new Date(scheduleTime);
      const now = new Date();
      
      if (scheduledDate <= now) {
        newErrors.scheduleTime = 'Schedule time must be in the future';
      }
      
      const maxFuture = new Date();
      maxFuture.setDate(maxFuture.getDate() + 365); // 1 year max
      
      if (scheduledDate > maxFuture) {
        newErrors.scheduleTime = 'Cannot schedule messages more than 1 year in advance';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSchedule = async () => {
    if (!validateForm()) {
      return;
    }

    setLoading(true);
    try {
      const response = await fetch('/api/v1/scheduled-messages', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          conversation_id: conversationId,
          content: content.trim(),
          content_type: contentType,
          scheduled_for: scheduleTime,
          timezone,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Failed to schedule message');
      }

      const data = await response.json();
      
      if (onScheduled) {
        onScheduled(data.scheduled_message);
      }

      // Reset form
      setContent('');
      setScheduleTime('');
      setErrors({});

      toast.success('Message scheduled successfully!');
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Failed to schedule message');
    } finally {
      setLoading(false);
    }
  };

  const getMinDateTime = (): string => {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 1); // At least 1 minute in the future
    return now.toISOString().slice(0, 16);
  };

  const getMaxDateTime = (): string => {
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 365);
    return maxDate.toISOString().slice(0, 16);
  };

  return (
    <Card className="w-full max-w-2xl">
      <CardHeader>
        <CardTitle className="flex items-center space-x-2">
          <Calendar className="h-5 w-5" />
          <span>Schedule Message</span>
        </CardTitle>
      </CardHeader>
      
      <CardContent className="space-y-4">
        {/* Content Type Selector */}
        <div className="space-y-2">
          <label className="block text-sm font-medium">Content Type</label>
          <div className="flex space-x-2">
            {(['text', 'markdown', 'html'] as const).map((type) => (
              <Button
                key={type}
                variant={contentType === type ? 'default' : 'outline'}
                size="sm"
                onClick={() => setContentType(type)}
              >
                {type.toUpperCase()}
              </Button>
            ))}
          </div>
        </div>

        {/* Message Content */}
        <div className="space-y-2">
          <label htmlFor="content" className="block text-sm font-medium">
            Message Content
          </label>
          <textarea
            id="content"
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="Enter your message content..."
            rows={4}
            className={`w-full px-3 py-2 border rounded-md resize-none ${
              errors.content ? 'border-red-300' : 'border-gray-300'
            }`}
          />
          {errors.content && (
            <div className="text-red-500 text-sm flex items-center space-x-1">
              <AlertCircle className="h-4 w-4" />
              <span>{errors.content}</span>
            </div>
          )}
          <div className="text-xs text-gray-500">
            {content.length}/10,000 characters
          </div>
        </div>

        {/* Schedule Time */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-2">
            <label htmlFor="scheduleTime" className="block text-sm font-medium">
              Schedule Time
            </label>
            <input
              id="scheduleTime"
              type="datetime-local"
              value={scheduleTime}
              onChange={(e) => setScheduleTime(e.target.value)}
              min={getMinDateTime()}
              max={getMaxDateTime()}
              className={`w-full px-3 py-2 border rounded-md ${
                errors.scheduleTime ? 'border-red-300' : 'border-gray-300'
              }`}
            />
            {errors.scheduleTime && (
              <div className="text-red-500 text-sm flex items-center space-x-1">
                <AlertCircle className="h-4 w-4" />
                <span>{errors.scheduleTime}</span>
              </div>
            )}
          </div>

          <div className="space-y-2">
            <label htmlFor="timezone" className="block text-sm font-medium">
              Timezone
            </label>
            <select
              id="timezone"
              value={timezone}
              onChange={(e) => setTimezone(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              <option value={Intl.DateTimeFormat().resolvedOptions().timeZone}>
                {Intl.DateTimeFormat().resolvedOptions().timeZone} (Local)
              </option>
              <option value="UTC">UTC</option>
              <option value="America/New_York">Eastern Time</option>
              <option value="America/Chicago">Central Time</option>
              <option value="America/Denver">Mountain Time</option>
              <option value="America/Los_Angeles">Pacific Time</option>
              <option value="Europe/London">London</option>
              <option value="Europe/Paris">Paris</option>
              <option value="Europe/Berlin">Berlin</option>
              <option value="Asia/Tokyo">Tokyo</option>
              <option value="Asia/Shanghai">Shanghai</option>
              <option value="Asia/Kolkata">Mumbai</option>
              <option value="Australia/Sydney">Sydney</option>
            </select>
          </div>
        </div>

        {/* Preview */}
        {scheduleTime && (
          <div className="p-3 bg-blue-50 rounded-md">
            <div className="text-sm text-blue-700">
              <Clock className="h-4 w-4 inline mr-1" />
              This message will be sent on{' '}
              <strong>
                {new Date(scheduleTime).toLocaleString('en-US', {
                  timeZone: timezone,
                  weekday: 'long',
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                  timeZoneName: 'short',
                })}
              </strong>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex justify-between items-center pt-4">
          <Button
            variant="outline"
            onClick={() => {
              setContent('');
              setScheduleTime('');
              setErrors({});
            }}
          >
            Clear
          </Button>
          
          <Button
            onClick={handleSchedule}
            disabled={loading || !content.trim() || !scheduleTime}
            className="min-w-32"
          >
            {loading ? (
              <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2" />
            ) : (
              <Send className="h-4 w-4 mr-2" />
            )}
            Schedule Message
          </Button>
        </div>
      </CardContent>
    </Card>
  );
};

// Scheduled Messages List Component
interface ScheduledMessagesListProps {
  conversationId?: string;
  showAllConversations?: boolean;
}

export const ScheduledMessagesList: React.FC<ScheduledMessagesListProps> = ({
  conversationId,
  showAllConversations = false,
}) => {
  const [messages, setMessages] = useState<ScheduledMessage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadScheduledMessages();
  }, [conversationId]);

  const loadScheduledMessages = async () => {
    setLoading(true);
    setError(null);
    
    try {
      const params = new URLSearchParams();
      if (conversationId && !showAllConversations) {
        params.append('conversation_id', conversationId);
      }
      
      const response = await fetch(`/api/v1/scheduled-messages?${params.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to load scheduled messages');
      }

      const data = await response.json();
      setMessages(data.scheduled_messages || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load messages');
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = async (messageId: string) => {
    if (!confirm('Are you sure you want to cancel this scheduled message?')) {
      return;
    }

    try {
      const response = await fetch(`/api/v1/scheduled-messages/${messageId}/cancel`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to cancel message');
      }

      await loadScheduledMessages();
      toast.success('Scheduled message cancelled');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to cancel message');
    }
  };

  const handleRetry = async (messageId: string) => {
    try {
      const response = await fetch(`/api/v1/scheduled-messages/${messageId}/retry`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to retry message');
      }

      await loadScheduledMessages();
      toast.success('Message retry scheduled');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to retry message');
    }
  };

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'scheduled':
        return 'bg-blue-100 text-blue-800';
      case 'sending':
        return 'bg-yellow-100 text-yellow-800';
      case 'sent':
        return 'bg-green-100 text-green-800';
      case 'failed':
        return 'bg-red-100 text-red-800';
      case 'cancelled':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusIcon = (status: string): React.ReactNode => {
    switch (status) {
      case 'scheduled':
        return <Clock className="h-4 w-4" />;
      case 'sending':
        return <Send className="h-4 w-4" />;
      case 'sent':
        return <Send className="h-4 w-4" />;
      case 'failed':
        return <AlertCircle className="h-4 w-4" />;
      case 'cancelled':
        return <Trash2 className="h-4 w-4" />;
      default:
        return <Clock className="h-4 w-4" />;
    }
  };

  if (loading) {
    return (
      <Card>
        <CardContent className="p-6 text-center">
          <div className="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-2" />
          Loading scheduled messages...
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardContent className="p-6 text-center text-red-600">
          <AlertCircle className="h-8 w-8 mx-auto mb-2" />
          {error}
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <Calendar className="h-5 w-5" />
            <span>Scheduled Messages</span>
            <Badge variant="secondary">{messages.length}</Badge>
          </div>
          <Button
            onClick={loadScheduledMessages}
            variant="outline"
            size="sm"
          >
            Refresh
          </Button>
        </CardTitle>
      </CardHeader>
      
      <CardContent>
        {messages.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            <Calendar className="h-12 w-12 mx-auto mb-4 opacity-50" />
            <p>No scheduled messages</p>
          </div>
        ) : (
          <div className="space-y-4">
            {messages.map((message) => (
              <div
                key={message.id}
                className="border rounded-lg p-4 space-y-3"
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1 min-w-0">
                    <div className="text-sm text-gray-900 truncate">
                      {message.content}
                    </div>
                    <div className="text-xs text-gray-500 mt-1">
                      Scheduled for: {new Date(message.scheduled_for).toLocaleString()}
                    </div>
                  </div>
                  
                  <div className="flex items-center space-x-2 ml-4">
                    <Badge className={getStatusColor(message.status)}>
                      {getStatusIcon(message.status)}
                      <span className="ml-1 capitalize">{message.status}</span>
                    </Badge>
                  </div>
                </div>

                {message.error_message && (
                  <div className="text-red-600 text-sm bg-red-50 p-2 rounded">
                    <AlertCircle className="h-4 w-4 inline mr-1" />
                    {message.error_message}
                  </div>
                )}

                <div className="flex items-center justify-between">
                  <div className="text-xs text-gray-500">
                    Created: {new Date(message.created_at).toLocaleString()}
                    {message.retry_count > 0 && (
                      <span className="ml-2">• Retries: {message.retry_count}</span>
                    )}
                  </div>
                  
                  <div className="flex items-center space-x-2">
                    {message.status === 'failed' && (
                      <Button
                        onClick={() => handleRetry(message.id)}
                        size="sm"
                        variant="outline"
                        className="text-blue-600"
                      >
                        <Play className="h-4 w-4 mr-1" />
                        Retry
                      </Button>
                    )}
                    
                    {message.status === 'scheduled' && (
                      <Button
                        onClick={() => handleCancel(message.id)}
                        size="sm"
                        variant="outline"
                        className="text-red-600"
                      >
                        <Trash2 className="h-4 w-4 mr-1" />
                        Cancel
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
};