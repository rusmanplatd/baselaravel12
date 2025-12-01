import React, { useState, useEffect } from 'react';
import { 
    Settings, 
    Users, 
    BarChart3, 
    MessageSquare, 
    UserPlus, 
    UserX, 
    Shield, 
    Download,
    Trash2,
    Crown,
    Ban,
    AlertTriangle
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogFooter,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import { useChannelManagement } from '@/hooks/useChannelManagement';

interface Channel {
    id: string;
    name: string;
    username: string;
    description: string;
    is_verified: boolean;
    is_broadcast: boolean;
    subscriber_count: number;
    view_count: number;
    privacy: string;
    category: string;
    created_by_user_id: string;
    channel_settings?: Record<string, any>;
    creator: {
        id: string;
        name: string;
    };
}

interface ChannelManagementDashboardProps {
    channel: Channel;
    isOwner: boolean;
    onChannelUpdate: (channel: Channel) => void;
    className?: string;
}

export default function ChannelManagementDashboard({ 
    channel, 
    isOwner, 
    onChannelUpdate,
    className 
}: ChannelManagementDashboardProps) {
    const [activeTab, setActiveTab] = useState('settings');
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [showTransferDialog, setShowTransferDialog] = useState(false);
    const [deleteConfirmation, setDeleteConfirmation] = useState('');
    const [transferConfirmation, setTransferConfirmation] = useState('');
    const [selectedNewOwner, setSelectedNewOwner] = useState('');

    const {
        statistics,
        admins,
        bannedUsers,
        isLoading,
        error,
        loadStatistics,
        loadAdmins,
        loadBannedUsers,
        addAdmin,
        removeAdmin,
        banUser,
        unbanUser,
        updateChannelSettings,
        transferOwnership,
        deleteChannel,
        exportChannelData,
    } = useChannelManagement(channel.id);

    useEffect(() => {
        if (activeTab === 'analytics') {
            loadStatistics('week');
        } else if (activeTab === 'admins') {
            loadAdmins();
        } else if (activeTab === 'banned') {
            loadBannedUsers();
        }
    }, [activeTab]);

    const handleSettingsUpdate = async (settings: Record<string, any>) => {
        try {
            await updateChannelSettings(settings);
            onChannelUpdate({ ...channel, channel_settings: settings });
            toast.success('Settings updated successfully');
        } catch (err) {
            toast.error('Failed to update settings');
        }
    };

    const handleDeleteChannel = async () => {
        if (deleteConfirmation !== 'DELETE') {
            toast.error('Please type DELETE to confirm');
            return;
        }

        try {
            await deleteChannel('Channel deleted by owner');
            toast.success('Channel deleted successfully');
            setShowDeleteDialog(false);
            // Redirect or close management dashboard
        } catch (err) {
            toast.error('Failed to delete channel');
        }
    };

    const handleTransferOwnership = async () => {
        if (transferConfirmation !== 'TRANSFER' || !selectedNewOwner) {
            toast.error('Please complete the transfer confirmation');
            return;
        }

        try {
            await transferOwnership(selectedNewOwner);
            toast.success('Ownership transferred successfully');
            setShowTransferDialog(false);
            // Update UI to reflect new ownership
        } catch (err) {
            toast.error('Failed to transfer ownership');
        }
    };

    const formatNumber = (num: number): string => {
        if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
        return num.toString();
    };

    return (
        <div className={`max-w-6xl mx-auto ${className}`}>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Channel Management</h1>
                <p className="text-gray-600">Manage your channel settings, subscribers, and content</p>
            </div>

            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                <TabsList className="grid w-full grid-cols-5">
                    <TabsTrigger value="settings" className="flex items-center gap-2">
                        <Settings className="h-4 w-4" />
                        Settings
                    </TabsTrigger>
                    <TabsTrigger value="analytics" className="flex items-center gap-2">
                        <BarChart3 className="h-4 w-4" />
                        Analytics
                    </TabsTrigger>
                    <TabsTrigger value="admins" className="flex items-center gap-2">
                        <Shield className="h-4 w-4" />
                        Admins
                    </TabsTrigger>
                    <TabsTrigger value="moderation" className="flex items-center gap-2">
                        <Ban className="h-4 w-4" />
                        Moderation
                    </TabsTrigger>
                    <TabsTrigger value="advanced" className="flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4" />
                        Advanced
                    </TabsTrigger>
                </TabsList>

                {/* Channel Settings */}
                <TabsContent value="settings" className="space-y-6">
                    <ChannelSettings 
                        channel={channel}
                        onUpdate={handleSettingsUpdate}
                    />
                </TabsContent>

                {/* Analytics */}
                <TabsContent value="analytics" className="space-y-6">
                    <ChannelAnalytics 
                        statistics={statistics}
                        isLoading={isLoading}
                        onPeriodChange={loadStatistics}
                    />
                </TabsContent>

                {/* Admins Management */}
                <TabsContent value="admins" className="space-y-6">
                    <AdminManagement
                        admins={admins}
                        channelOwnerId={channel.created_by_user_id}
                        isLoading={isLoading}
                        onAddAdmin={addAdmin}
                        onRemoveAdmin={removeAdmin}
                    />
                </TabsContent>

                {/* Moderation */}
                <TabsContent value="moderation" className="space-y-6">
                    <ModerationPanel
                        bannedUsers={bannedUsers}
                        isLoading={isLoading}
                        onBanUser={banUser}
                        onUnbanUser={unbanUser}
                    />
                </TabsContent>

                {/* Advanced Settings */}
                <TabsContent value="advanced" className="space-y-6">
                    <div className="grid gap-6">
                        {/* Export Data */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Download className="h-5 w-5" />
                                    Export Channel Data
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-gray-600 mb-4">
                                    Export all channel data including subscriber information, messages, and analytics.
                                </p>
                                <Button onClick={exportChannelData} variant="outline">
                                    <Download className="h-4 w-4 mr-2" />
                                    Export Data
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Transfer Ownership */}
                        {isOwner && (
                            <Card className="border-orange-200">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-orange-600">
                                        <Crown className="h-5 w-5" />
                                        Transfer Ownership
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Alert className="mb-4">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            Transferring ownership cannot be undone. The new owner will have full control over the channel.
                                        </AlertDescription>
                                    </Alert>
                                    
                                    <Dialog open={showTransferDialog} onOpenChange={setShowTransferDialog}>
                                        <DialogTrigger asChild>
                                            <Button variant="outline" className="border-orange-300 text-orange-600 hover:bg-orange-50">
                                                Transfer Ownership
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>Transfer Channel Ownership</DialogTitle>
                                            </DialogHeader>
                                            <div className="space-y-4">
                                                <div>
                                                    <Label>Select New Owner</Label>
                                                    <Select value={selectedNewOwner} onValueChange={setSelectedNewOwner}>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Choose an admin" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {admins?.filter(admin => !admin.is_owner).map((admin) => (
                                                                <SelectItem key={admin.id} value={admin.id}>
                                                                    {admin.name} ({admin.email})
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                
                                                <div>
                                                    <Label>Type "TRANSFER" to confirm</Label>
                                                    <Input
                                                        value={transferConfirmation}
                                                        onChange={(e) => setTransferConfirmation(e.target.value)}
                                                        placeholder="TRANSFER"
                                                    />
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <Button variant="outline" onClick={() => setShowTransferDialog(false)}>
                                                    Cancel
                                                </Button>
                                                <Button 
                                                    onClick={handleTransferOwnership}
                                                    disabled={transferConfirmation !== 'TRANSFER' || !selectedNewOwner}
                                                    className="bg-orange-600 hover:bg-orange-700"
                                                >
                                                    Transfer Ownership
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </CardContent>
                            </Card>
                        )}

                        {/* Delete Channel */}
                        {isOwner && (
                            <Card className="border-red-200">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-red-600">
                                        <Trash2 className="h-5 w-5" />
                                        Delete Channel
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Alert className="mb-4" variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            This action cannot be undone. All messages, subscribers, and data will be permanently deleted.
                                        </AlertDescription>
                                    </Alert>
                                    
                                    <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                                        <DialogTrigger asChild>
                                            <Button variant="destructive">Delete Channel</Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>Delete Channel</DialogTitle>
                                            </DialogHeader>
                                            <div className="space-y-4">
                                                <Alert variant="destructive">
                                                    <AlertTriangle className="h-4 w-4" />
                                                    <AlertDescription>
                                                        This will permanently delete the channel "{channel.name}" and all associated data.
                                                    </AlertDescription>
                                                </Alert>
                                                
                                                <div>
                                                    <Label>Type "DELETE" to confirm</Label>
                                                    <Input
                                                        value={deleteConfirmation}
                                                        onChange={(e) => setDeleteConfirmation(e.target.value)}
                                                        placeholder="DELETE"
                                                    />
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
                                                    Cancel
                                                </Button>
                                                <Button 
                                                    variant="destructive"
                                                    onClick={handleDeleteChannel}
                                                    disabled={deleteConfirmation !== 'DELETE'}
                                                >
                                                    Delete Channel
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    );
}

// Sub-components would be created as separate files, but for brevity including placeholders here
function ChannelSettings({ channel, onUpdate }: { channel: Channel; onUpdate: (settings: any) => void }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Channel Settings</CardTitle>
            </CardHeader>
            <CardContent>
                <p>Channel settings component would go here</p>
            </CardContent>
        </Card>
    );
}

function ChannelAnalytics({ statistics, isLoading, onPeriodChange }: { statistics: any; isLoading: boolean; onPeriodChange: (period: string) => void }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Analytics</CardTitle>
            </CardHeader>
            <CardContent>
                <p>Analytics component would go here</p>
            </CardContent>
        </Card>
    );
}

function AdminManagement({ admins, channelOwnerId, isLoading, onAddAdmin, onRemoveAdmin }: any) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Admin Management</CardTitle>
            </CardHeader>
            <CardContent>
                <p>Admin management component would go here</p>
            </CardContent>
        </Card>
    );
}

function ModerationPanel({ bannedUsers, isLoading, onBanUser, onUnbanUser }: any) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Moderation</CardTitle>
            </CardHeader>
            <CardContent>
                <p>Moderation panel would go here</p>
            </CardContent>
        </Card>
    );
}