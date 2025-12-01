import React, { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
  MessageSquare,
  Users,
  Clock,
  Plus,
  MoreVertical,
  Bell,
  BellOff,
  UserPlus,
  UserMinus,
  Edit,
  Trash2,
  Settings,
  Send,
  Reply
} from 'lucide-react';
import { useThreading, Thread, ThreadMessage } from '@/hooks/useThreading';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';

interface ThreadViewProps {
  conversationId: string;
  parentMessageId?: string;
  className?: string;
}

export function ThreadView({ conversationId, parentMessageId, className }: ThreadViewProps) {
  const {
    threads,
    currentThread,
    threadMessages,
    isLoading,
    isLoadingMessages,
    error,
    loadThreads,
    createThread,
    loadThread,
    updateThread,
    deleteThread,
    joinThread,
    leaveThread,
    updateNotificationSettings,
    sendThreadMessage,
    markThreadAsRead,
  } = useThreading({ conversationId });

  const [selectedThreadId, setSelectedThreadId] = useState<string | null>(null);
  const [newThreadTitle, setNewThreadTitle] = useState('');
  const [newMessage, setNewMessage] = useState('');
  const [showCreateThread, setShowCreateThread] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    loadThreads();
  }, [conversationId]);

  useEffect(() => {
    if (parentMessageId && threads.length > 0) {
      const thread = threads.find(t => t.parent_message_id === parentMessageId);
      if (thread) {
        setSelectedThreadId(thread.id);
        loadThread(thread.id);
      }
    }
  }, [parentMessageId, threads]);

  useEffect(() => {
    if (selectedThreadId && selectedThreadId !== currentThread?.id) {
      loadThread(selectedThreadId);
    }
  }, [selectedThreadId, currentThread]);

  useEffect(() => {
    scrollToBottom();
  }, [threadMessages]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const handleCreateThread = async () => {
    if (!parentMessageId) return;

    try {
      const thread = await createThread(parentMessageId, {
        title: newThreadTitle || undefined,
      });

      setSelectedThreadId(thread.id);
      setNewThreadTitle('');
      setShowCreateThread(false);
    } catch (error) {
      console.error('Failed to create thread:', error);
    }
  };

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMessage.trim() || !currentThread) return;

    try {
      await sendThreadMessage(currentThread.id, newMessage.trim());
      setNewMessage('');
    } catch (error) {
      console.error('Failed to send message:', error);
    }
  };

  const handleMarkAsRead = async () => {
    if (!currentThread) return;

    try {
      await markThreadAsRead(currentThread.id);
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  };

  if (isLoading) {
    return (
      <div className={cn("flex items-center justify-center h-48", className)}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-2"></div>
          <p className="text-sm text-muted-foreground">Loading threads...</p>
        </div>
      </div>
    );
  }

  return (
    <div className={cn("flex h-full", className)}>
      {/* Thread List Sidebar */}
      <div className="w-1/3 border-r bg-background">
        <div className="p-4 border-b">
          <div className="flex items-center justify-between mb-2">
            <h3 className="font-semibold">Threads</h3>
            {parentMessageId && (
              <Dialog open={showCreateThread} onOpenChange={setShowCreateThread}>
                <DialogTrigger asChild>
                  <Button size="sm" variant="outline">
                    <Plus className="h-4 w-4 mr-1" />
                    New
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Create Thread</DialogTitle>
                  </DialogHeader>
                  <div className="space-y-4">
                    <div>
                      <label className="text-sm font-medium">Thread Title (Optional)</label>
                      <Input
                        value={newThreadTitle}
                        onChange={(e) => setNewThreadTitle(e.target.value)}
                        placeholder="Enter thread title..."
                      />
                    </div>
                    <div className="flex justify-end space-x-2">
                      <Button variant="outline" onClick={() => setShowCreateThread(false)}>
                        Cancel
                      </Button>
                      <Button onClick={handleCreateThread}>
                        Create Thread
                      </Button>
                    </div>
                  </div>
                </DialogContent>
              </Dialog>
            )}
          </div>
          <p className="text-xs text-muted-foreground">
            {threads.length} thread{threads.length !== 1 ? 's' : ''}
          </p>
        </div>

        <ScrollArea className="h-[calc(100%-80px)]">
          <div className="p-2">
            {threads.length === 0 ? (
              <div className="text-center py-8">
                <MessageSquare className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                <p className="text-sm text-muted-foreground">No threads yet</p>
                <p className="text-xs text-muted-foreground mt-1">
                  Reply to a message to start a thread
                </p>
              </div>
            ) : (
              threads.map((thread) => (
                <ThreadCard
                  key={thread.id}
                  thread={thread}
                  isSelected={selectedThreadId === thread.id}
                  onClick={() => setSelectedThreadId(thread.id)}
                  onUpdate={updateThread}
                  onDelete={deleteThread}
                />
              ))
            )}
          </div>
        </ScrollArea>
      </div>

      {/* Thread Messages */}
      <div className="flex-1 flex flex-col">
        {currentThread ? (
          <>
            {/* Thread Header */}
            <div className="p-4 border-b bg-background">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <Avatar className="h-8 w-8">
                    <AvatarImage src={currentThread.creator.avatar} />
                    <AvatarFallback>
                      {currentThread.creator.name.charAt(0)}
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <h4 className="font-semibold">
                      {currentThread.decrypted_title || currentThread.title || 'Thread'}
                    </h4>
                    <div className="flex items-center space-x-2 text-xs text-muted-foreground">
                      <span>Started by {currentThread.creator.name}</span>
                      <span>•</span>
                      <span>{formatDistanceToNow(new Date(currentThread.last_message_at), { addSuffix: true })}</span>
                    </div>
                  </div>
                </div>

                <div className="flex items-center space-x-2">
                  <Badge variant="secondary">
                    <Users className="h-3 w-3 mr-1" />
                    {currentThread.participant_count}
                  </Badge>
                  <Badge variant="secondary">
                    <MessageSquare className="h-3 w-3 mr-1" />
                    {currentThread.message_count}
                  </Badge>

                  <ThreadSettings
                    thread={currentThread}
                    onJoin={() => joinThread(currentThread.id)}
                    onLeave={() => leaveThread(currentThread.id)}
                    onUpdateNotifications={(settings) =>
                      updateNotificationSettings(currentThread.id, settings)
                    }
                    onMarkAsRead={handleMarkAsRead}
                  />
                </div>
              </div>

              {/* Parent Message Preview */}
              {currentThread.parent_message && (
                <div className="mt-3 p-3 bg-muted rounded-lg">
                  <div className="flex items-center space-x-2 mb-1">
                    <Reply className="h-3 w-3 text-muted-foreground" />
                    <span className="text-xs font-medium">
                      {currentThread.parent_message.sender.name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                      {formatDistanceToNow(new Date(currentThread.parent_message.created_at), { addSuffix: true })}
                    </span>
                  </div>
                  <p className="text-sm text-muted-foreground line-clamp-2">
                    {currentThread.parent_message.decrypted_content}
                  </p>
                </div>
              )}
            </div>

            {/* Messages */}
            <ScrollArea className="flex-1 p-4">
              <div className="space-y-4">
                {isLoadingMessages ? (
                  <div className="flex justify-center">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
                  </div>
                ) : (
                  threadMessages.map((message) => (
                    <ThreadMessageItem
                      key={message.id}
                      message={message}
                    />
                  ))
                )}
                <div ref={messagesEndRef} />
              </div>
            </ScrollArea>

            {/* Message Input */}
            <div className="p-4 border-t">
              <form onSubmit={handleSendMessage} className="flex space-x-2">
                <Input
                  value={newMessage}
                  onChange={(e) => setNewMessage(e.target.value)}
                  placeholder="Type a message...306"
                  className="flex-1"
                />
                <Button type="submit" size="sm" disabled={!newMessage.trim()}>
                  <Send className="h-4 w-4" />
                </Button>
              </form>
            </div>
          </>
        ) : (
          <div className="flex-1 flex items-center justify-center">
            <div className="text-center">
              <MessageSquare className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <h3 className="font-semibold mb-2">Select a Thread</h3>
              <p className="text-sm text-muted-foreground">
                Choose a thread from the sidebar to view messages
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

interface ThreadCardProps {
  thread: Thread;
  isSelected: boolean;
  onClick: () => void;
  onUpdate: (threadId: string, updates: Partial<Thread>) => Promise<void>;
  onDelete: (threadId: string) => Promise<void>;
}

function ThreadCard({ thread, isSelected, onClick, onUpdate, onDelete }: ThreadCardProps) {
  return (
    <Card
      className={cn(
        "cursor-pointer transition-colors mb-2",
        isSelected ? "border-primary bg-primary/5" : "hover:bg-muted/50"
      )}
      onClick={onClick}
    >
      <CardContent className="p-3">
        <div className="flex items-center justify-between mb-2">
          <h4 className="font-medium text-sm line-clamp-1">
            {thread.decrypted_title || thread.title || `Thread #${thread.id.slice(-6)}`}
          </h4>
          <DropdownMenu>
            <DropdownMenuTrigger asChild onClick={(e) => e.stopPropagation()}>
              <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                <MoreVertical className="h-3 w-3" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onUpdate(thread.id, { title: 'New Title' })}>
                <Edit className="h-4 w-4 mr-2" />
                Edit
              </DropdownMenuItem>
              <DropdownMenuItem
                className="text-destructive"
                onClick={() => onDelete(thread.id)}
              >
                <Trash2 className="h-4 w-4 mr-2" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <div className="flex items-center justify-between text-xs text-muted-foreground">
          <div className="flex items-center space-x-2">
            <span className="flex items-center">
              <Users className="h-3 w-3 mr-1" />
              {thread.participant_count}
            </span>
            <span className="flex items-center">
              <MessageSquare className="h-3 w-3 mr-1" />
              {thread.message_count}
            </span>
          </div>

          <span className="flex items-center">
            <Clock className="h-3 w-3 mr-1" />
            {formatDistanceToNow(new Date(thread.last_message_at), { addSuffix: true })}
          </span>
        </div>
      </CardContent>
    </Card>
  );
}

interface ThreadMessageItemProps {
  message: ThreadMessage;
}

function ThreadMessageItem({ message }: ThreadMessageItemProps) {
  return (
    <div className="flex items-start space-x-3">
      <Avatar className="h-8 w-8 mt-0.5">
        <AvatarImage src={message.sender.avatar} />
        <AvatarFallback>
          {message.sender.name.charAt(0)}
        </AvatarFallback>
      </Avatar>

      <div className="flex-1 min-w-0">
        <div className="flex items-center space-x-2 mb-1">
          <span className="font-medium text-sm">{message.sender.name}</span>
          <span className="text-xs text-muted-foreground">
            {formatDistanceToNow(new Date(message.created_at), { addSuffix: true })}
          </span>
        </div>

        <div className="text-sm">
          {message.decrypted_content || '[Encrypted message]'}
        </div>

        {message.reactions && message.reactions.length > 0 && (
          <div className="flex items-center space-x-1 mt-2">
            {message.reactions.map((reaction, index) => (
              <TooltipProvider key={index}>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Badge variant="secondary" className="text-xs cursor-pointer">
                      {reaction.emoji}
                    </Badge>
                  </TooltipTrigger>
                  <TooltipContent>
                    <p>{reaction.user.name}</p>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

interface ThreadSettingsProps {
  thread: Thread;
  onJoin: () => void;
  onLeave: () => void;
  onUpdateNotifications: (settings: any) => void;
  onMarkAsRead: () => void;
}

function ThreadSettings({
  thread,
  onJoin,
  onLeave,
  onUpdateNotifications,
  onMarkAsRead
}: ThreadSettingsProps) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="sm">
          <Settings className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={onMarkAsRead}>
          <MessageSquare className="h-4 w-4 mr-2" />
          Mark as Read
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onJoin}>
          <UserPlus className="h-4 w-4 mr-2" />
          Join Thread
        </DropdownMenuItem>
        <DropdownMenuItem onClick={onLeave}>
          <UserMinus className="h-4 w-4 mr-2" />
          Leave Thread
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onUpdateNotifications({ mentions: true })}>
          <Bell className="h-4 w-4 mr-2" />
          Enable Notifications
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onUpdateNotifications({ mentions: false })}>
          <BellOff className="h-4 w-4 mr-2" />
          Disable Notifications
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export default ThreadView;
