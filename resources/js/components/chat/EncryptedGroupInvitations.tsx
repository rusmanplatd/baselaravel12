import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { QrCodeIcon, LinkIcon, ShieldCheckIcon, UserPlusIcon, ClockIcon, EyeIcon, CopyIcon } from '@heroicons/react/24/outline';
import { QRCodeSVG } from 'qrcode.react';
import { formatDistanceToNow, addDays, addHours } from 'date-fns';
import { toast } from 'sonner';

interface GroupInvitation {
  id: string;
  groupId: string;
  groupName: string;
  inviteCode: string;
  inviteLink: string;
  qrCode?: string;
  expiresAt: Date;
  maxUses?: number;
  currentUses: number;
  createdBy: string;
  createdAt: Date;
  permissions: {
    canInvite: boolean;
    canManageGroup: boolean;
    canViewHistory: boolean;
  };
  encryptedWelcomeMessage?: string;
  status: 'active' | 'expired' | 'revoked' | 'exhausted';
}

interface EncryptedGroupInvitationsProps {
  groupId: string;
  groupName: string;
  currentUserId: string;
  canCreateInvites: boolean;
  onCreateInvitation: (invitation: {
    expiresAt: Date;
    maxUses?: number;
    permissions: {
      canInvite: boolean;
      canManageGroup: boolean;
      canViewHistory: boolean;
    };
    welcomeMessage?: string;
  }) => Promise<GroupInvitation>;
  onRevokeInvitation: (invitationId: string) => Promise<void>;
  existingInvitations: GroupInvitation[];
}

const EXPIRY_OPTIONS = [
  { label: '1 hour', value: 1, unit: 'hour' as const },
  { label: '6 hours', value: 6, unit: 'hour' as const },
  { label: '24 hours', value: 24, unit: 'hour' as const },
  { label: '3 days', value: 3, unit: 'day' as const },
  { label: '7 days', value: 7, unit: 'day' as const },
  { label: '30 days', value: 30, unit: 'day' as const },
  { label: 'Never', value: 0, unit: 'never' as const },
];

const MAX_USES_OPTIONS = [
  { label: 'Unlimited', value: 0 },
  { label: '1 use', value: 1 },
  { label: '5 uses', value: 5 },
  { label: '10 uses', value: 10 },
  { label: '25 uses', value: 25 },
  { label: '100 uses', value: 100 },
];

