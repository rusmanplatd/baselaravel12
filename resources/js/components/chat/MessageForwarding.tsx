import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { ArrowRightIcon, ShieldCheckIcon, UserGroupIcon, MagnifyingGlassIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import { Conversation, Message, User } from '@/types/chat';

interface ForwardingDestination {
  id: string;
  name: string;
  type: 'conversation' | 'group';
  participantCount?: number;
  lastMessageAt?: string;
  isE2EEEnabled: boolean;
  canForward: boolean;
  warning?: string;
}

interface MessageForwardingProps {
  isOpen: boolean;
  onClose: () => void;
  messages: Message[];
  conversations: Conversation[];
  currentUser: User;
  onForwardMessages: (
    messageIds: string[],
    destinationIds: string[],
    options: {
      includeQuote: boolean;
      preserveThreads: boolean;
      addComment?: string;
    }
  ) => Promise<void>;
}

export default function MessageForwarding({
  isOpen,
  onClose,
  messages,
  conversations,
  currentUser,
  onForwardMessages
}: MessageForwardingProps) {
  const [selectedDestinations, setSelectedDestinations] = useState<string[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [includeQuote, setIncludeQuote] = useState(true);
  const [preserveThreads, setPreserveThreads] = useState(false);
  const [addComment, setAddComment] = useState('');
  const [isForwarding, setIsForwarding] = useState(false);

  // Reset state when dialog opens/closes
  useEffect(() => {
    if (isOpen) {
      setSelectedDestinations([]);
      setSearchQuery('');
      setIncludeQuote(true);
      setPreserveThreads(false);
      setAddComment('');
    }
  }, [isOpen]);

  // Convert conversations to forwarding destinations
  const forwardingDestinations: ForwardingDestination[] = conversations
    .filter(conv => conv.id !== messages[0]?.conversation_id) // Don't include current conversation
    .map(conv => {
      const participantCount = conv.participants?.length || 0;
      const isGroup = participantCount > 2;
      const canForward = participantCount > 0; // Basic check - could be more sophisticated
      
      return {
        id: conv.id,
        name: conv.name || (isGroup ? `Group (${participantCount} members)` : 'Direct Message'),
        type: isGroup ? 'group' : 'conversation',
        participantCount,
        lastMessageAt: conv.updated_at,
        isE2EEEnabled: true, // Assuming all conversations have E2EE
        canForward,
        warning: !canForward ? 'Cannot forward to this conversation' : undefined
      };
    })
    .filter(dest => 
      dest.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      dest.id.includes(searchQuery)
    )
    .sort((a, b) => {
      // Sort by last message time, most recent first
      if (a.lastMessageAt && b.lastMessageAt) {
        return new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime();
      }
      return a.name.localeCompare(b.name);
    });

  const handleDestinationToggle = (destinationId: string) => {
    setSelectedDestinations(prev => 
      prev.includes(destinationId)
        ? prev.filter(id => id !== destinationId)
        : [...prev, destinationId]
    );
  };

  const handleForward = async () => {
    if (selectedDestinations.length === 0 || messages.length === 0) {
      return;
    }

    setIsForwarding(true);
    try {
      await onForwardMessages(
        messages.map(m => m.id),
        selectedDestinations,
        {
          includeQuote,
          preserveThreads,
          addComment: addComment.trim() || undefined,
        }
      );
      onClose();
    } catch (error) {
      console.error('Failed to forward messages:', error);
    } finally {
      setIsForwarding(false);
    }
  };

  const selectedCount = selectedDestinations.length;
  const messageCount = messages.length;
  const hasThreadMessages = messages.some(m => m.reply_to_id);

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-2xl max-h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ArrowRightIcon className="h-5 w-5" />
            Forward {messageCount} Message{messageCount !== 1 ? 's' : ''}
          </DialogTitle>
          <DialogDescription>
            Forward messages to other conversations. All forwarded messages are end-to-end encrypted.
          </DialogDescription>
        </DialogHeader>

        <div className="flex-1 space-y-4 overflow-hidden">
          {/* Message Preview */}
          <div className="bg-muted/50 rounded-lg p-3 space-y-2">
            <div className="text-sm font-medium">Messages to forward:</div>
            <div className="space-y-1 max-h-24 overflow-y-auto">
              {messages.slice(0, 3).map((message, index) => (
                <div key={message.id} className="text-xs text-muted-foreground truncate">
                  {index + 1}. {message.sender?.name}: {message.content || '[Encrypted content]'}
                </div>
              ))}
              {messages.length > 3 && (
                <div className="text-xs text-muted-foreground">
                  ... and {messages.length - 3} more messages
                </div>
              )}
            </div>
          </div>

          {/* Search */}
          <div className="relative">
            <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search conversations..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>

          {/* Destination List */}
          <div className="flex-1 min-h-0">
            <ScrollArea className="h-full">
              <div className="space-y-1">
                {forwardingDestinations.length === 0 ? (
                  <div className="text-center text-muted-foreground py-8">
                    {searchQuery ? 'No conversations found' : 'No available destinations'}
                  </div>
                ) : (
                  forwardingDestinations.map((destination) => {
                    const isSelected = selectedDestinations.includes(destination.id);
                    const isDisabled = !destination.canForward;

                    return (
                      <div
                        key={destination.id}
                        className={`
                          flex items-center space-x-3 p-3 rounded-lg border cursor-pointer transition-colors
                          ${isSelected ? 'border-blue-500 bg-blue-50' : 'border-border hover:bg-muted/50'}
                          ${isDisabled ? 'opacity-50 cursor-not-allowed' : ''}
                        `}
                        onClick={() => !isDisabled && handleDestinationToggle(destination.id)}
                      >
                        <Checkbox
                          checked={isSelected}
                          onChange={() => !isDisabled && handleDestinationToggle(destination.id)}
                          disabled={isDisabled}
                        />
                        
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2">
                            <div className="font-medium truncate">{destination.name}</div>
                            <div className="flex items-center gap-1">
                              {destination.isE2EEEnabled && (
                                <ShieldCheckIcon className="h-3 w-3 text-green-600" />
                              )}
                              {destination.type === 'group' && (
                                <UserGroupIcon className="h-3 w-3 text-muted-foreground" />
                              )}
                            </div>
                          </div>
                          
                          <div className="flex items-center gap-2 mt-1">
                            <Badge variant="outline" className="text-xs">
                              {destination.type === 'group' 
                                ? `${destination.participantCount} members`
                                : 'Direct'
                              }
                            </Badge>
                            {destination.warning && (
                              <div className="flex items-center gap-1 text-xs text-red-600">
                                <ExclamationTriangleIcon className="h-3 w-3" />
                                {destination.warning}
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </ScrollArea>
          </div>

          <Separator />

          {/* Forwarding Options */}
          <div className="space-y-3">
            <div className="text-sm font-medium">Forwarding Options</div>
            
            <div className="space-y-2">
              <div className="flex items-center space-x-2">
                <Checkbox
                  checked={includeQuote}
                  onChange={setIncludeQuote}
                  id="include-quote"
                />
                <Label htmlFor="include-quote" className="text-sm">
                  Include original message as quote
                </Label>
              </div>

              {hasThreadMessages && (
                <div className="flex items-center space-x-2">
                  <Checkbox
                    checked={preserveThreads}
                    onChange={setPreserveThreads}
                    id="preserve-threads"
                  />
                  <Label htmlFor="preserve-threads" className="text-sm">
                    Preserve thread structure
                  </Label>
                </div>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="add-comment" className="text-sm">
                Add comment (optional)
              </Label>
              <Input
                id="add-comment"
                placeholder="Add a comment with the forwarded messages..."
                value={addComment}
                onChange={(e) => setAddComment(e.target.value)}
              />
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-between pt-4 border-t">
            <div className="text-sm text-muted-foreground">
              {selectedCount > 0 && (
                <>
                  Forwarding to {selectedCount} conversation{selectedCount !== 1 ? 's' : ''}
                </>
              )}
            </div>
            
            <div className="flex space-x-2">
              <Button variant="outline" onClick={onClose}>
                Cancel
              </Button>
              <Button 
                onClick={handleForward}
                disabled={selectedCount === 0 || isForwarding}
                className="bg-blue-600 hover:bg-blue-700"
              >
                {isForwarding ? (
                  <>Forwarding...</>
                ) : (
                  <>
                    <ArrowRightIcon className="h-4 w-4 mr-2" />
                    Forward Messages
                  </>
                )}
              </Button>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}