import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { toast } from 'sonner';
import { User } from '@/types';
import { Conversation, Participant } from '@/types/chat';
import { 
  CogIcon, 
  UserGroupIcon, 
  LinkIcon,
  TrashIcon,
  ShieldCheckIcon,
  UserIcon,
  ClipboardIcon,
  PlusIcon
} from '@heroicons/react/24/outline';

interface GroupSettingsProps {
  conversation: Conversation;
  participants: Participant[];
  currentUser: User;
  onUpdateSettings: (settings: any) => Promise<void>;
  onUpdateParticipantRole: (userId: string, role: 'admin' | 'member') => Promise<void>;
  onRemoveParticipant: (userId: string) => Promise<void>;
  onGenerateInviteLink: (options: { expires_at?: string; max_uses?: number }) => Promise<{ invite_url: string }>;
  trigger?: React.ReactNode;
}

export default function GroupSettings({ 
  conversation, 
  participants, 
  currentUser,
  onUpdateSettings,
  onUpdateParticipantRole,
  onRemoveParticipant,
  onGenerateInviteLink,
  trigger 
}: GroupSettingsProps) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [inviteLinks, setInviteLinks] = useState<Array<{ invite_url: string; expires_at?: string; max_uses?: number }>>([]);
  
  // Settings form state
  const [settings, setSettings] = useState({
    name: conversation.name || '',
    description: conversation.description || '',
    avatar_url: conversation.avatar_url || '',
    allow_member_invite: conversation.metadata?.settings?.allow_member_invite ?? true,
    only_admins_can_message: conversation.metadata?.settings?.only_admins_can_message ?? false,
    message_retention_days: conversation.metadata?.settings?.message_retention_days ?? 30,
  });

  const currentUserParticipant = participants.find(p => p.user_id === currentUser.id);
  const isOwner = currentUserParticipant?.role === 'owner';
  const isAdmin = currentUserParticipant?.role === 'admin' || isOwner;

  const handleUpdateSettings = async () => {
    setLoading(true);
    try {
      await onUpdateSettings({
        name: settings.name,
        description: settings.description,
        avatar_url: settings.avatar_url || null,
        settings: {
          allow_member_invite: settings.allow_member_invite,
          only_admins_can_message: settings.only_admins_can_message,
          message_retention_days: settings.message_retention_days,
        }
      });
      toast.success('Group settings updated successfully');
    } catch (error) {
      toast.error('Failed to update group settings');
    } finally {
      setLoading(false);
    }
  };

  const handleRoleChange = async (userId: string, newRole: 'admin' | 'member') => {
    try {
      await onUpdateParticipantRole(userId, newRole);
      toast.success(`Member role updated to ${newRole}`);
    } catch (error) {
      toast.error('Failed to update member role');
    }
  };

  const handleRemoveMember = async (userId: string) => {
    try {
      await onRemoveParticipant(userId);
      toast.success('Member removed from group');
    } catch (error) {
      toast.error('Failed to remove member');
    }
  };

  const handleGenerateInviteLink = async () => {
    setLoading(true);
    try {
      const result = await onGenerateInviteLink({});
      setInviteLinks([...inviteLinks, result]);
      toast.success('Invite link generated');
    } catch (error) {
      toast.error('Failed to generate invite link');
    } finally {
      setLoading(false);
    }
  };

  const copyInviteLink = (url: string) => {
    navigator.clipboard.writeText(url);
    toast.success('Invite link copied to clipboard');
  };

  const getRoleIcon = (role: string) => {
    switch (role) {
      case 'owner':
        return <ShieldCheckIcon className="h-4 w-4 text-yellow-500" />;
      case 'admin':
        return <ShieldCheckIcon className="h-4 w-4 text-blue-500" />;
      default:
        return <UserIcon className="h-4 w-4 text-gray-500" />;
    }
  };

  const getRoleBadge = (role: string) => {
    const colors = {
      owner: 'bg-yellow-100 text-yellow-800',
      admin: 'bg-blue-100 text-blue-800',
      member: 'bg-gray-100 text-gray-800',
    };
    return <Badge className={colors[role as keyof typeof colors] || colors.member}>{role}</Badge>;
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {trigger || (
          <Button variant="ghost" size="sm">
            <CogIcon className="h-4 w-4" />
          </Button>
        )}
      </DialogTrigger>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <UserGroupIcon className="h-5 w-5" />
            <span>Group Settings</span>
          </DialogTitle>
        </DialogHeader>

        <Tabs defaultValue="general" className="w-full">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="general">General</TabsTrigger>
            <TabsTrigger value="members">Members ({participants.length})</TabsTrigger>
            <TabsTrigger value="invite">Invite</TabsTrigger>
          </TabsList>

          <TabsContent value="general" className="space-y-4">
            <div className="space-y-4">
              <div>
                <Label htmlFor="group-name">Group Name</Label>
                <Input
                  id="group-name"
                  value={settings.name}
                  onChange={(e) => setSettings({ ...settings, name: e.target.value })}
                  placeholder="Enter group name..."
                  disabled={!isAdmin}
                />
              </div>

              <div>
                <Label htmlFor="group-description">Description</Label>
                <Textarea
                  id="group-description"
                  value={settings.description}
                  onChange={(e) => setSettings({ ...settings, description: e.target.value })}
                  placeholder="Enter group description..."
                  disabled={!isAdmin}
                  rows={3}
                />
              </div>

              <div>
                <Label htmlFor="avatar-url">Avatar URL</Label>
                <Input
                  id="avatar-url"
                  value={settings.avatar_url}
                  onChange={(e) => setSettings({ ...settings, avatar_url: e.target.value })}
                  placeholder="https://example.com/avatar.jpg"
                  disabled={!isAdmin}
                />
              </div>

              {isAdmin && (
                <div className="space-y-4 border-t pt-4">
                  <h4 className="font-medium">Group Permissions</h4>
                  
                  <div className="flex items-center justify-between">
                    <div>
                      <Label htmlFor="allow-member-invite">Allow members to invite others</Label>
                      <p className="text-sm text-gray-500">Members can generate invite links</p>
                    </div>
                    <Switch
                      id="allow-member-invite"
                      checked={settings.allow_member_invite}
                      onCheckedChange={(checked) => setSettings({ ...settings, allow_member_invite: checked })}
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <Label htmlFor="only-admins-message">Only admins can send messages</Label>
                      <p className="text-sm text-gray-500">Restrict messaging to admins only</p>
                    </div>
                    <Switch
                      id="only-admins-message"
                      checked={settings.only_admins_can_message}
                      onCheckedChange={(checked) => setSettings({ ...settings, only_admins_can_message: checked })}
                    />
                  </div>

                  <div>
                    <Label htmlFor="retention-days">Message retention (days)</Label>
                    <Input
                      id="retention-days"
                      type="number"
                      min="1"
                      max="365"
                      value={settings.message_retention_days}
                      onChange={(e) => setSettings({ ...settings, message_retention_days: parseInt(e.target.value) || 30 })}
                    />
                    <p className="text-sm text-gray-500">Messages older than this will be automatically deleted</p>
                  </div>
                </div>
              )}

              {isAdmin && (
                <div className="flex justify-end">
                  <Button onClick={handleUpdateSettings} disabled={loading}>
                    {loading ? 'Saving...' : 'Save Settings'}
                  </Button>
                </div>
              )}
            </div>
          </TabsContent>

          <TabsContent value="members" className="space-y-4">
            <div className="space-y-3">
              {participants.map((participant) => (
                <div key={participant.id} className="flex items-center justify-between p-3 rounded-lg border">
                  <div className="flex items-center space-x-3">
                    <Avatar className="h-10 w-10">
                      <AvatarFallback>
                        {participant.user?.name?.charAt(0).toUpperCase() || '?'}
                      </AvatarFallback>
                    </Avatar>
                    <div>
                      <div className="flex items-center space-x-2">
                        <span className="font-medium">{participant.user?.name || 'Unknown User'}</span>
                        {getRoleBadge(participant.role)}
                      </div>
                      <p className="text-sm text-gray-500">{participant.user?.email}</p>
                      {participant.user_id === currentUser.id && (
                        <p className="text-xs text-blue-600">You</p>
                      )}
                    </div>
                  </div>

                  {isOwner && participant.user_id !== currentUser.id && (
                    <div className="flex items-center space-x-2">
                      {participant.role !== 'owner' && (
                        <select
                          value={participant.role}
                          onChange={(e) => handleRoleChange(participant.user_id, e.target.value as 'admin' | 'member')}
                          className="text-sm border rounded px-2 py-1"
                        >
                          <option value="member">Member</option>
                          <option value="admin">Admin</option>
                        </select>
                      )}
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleRemoveMember(participant.user_id)}
                        className="text-red-600 hover:text-red-700"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </Button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="invite" className="space-y-4">
            {(isAdmin || settings.allow_member_invite) ? (
              <>
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-medium">Invite Links</h4>
                    <p className="text-sm text-gray-500">Generate links to invite new members</p>
                  </div>
                  <Button onClick={handleGenerateInviteLink} disabled={loading}>
                    <PlusIcon className="h-4 w-4 mr-2" />
                    Generate Link
                  </Button>
                </div>

                {inviteLinks.length > 0 && (
                  <div className="space-y-3">
                    {inviteLinks.map((link, index) => (
                      <div key={index} className="flex items-center justify-between p-3 rounded-lg border bg-gray-50">
                        <div className="flex-1 mr-3">
                          <p className="text-sm font-mono text-gray-700 truncate">
                            {link.invite_url}
                          </p>
                          <div className="flex items-center space-x-4 text-xs text-gray-500 mt-1">
                            {link.expires_at && (
                              <span>Expires: {new Date(link.expires_at).toLocaleDateString()}</span>
                            )}
                            {link.max_uses && (
                              <span>Max uses: {link.max_uses}</span>
                            )}
                          </div>
                        </div>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => copyInviteLink(link.invite_url)}
                        >
                          <ClipboardIcon className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}

                {inviteLinks.length === 0 && (
                  <div className="text-center py-8 text-gray-500">
                    <LinkIcon className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                    <p>No invite links generated yet</p>
                    <p className="text-sm">Click "Generate Link" to create one</p>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center py-8 text-gray-500">
                <LinkIcon className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                <p>You don't have permission to generate invite links</p>
                <p className="text-sm">Ask a group admin to enable member invites</p>
              </div>
            )}
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}