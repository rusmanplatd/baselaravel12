import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { 
  MessageSquare, 
  Users, 
  Clock, 
  Reply,
  Send,
  ChevronRight
} from 'lucide-react';
import { useThreading } from '@/hooks/useThreading';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';

interface ThreadPreviewProps {
  conversationId: string;
  parentMessageId: string;
  className?: string;
  onOpenThread?: () => void;
}

export function ThreadPreview({ 
  conversationId, 
  parentMessageId, 
  className,
  onOpenThread 
}: ThreadPreviewProps) {
  const { getThreadSummary, createThread, sendThreadMessage } = useThreading({ conversationId });
  const [threadSummary, setThreadSummary] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [quickReply, setQuickReply] = useState('');
  const [showQuickReply, setShowQuickReply] = useState(false);

  useEffect(() => {
    loadThreadSummary();
  }, [parentMessageId]);

  const loadThreadSummary = async () => {
    try {
      setIsLoading(true);
      const summary = await getThreadSummary(parentMessageId);
      setThreadSummary(summary);
    } catch (error) {
      console.error('Failed to load thread summary:', error);
      setThreadSummary(null);
    } finally {
      setIsLoading(false);
    }
  };

  const handleQuickReply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!quickReply.trim() || !threadSummary) return;

    try {
      // If no existing thread, create one first
      if (threadSummary.thread_stats.total_replies === 0) {
        await createThread(parentMessageId, {
          initialMessage: quickReply.trim(),
        });
      } else {
        // Find the thread ID (you might need to modify the summary to include this)
        // For now, we'll assume we have the thread ID available
        // await sendThreadMessage(threadId, quickReply.trim());
      }
      
      setQuickReply('');
      setShowQuickReply(false);
      await loadThreadSummary();
    } catch (error) {
      console.error('Failed to send quick reply:', error);
    }
  };

  const handleStartThread = async () => {
    try {
      const thread = await createThread(parentMessageId);
      onOpenThread?.();
    } catch (error) {
      console.error('Failed to start thread:', error);
    }
  };

  if (isLoading) {
    return (
      <div className={cn("inline-block", className)}>
        <Button variant="ghost" size="sm" disabled>
          <div className="animate-spin rounded-full h-3 w-3 border-b border-current mr-2"></div>
          Loading...
        </Button>
      </div>
    );
  }

  // No thread exists yet
  if (!threadSummary || threadSummary.thread_stats.total_replies === 0) {
    return (
      <div className={cn("inline-block", className)}>
        <Button 
          variant="ghost" 
          size="sm" 
          onClick={handleStartThread}
          className="text-xs text-muted-foreground hover:text-foreground"
        >
          <Reply className="h-3 w-3 mr-1" />
          Start thread
        </Button>
      </div>
    );
  }

  return (
    <div className={cn("inline-block", className)}>
      <Popover>
        <PopoverTrigger asChild>
          <Button 
            variant="ghost" 
            size="sm"
            className="text-xs text-primary hover:text-primary/80 hover:bg-primary/10"
          >
            <MessageSquare className="h-3 w-3 mr-1" />
            {threadSummary.thread_stats.total_replies} repl{threadSummary.thread_stats.total_replies === 1 ? 'y' : 'ies'}
            {threadSummary.thread_stats.unread_count > 0 && (
              <Badge variant="destructive" className="ml-2 h-4 px-1 text-xs">
                {threadSummary.thread_stats.unread_count}
              </Badge>
            )}
            <ChevronRight className="h-3 w-3 ml-1" />
          </Button>
        </PopoverTrigger>
        
        <PopoverContent className="w-80" align="start">
          <div className="space-y-3">
            {/* Thread Stats */}
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                <Badge variant="secondary">
                  <MessageSquare className="h-3 w-3 mr-1" />
                  {threadSummary.thread_stats.total_replies}
                </Badge>
                <Badge variant="secondary">
                  <Users className="h-3 w-3 mr-1" />
                  {threadSummary.thread_stats.unique_participants}
                </Badge>
              </div>
              
              <span className="text-xs text-muted-foreground flex items-center">
                <Clock className="h-3 w-3 mr-1" />
                {formatDistanceToNow(new Date(threadSummary.thread_stats.last_reply_at), { addSuffix: true })}
              </span>
            </div>

            {/* Recent Participants */}
            <div>
              <p className="text-xs font-medium mb-2">Participants</p>
              <div className="flex items-center space-x-2">
                {threadSummary.recent_participants.slice(0, 4).map((participant: any) => (
                  <Avatar key={participant.id} className="h-6 w-6">
                    <AvatarImage src={participant.avatar} />
                    <AvatarFallback className="text-xs">
                      {participant.name.charAt(0)}
                    </AvatarFallback>
                  </Avatar>
                ))}
                {threadSummary.recent_participants.length > 4 && (
                  <div className="text-xs text-muted-foreground">
                    +{threadSummary.recent_participants.length - 4} more
                  </div>
                )}
              </div>
            </div>

            {/* Latest Replies Preview */}
            <div>
              <p className="text-xs font-medium mb-2">Latest replies</p>
              <div className="space-y-2 max-h-32 overflow-y-auto">
                {threadSummary.latest_replies_preview.map((reply: any) => (
                  <div key={reply.id} className="flex items-start space-x-2">
                    <Avatar className="h-5 w-5 mt-0.5">
                      <AvatarImage src={reply.sender.avatar} />
                      <AvatarFallback className="text-xs">
                        {reply.sender.name.charAt(0)}
                      </AvatarFallback>
                    </Avatar>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center space-x-1">
                        <span className="text-xs font-medium">
                          {reply.sender.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {formatDistanceToNow(new Date(reply.created_at), { addSuffix: true })}
                        </span>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {reply.type === 'text' ? 'sent a message' : `sent ${reply.type}`}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Quick Reply */}
            {!showQuickReply ? (
              <Button 
                variant="outline" 
                size="sm" 
                className="w-full"
                onClick={() => setShowQuickReply(true)}
              >
                <Reply className="h-3 w-3 mr-2" />
                Quick reply
              </Button>
            ) : (
              <form onSubmit={handleQuickReply} className="space-y-2">
                <Input
                  value={quickReply}
                  onChange={(e) => setQuickReply(e.target.value)}
                  placeholder="Type a quick reply..."
                  className="text-sm"
                  autoFocus
                />
                <div className="flex justify-end space-x-2">
                  <Button 
                    type="button"
                    variant="ghost" 
                    size="sm"
                    onClick={() => {
                      setShowQuickReply(false);
                      setQuickReply('');
                    }}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" size="sm" disabled={!quickReply.trim()}>
                    <Send className="h-3 w-3 mr-1" />
                    Send
                  </Button>
                </div>
              </form>
            )}

            {/* View Full Thread */}
            <Button 
              variant="default" 
              size="sm" 
              className="w-full"
              onClick={() => {
                onOpenThread?.();
              }}
            >
              View full thread
              <ChevronRight className="h-3 w-3 ml-2" />
            </Button>
          </div>
        </PopoverContent>
      </Popover>
    </div>
  );
}

export default ThreadPreview;