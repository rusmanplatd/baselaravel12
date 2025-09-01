import { useState, useEffect } from 'react';
import { Channel, ChannelSearchResult } from '@/types/chat';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Hash, Lock, Plus, Search, Users } from 'lucide-react';
import { apiService } from '@/services/ApiService';

interface ChannelListProps {
  organizationId?: string;
  onChannelSelect?: (channel: Channel) => void;
}

export default function ChannelList({ organizationId, onChannelSelect }: ChannelListProps) {
  const [channels, setChannels] = useState<Channel[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [newChannel, setNewChannel] = useState({
    name: '',
    description: '',
    visibility: 'public' as 'public' | 'private',
  });

  useEffect(() => {
    fetchChannels();
  }, [organizationId]);

  const fetchChannels = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      if (organizationId) {
        params.append('organization_id', organizationId);
      }
      params.append('user_channels', 'true');

      const response = await apiService.get<{data: Channel[]}>(`/api/v1/chat/channels?${params}`);
      setChannels(response.data || []);
    } catch (error) {
      console.error('Failed to fetch channels:', error);
    } finally {
      setLoading(false);
    }
  };

  const searchChannels = async () => {
    if (!searchQuery.trim()) {
      fetchChannels();
      return;
    }

    try {
      setLoading(true);
      const params = new URLSearchParams({
        query: searchQuery,
      });
      if (organizationId) {
        params.append('organization_id', organizationId);
      }

      const response = await apiService.get<Channel[]>(`/api/v1/chat/channels/search?${params}`);
      setChannels(response || []);
    } catch (error) {
      console.error('Failed to search channels:', error);
    } finally {
      setLoading(false);
    }
  };

  const createChannel = async () => {
    if (!newChannel.name.trim()) return;

    try {
      setCreateLoading(true);
      const createdChannel = await apiService.post<Channel>('/api/v1/chat/channels', {
        ...newChannel,
        organization_id: organizationId,
      });
      setChannels(prev => [createdChannel, ...prev]);
      setShowCreateDialog(false);
      setNewChannel({ name: '', description: '', visibility: 'public' });
      
      if (onChannelSelect) {
        onChannelSelect(createdChannel);
      }
    } catch (error) {
      console.error('Failed to create channel:', error);
    } finally {
      setCreateLoading(false);
    }
  };

  const joinChannel = async (channel: Channel) => {
    if (channel.visibility === 'private') return;

    try {
      await apiService.post(`/api/v1/chat/channels/${channel.id}/join`);
      fetchChannels();
      if (onChannelSelect) {
        onChannelSelect(channel);
      }
    } catch (error) {
      console.error('Failed to join channel:', error);
    }
  };


  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="text-muted-foreground">Loading channels...</div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search channels..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && searchChannels()}
            className="pl-10"
          />
        </div>
        <Button onClick={searchChannels} variant="outline">
          Search
        </Button>
        
        <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
          <DialogTrigger asChild>
            <Button>
              <Plus className="h-4 w-4 mr-2" />
              Create Channel
            </Button>
          </DialogTrigger>
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
                  onValueChange={(value: 'public' | 'private') => setNewChannel(prev => ({ ...prev, visibility: value }))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="public">Public - Anyone can join</SelectItem>
                    <SelectItem value="private">Private - Invite only</SelectItem>
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
                  onClick={createChannel}
                  disabled={createLoading || !newChannel.name.trim()}
                >
                  {createLoading ? 'Creating...' : 'Create Channel'}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <div className="space-y-3">
        {channels.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-8">
              <Hash className="h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-medium mb-2">No channels found</h3>
              <p className="text-muted-foreground text-center mb-4">
                {searchQuery 
                  ? "No channels match your search criteria"
                  : "Create your first channel to get started"
                }
              </p>
              {!searchQuery && (
                <Button onClick={() => setShowCreateDialog(true)}>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Channel
                </Button>
              )}
            </CardContent>
          </Card>
        ) : (
          channels.map((channel) => (
            <Card
              key={channel.id}
              className="hover:bg-accent/50 cursor-pointer transition-colors"
              onClick={() => onChannelSelect?.(channel)}
            >
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {channel.visibility === 'private' ? (
                      <Lock className="h-4 w-4 text-muted-foreground" />
                    ) : (
                      <Hash className="h-4 w-4 text-muted-foreground" />
                    )}
                    <CardTitle className="text-base">{channel.name}</CardTitle>
                    <Badge variant={channel.visibility === 'private' ? 'secondary' : 'default'}>
                      {channel.visibility}
                    </Badge>
                  </div>
                  {channel.visibility === 'public' && (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={(e) => {
                        e.stopPropagation();
                        joinChannel(channel);
                      }}
                    >
                      Join
                    </Button>
                  )}
                </div>
              </CardHeader>
              {channel.description && (
                <CardContent className="pt-0">
                  <CardDescription>{channel.description}</CardDescription>
                </CardContent>
              )}
            </Card>
          ))
        )}
      </div>
    </div>
  );
}