export default function EncryptedGroupInvitations({
  groupId,
  groupName,
  currentUserId,
  canCreateInvites,
  onCreateInvitation,
  onRevokeInvitation,
  existingInvitations
}: EncryptedGroupInvitationsProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<'create' | 'manage'>('create');
  const [isCreating, setIsCreating] = useState(false);

  // Create invitation form state
  const [expiryOption, setExpiryOption] = useState(EXPIRY_OPTIONS[3]); // 3 days default
  const [maxUses, setMaxUses] = useState(0); // unlimited
  const [canInvite, setCanInvite] = useState(false);
  const [canManageGroup, setCanManageGroup] = useState(false);
  const [canViewHistory, setCanViewHistory] = useState(true);
  const [welcomeMessage, setWelcomeMessage] = useState('');

  // Reset form when dialog opens
  useEffect(() => {
    if (isOpen && activeTab === 'create') {
      setExpiryOption(EXPIRY_OPTIONS[3]);
      setMaxUses(0);
      setCanInvite(false);
      setCanManageGroup(false);
      setCanViewHistory(true);
      setWelcomeMessage('');
    }
  }, [isOpen, activeTab]);

  const handleCreateInvitation = async () => {
    setIsCreating(true);
    try {
      const expiresAt = expiryOption.value === 0 
        ? addDays(new Date(), 365) // Far future for "never"
        : expiryOption.unit === 'hour' 
          ? addHours(new Date(), expiryOption.value)
          : addDays(new Date(), expiryOption.value);

      await onCreateInvitation({
        expiresAt,
        maxUses: maxUses > 0 ? maxUses : undefined,
        permissions: {
          canInvite,
          canManageGroup,
          canViewHistory,
        },
        welcomeMessage: welcomeMessage.trim() || undefined,
      });

      toast.success('Encrypted invitation created successfully');
      setActiveTab('manage');
    } catch (error) {
      toast.error('Failed to create invitation');
    } finally {
      setIsCreating(false);
    }
  };

  const handleRevokeInvitation = async (invitationId: string) => {
    try {
      await onRevokeInvitation(invitationId);
      toast.success('Invitation revoked');
    } catch (error) {
      toast.error('Failed to revoke invitation');
    }
  };

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success('Copied to clipboard');
    } catch (error) {
      toast.error('Failed to copy');
    }
  };

  const getInvitationStatusBadge = (invitation: GroupInvitation) => {
    const now = new Date();
    
    if (invitation.status === 'revoked') {
      return <Badge variant="destructive">Revoked</Badge>;
    }
    
    if (invitation.expiresAt <= now) {
      return <Badge variant="secondary">Expired</Badge>;
    }
    
    if (invitation.maxUses && invitation.currentUses >= invitation.maxUses) {
      return <Badge variant="secondary">Exhausted</Badge>;
    }
    
    return <Badge variant="default">Active</Badge>;
  };

  const activeInvitations = existingInvitations.filter(inv => 
    inv.status === 'active' && 
    inv.expiresAt > new Date() && 
    (!inv.maxUses || inv.currentUses < inv.maxUses)
  );

  return (
    <Dialog open={isOpen} onOpenChange={setIsOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="flex items-center gap-2">
          <UserPlusIcon className="h-4 w-4" />
          Group Invites
          {activeInvitations.length > 0 && (
            <Badge variant="secondary" className="ml-1">
              {activeInvitations.length}
            </Badge>
          )}
        </Button>
      </DialogTrigger>

      <DialogContent className="max-w-2xl max-h-[90vh]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ShieldCheckIcon className="h-5 w-5 text-green-600" />
            Encrypted Group Invitations
          </DialogTitle>
          <DialogDescription>
            Create secure, encrypted invitations to {groupName}. All invitation data is end-to-end encrypted.
          </DialogDescription>
        </DialogHeader>

        <Tabs value={activeTab} onValueChange={(value: any) => setActiveTab(value)} className="flex-1">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="create" disabled={!canCreateInvites}>
              Create Invitation
            </TabsTrigger>
            <TabsTrigger value="manage">
              Manage ({existingInvitations.length})
            </TabsTrigger>
          </TabsList>

          <TabsContent value="create" className="space-y-4 mt-4">
            {!canCreateInvites ? (
              <div className="text-center py-8 text-muted-foreground">
                You don't have permission to create group invitations.
              </div>
            ) : (
              <>
                {/* Expiration Settings */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Invitation Expires</Label>
                    <Select 
                      value={`${expiryOption.value}_${expiryOption.unit}`}
                      onValueChange={(value) => {
                        const option = EXPIRY_OPTIONS.find(opt => `${opt.value}_${opt.unit}` === value);
                        if (option) setExpiryOption(option);
                      }}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {EXPIRY_OPTIONS.map((option) => (
                          <SelectItem key={`${option.value}_${option.unit}`} value={`${option.value}_${option.unit}`}>
                            {option.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label>Maximum Uses</Label>
                    <Select value={maxUses.toString()} onValueChange={(value) => setMaxUses(parseInt(value))}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {MAX_USES_OPTIONS.map((option) => (
                          <SelectItem key={option.value} value={option.value.toString()}>
                            {option.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                {/* Permissions */}
                <div className="space-y-3">
                  <Label className="text-base">Member Permissions</Label>
                  <div className="space-y-2">
                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="can-view-history"
                        checked={canViewHistory}
                        onChange={(e) => setCanViewHistory(e.target.checked)}
                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <Label htmlFor="can-view-history" className="text-sm">
                        Can view message history
                      </Label>
                    </div>
                    
                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="can-invite"
                        checked={canInvite}
                        onChange={(e) => setCanInvite(e.target.checked)}
                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <Label htmlFor="can-invite" className="text-sm">
                        Can invite other members
                      </Label>
                    </div>
                    
                    <div className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        id="can-manage"
                        checked={canManageGroup}
                        onChange={(e) => setCanManageGroup(e.target.checked)}
                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <Label htmlFor="can-manage" className="text-sm">
                        Can manage group settings
                      </Label>
                    </div>
                  </div>
                </div>

                {/* Welcome Message */}
                <div className="space-y-2">
                  <Label htmlFor="welcome-message">Welcome Message (Optional)</Label>
                  <textarea
                    id="welcome-message"
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    placeholder="Add a welcome message for new members..."
                    value={welcomeMessage}
                    onChange={(e) => setWelcomeMessage(e.target.value)}
                  />
                </div>

                {/* Create Button */}
                <Button 
                  onClick={handleCreateInvitation} 
                  disabled={isCreating}
                  className="w-full"
                >
                  {isCreating ? 'Creating...' : 'Create Encrypted Invitation'}
                </Button>
              </>
            )}
          </TabsContent>

          <TabsContent value="manage" className="mt-4">
            <ScrollArea className="max-h-96">
              {existingInvitations.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  No invitations created yet
                </div>
              ) : (
                <div className="space-y-4">
                  {existingInvitations.map((invitation) => (
                    <div key={invitation.id} className="border rounded-lg p-4 space-y-3">
                      <div className="flex items-start justify-between">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            {getInvitationStatusBadge(invitation)}
                            <span className="text-xs text-muted-foreground">
                              Created {formatDistanceToNow(invitation.createdAt, { addSuffix: true })}
                            </span>
                          </div>
                          <div className="text-sm text-muted-foreground">
                            {invitation.currentUses}/{invitation.maxUses || '∞'} uses • 
                            Expires {formatDistanceToNow(invitation.expiresAt, { addSuffix: true })}
                          </div>
                        </div>

                        {invitation.status === 'active' && invitation.expiresAt > new Date() && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleRevokeInvitation(invitation.id)}
                            className="text-red-600 hover:text-red-700"
                          >
                            Revoke
                          </Button>
                        )}
                      </div>

                      {invitation.status === 'active' && invitation.expiresAt > new Date() && (
                        <div className="space-y-2">
                          {/* Invite Link */}
                          <div className="flex items-center gap-2">
                            <Input
                              value={invitation.inviteLink}
                              readOnly
                              className="font-mono text-xs"
                            />
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => copyToClipboard(invitation.inviteLink)}
                            >
                              <CopyIcon className="h-4 w-4" />
                            </Button>
                          </div>

                          {/* QR Code */}
                          <div className="flex justify-center">
                            <div className="bg-white p-2 rounded border">
                              <QRCodeSVG 
                                value={invitation.inviteLink} 
                                size={120}
                                level="M"
                                includeMargin={true}
                              />
                            </div>
                          </div>
                        </div>
                      )}

                      {/* Permissions */}
                      <div className="flex flex-wrap gap-1">
                        {invitation.permissions.canViewHistory && (
                          <Badge variant="outline" className="text-xs">
                            <EyeIcon className="h-3 w-3 mr-1" />
                            View History
                          </Badge>
                        )}
                        {invitation.permissions.canInvite && (
                          <Badge variant="outline" className="text-xs">
                            <UserPlusIcon className="h-3 w-3 mr-1" />
                            Can Invite
                          </Badge>
                        )}
                        {invitation.permissions.canManageGroup && (
                          <Badge variant="outline" className="text-xs">
                            Manage Group
                          </Badge>
                        )}
                      </div>

                      {invitation.encryptedWelcomeMessage && (
                        <div className="bg-muted p-2 rounded text-xs">
                          <strong>Welcome message:</strong> [Encrypted content]
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </ScrollArea>
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}