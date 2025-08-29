import React, { useState, useEffect } from 'react';
import { Channel, Conversation } from '@/types/chat';
import { useChannels } from '@/hooks/useChannels';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { 
  Hash, 
  Lock, 
  Plus, 
  Search, 
  Users, 
  Settings, 
  LogOut,
  UserPlus,
  Shield,
  Globe,
  Eye,
  EyeOff
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface ChannelSidebarProps {
  selectedChannel?: Channel;
  onChannelSelect: (channel: Channel) => void;
  onConversationSelect?: (conversation: Conversation) => void;
  organizationId?: string;
  className?: string;
}

export default function ChannelSidebar({ 
  selectedChannel, 
  onChannelSelect, 
  onConversationSelect,
  organizationId,
  className 
}: ChannelSidebarProps) {
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [showPrivateChannels, setShowPrivateChannels] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [newChannel, setNewChannel] = useState({
    name: '',
    description: '',
    visibility: 'public' as 'public' | 'private',
  });

  const {
    channels,
    loading,
    error,
    searchResults,
    searchLoading,
    createChannel,
    searchChannels,
    joinChannel,
    leaveChannel,
    clearError,
  } = useChannels({ organizationId });

  const [filteredChannels, setFilteredChannels] = useState<Channel[]>([]);

  useEffect(() => {
    const filtered = channels.filter(channel => {
      const matchesSearch = !searchQuery || 
        channel.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (channel.description && channel.description.toLowerCase().includes(searchQuery.toLowerCase()));
      
      const matchesVisibility = showPrivateChannels || channel.visibility === 'public';
      
      return matchesSearch && matchesVisibility;
    });
    
    setFilteredChannels(filtered);
  }, [channels, searchQuery, showPrivateChannels]);

  const handleCreateChannel = async () => {
    if (!newChannel.name.trim()) return;

    try {
      const createdChannel = await createChannel({
        ...newChannel,
        organization_id: organizationId,
      });
      
      setShowCreateDialog(false);
      setNewChannel({ name: '', description: '', visibility: 'public' });
      onChannelSelect(createdChannel);
    } catch (error) {
      console.error('Failed to create channel:', error);
    }
  };

  const handleChannelClick = (channel: Channel) => {
    onChannelSelect(channel);
    
    // Also select the underlying conversation if callback provided
    if (onConversationSelect && channel.conversation) {
      onConversationSelect(channel.conversation);
    }
  };

  const handleJoinChannel = async (e: React.MouseEvent, channel: Channel) => {
    e.stopPropagation();
    
    try {
      await joinChannel(channel.id);
      onChannelSelect(channel);
    } catch (error) {
      console.error('Failed to join channel:', error);
    }
  };

  const handleLeaveChannel = async (e: React.MouseEvent, channel: Channel) => {
    e.stopPropagation();
    
    try {
      await leaveChannel(channel.id);
    } catch (error) {
      console.error('Failed to leave channel:', error);
    }
  };

  const isUserMember = (channel: Channel) => {
    return channel.conversation?.participants?.some(p => p.left_at === null);
  };

  if (error) {
    return (
      <div className={cn("flex flex-col h-full", className)}>
        <div className="p-4 text-center">
          <p className="text-sm text-destructive mb-2">Failed to load channels</p>
          <Button size="sm" variant="outline" onClick={clearError}>
            Retry
          </Button>
        </div>
      </div>
    );
  }

  return (
    <TooltipProvider>
      <div className={cn("flex flex-col h-full bg-muted/30", className)}>
        {/* Header */}
        <div className="p-4 border-b">
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-semibold text-lg">Channels</h2>
            
            <div className="flex gap-1">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setShowPrivateChannels(!showPrivateChannels)}
                    className={cn(showPrivateChannels && "bg-accent")}
                  >
                    {showPrivateChannels ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  {showPrivateChannels ? 'Hide private channels' : 'Show private channels'}
                </TooltipContent>
              </Tooltip>

              <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <DialogTrigger asChild>
                      <Button variant="ghost" size="icon">
                        <Plus className="h-4 w-4" />
                      </Button>
                    </DialogTrigger>
                  </TooltipTrigger>
                  <TooltipContent>Create channel</TooltipContent>
                </Tooltip>

                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Create New Channel</DialogTitle>
                  </DialogHeader>
                  <div className="space-y-4">
                    <div>
                      <Label htmlFor="channel-name">Channel Name</Label>
                      <Input
                        id="channel-name"
                        value={newChannel.name}
                        onChange={(e) => setNewChannel(prev => ({ ...prev, name: e.target.value }))}
                        placeholder="Enter channel name"
                      />
                    </div>
                    <div>
                      <Label htmlFor="channel-description">Description (Optional)</Label>
                      <Textarea
                        id="channel-description"
                        value={newChannel.description}
                        onChange={(e) => setNewChannel(prev => ({ ...prev, description: e.target.value }))}
                        placeholder="Describe what this channel is for"
                        rows={3}
                      />
                    </div>
                    <div>
                      <Label htmlFor="channel-visibility">Visibility</Label>
                      <Select
                        value={newChannel.visibility}
                        onValueChange={(value: 'public' | 'private') => 
                          setNewChannel(prev => ({ ...prev, visibility: value }))
                        }
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="public">
                            <div className="flex items-center gap-2">
                              <Globe className="h-4 w-4" />
                              Public - Anyone can join
                            </div>
                          </SelectItem>
                          <SelectItem value="private">
                            <div className="flex items-center gap-2">
                              <Lock className="h-4 w-4" />
                              Private - Invite only
                            </div>
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="flex justify-end gap-2">
                      <Button
                        variant="outline"
                        onClick={() => setShowCreateDialog(false)}
                      >
                        Cancel
                      </Button>
                      <Button
                        onClick={handleCreateChannel}
                        disabled={!newChannel.name.trim()}
                      >
                        Create Channel
                      </Button>
                    </div>
                  </div>
                </DialogContent>
              </Dialog>
            </div>
          </div>

          {/* Search */}
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search channels..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>
        </div>

        {/* Channel List */}
        <ScrollArea className="flex-1">
          <div className="p-2">
            {loading ? (
              <div className="text-center py-8">
                <div className="text-sm text-muted-foreground">Loading channels...</div>
              </div>
            ) : filteredChannels.length === 0 ? (
              <div className="text-center py-8">
                <Hash className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                <p className="text-sm text-muted-foreground mb-2">
                  {searchQuery ? 'No channels found' : 'No channels available'}
                </p>
                {!searchQuery && (
                  <Button size="sm" onClick={() => setShowCreateDialog(true)}>
                    Create your first channel
                  </Button>
                )}
              </div>
            ) : (
              <div className="space-y-1">
                {filteredChannels.map((channel) => {
                  const isSelected = selectedChannel?.id === channel.id;
                  const isMember = isUserMember(channel);
                  
                  return (
                    <div
                      key={channel.id}
                      className={cn(
                        "flex items-center gap-2 p-2 rounded-md cursor-pointer hover:bg-accent/50 transition-colors group",
                        isSelected && "bg-accent"
                      )}
                      onClick={() => handleChannelClick(channel)}
                    >
                      <div className="flex-shrink-0">
                        {channel.visibility === 'private' ? (
                          <Lock className="h-4 w-4 text-muted-foreground" />
                        ) : (
                          <Hash className="h-4 w-4 text-muted-foreground" />
                        )}
                      </div>

                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="font-medium text-sm truncate">
                            {channel.name}
                          </span>
                          {channel.visibility === 'private' && (
                            <Badge variant="secondary" className="h-4 text-xs">
                              Private
                            </Badge>
                          )}
                        </div>
                        {channel.description && (
                          <p className="text-xs text-muted-foreground truncate">
                            {channel.description}
                          </p>
                        )}
                      </div>

                      {/* Action buttons */}
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        {!isMember && channel.visibility === 'public' && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-6 w-6"
                                onClick={(e) => handleJoinChannel(e, channel)}
                              >
                                <UserPlus className="h-3 w-3" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>Join channel</TooltipContent>
                          </Tooltip>
                        )}
                        
                        {isMember && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-6 w-6"
                                onClick={(e) => handleLeaveChannel(e, channel)}
                              >
                                <LogOut className="h-3 w-3" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>Leave channel</TooltipContent>
                          </Tooltip>
                        )}

                        {channel.conversation && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <div className="h-6 w-6 flex items-center justify-center">
                                <Shield className="h-3 w-3 text-green-500" />
                              </div>
                            </TooltipTrigger>
                            <TooltipContent>End-to-end encrypted</TooltipContent>
                          </Tooltip>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </ScrollArea>

        {/* Footer */}
        {selectedChannel && (
          <>
            <Separator />
            <div className="p-4">
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Users className="h-4 w-4" />
                <span>
                  {selectedChannel.conversation?.participants?.filter(p => !p.left_at).length || 0} members
                </span>
                {selectedChannel.conversation && (
                  <>
                    <Separator orientation="vertical" className="h-4" />
                    <Shield className="h-4 w-4 text-green-500" />
                    <span>E2EE</span>
                  </>
                )}
              </div>
            </div>
          </>
        )}
      </div>
    </TooltipProvider>
  );
}