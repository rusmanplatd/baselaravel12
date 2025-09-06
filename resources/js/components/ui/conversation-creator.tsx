import React, { useState, useCallback } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { UserSearchCombobox } from '@/components/ui/user-search-combobox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MessageCircle, Users, Hash, X, Shield, Zap, Settings } from 'lucide-react';
import { useE2EEChat } from '@/hooks/useE2EEChat';
import { apiService } from '@/services/ApiService';
import { toast } from 'sonner';

export interface User {
    id: string;
    name: string;
    avatar?: string;
    email?: string;
}

interface ConversationCreatorProps {
    trigger?: React.ReactNode;
    onConversationCreated?: (conversationId: string) => void;
    defaultType?: 'direct' | 'group' | 'channel';
    defaultParticipants?: User[];
}

interface ConversationFormData {
    type: 'direct' | 'group' | 'channel';
    name: string;
    description: string;
    participants: User[];
    enableQuantum: boolean;
    keyStrength: 512 | 768 | 1024;
    avatarUrl: string;
    settings: {
        isPublic?: boolean;
        allowMemberInvites?: boolean;
        moderated?: boolean;
        everyoneCanAddMembers?: boolean;
    };
}

export function ConversationCreator({ 
    trigger, 
    onConversationCreated,
    defaultType = 'direct',
    defaultParticipants = [] 
}: ConversationCreatorProps) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [formData, setFormData] = useState<ConversationFormData>({
        type: defaultType,
        name: '',
        description: '',
        participants: defaultParticipants,
        enableQuantum: true,
        keyStrength: 768 as 512 | 768 | 1024,
        avatarUrl: '',
        settings: {
            isPublic: false,
            allowMemberInvites: true,
            moderated: false,
            everyoneCanAddMembers: true,
        }
    });

    const { createConversation, error } = useE2EEChat();

    const handleTypeChange = useCallback((type: 'direct' | 'group' | 'channel') => {
        setFormData(prev => ({
            ...prev,
            type,
            // Reset participants if switching to direct (limit to 2)
            participants: type === 'direct' ? prev.participants.slice(0, 1) : prev.participants,
            // Reset settings based on type
            settings: {
                ...prev.settings,
                isPublic: type === 'channel' ? false : undefined,
                moderated: type === 'channel' ? false : undefined,
                everyoneCanAddMembers: type === 'group' ? true : undefined,
            }
        }));
    }, []);

    const handleParticipantAdd = useCallback(async (userEmail: string) => {
        // First, fetch the user details by email using authenticated API service
        try {
            // Use the suggestions endpoint like UserSearchCombobox does
            const users = await apiService.get<Array<{
                id: number;
                name: string;
                email: string;
                avatar?: string;
            }>>(`/api/v1/users/suggestions?q=${encodeURIComponent(userEmail)}`);
            
            const user = users.find((u: any) => u.email === userEmail);
            
            if (!user) {
                toast.error('User not found');
                return;
            }

            const userObj: User = {
                id: user.id.toString(),
                name: user.name,
                email: user.email,
                avatar: user.avatar ? `/storage/${user.avatar}` : undefined,
            };
            
            setFormData(prev => {
                // Check for duplicates
                if (prev.participants.some(p => p.id === userObj.id)) {
                    toast.error('User already added');
                    return prev;
                }
                
                // Limit participants based on type
                const maxParticipants = prev.type === 'direct' ? 1 : prev.type === 'group' ? 99 : 499;
                if (prev.participants.length >= maxParticipants) {
                    toast.error(`Maximum ${maxParticipants + 1} participants allowed for ${prev.type} conversations`);
                    return prev;
                }
                
                return {
                    ...prev,
                    participants: [...prev.participants, userObj]
                };
            });
        } catch (error) {
            console.error('Error fetching user:', error);
            toast.error('Failed to add user');
        }
    }, []);

    const handleParticipantRemove = useCallback((userId: string) => {
        setFormData(prev => ({
            ...prev,
            participants: prev.participants.filter(p => p.id !== userId)
        }));
    }, []);

    const validateForm = useCallback((): string | null => {
        if (formData.participants.length === 0) {
            return 'Please select at least one participant';
        }
        
        if (formData.type === 'direct' && formData.participants.length !== 1) {
            return 'Direct messages require exactly one other participant';
        }
        
        if ((formData.type === 'group' || formData.type === 'channel') && !formData.name.trim()) {
            return `${formData.type === 'group' ? 'Group' : 'Channel'} name is required`;
        }
        
        if (formData.type === 'group' && formData.participants.length < 1) {
            return 'Groups require at least one other participant';
        }
        
        return null;
    }, [formData]);

    const handleSubmit = useCallback(async () => {
        const validationError = validateForm();
        if (validationError) {
            toast.error(validationError);
            return;
        }

        setLoading(true);
        try {
            const conversation = await createConversation(
                formData.participants.map(p => p.id),
                {
                    type: formData.type,
                    name: formData.name.trim() || undefined,
                    description: formData.description.trim() || undefined,
                    avatar_url: formData.avatarUrl.trim() || undefined,
                    enable_quantum: formData.enableQuantum,
                    key_strength: formData.keyStrength,
                    settings: formData.settings
                }
            );

            toast.success(`${formData.type === 'direct' ? 'Direct message' : formData.type === 'group' ? 'Group' : 'Channel'} created successfully!`);
            setOpen(false);
            
            // Notify parent component
            if (conversation?.id && onConversationCreated) {
                onConversationCreated(conversation.id);
            }
            
            // Reset form
            setFormData({
                type: 'direct',
                name: '',
                description: '',
                participants: [],
                enableQuantum: true,
                keyStrength: 768 as 512 | 768 | 1024,
                avatarUrl: '',
                settings: {
                    isPublic: false,
                    allowMemberInvites: true,
                    moderated: false,
                    everyoneCanAddMembers: true,
                }
            });
            
        } catch (err) {
            toast.error(error || 'Failed to create conversation');
        } finally {
            setLoading(false);
        }
    }, [createConversation, formData, validateForm, error, onConversationCreated]);

    const getMaxParticipants = (type: string) => {
        switch (type) {
            case 'direct': return 1;
            case 'group': return 100;
            case 'channel': return 500;
            default: return 100;
        }
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'direct': return MessageCircle;
            case 'group': return Users;
            case 'channel': return Hash;
            default: return MessageCircle;
        }
    };

    const getTypeDescription = (type: string) => {
        switch (type) {
            case 'direct': return 'Private one-on-one conversation with end-to-end encryption';
            case 'group': return 'Small group chat with up to 100 participants';
            case 'channel': return 'Large broadcast channel with up to 500 participants';
            default: return '';
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger || (
                    <Button variant="default" className="gap-2">
                        <MessageCircle className="h-4 w-4" />
                        New Conversation
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Create New Conversation</DialogTitle>
                    <DialogDescription>
                        Start a secure, encrypted conversation with your colleagues
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Conversation Type Selection */}
                    <div className="space-y-3">
                        <Label className="text-sm font-medium">Conversation Type</Label>
                        <Tabs value={formData.type} onValueChange={handleTypeChange} className="w-full">
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="direct" className="flex items-center gap-2">
                                    <MessageCircle className="h-4 w-4" />
                                    Direct
                                </TabsTrigger>
                                <TabsTrigger value="group" className="flex items-center gap-2">
                                    <Users className="h-4 w-4" />
                                    Group
                                </TabsTrigger>
                                <TabsTrigger value="channel" className="flex items-center gap-2">
                                    <Hash className="h-4 w-4" />
                                    Channel
                                </TabsTrigger>
                            </TabsList>
                            
                            <div className="mt-3 p-3 bg-muted rounded-lg">
                                <div className="flex items-center gap-2 mb-1">
                                    {React.createElement(getTypeIcon(formData.type), { className: "h-4 w-4" })}
                                    <span className="font-medium capitalize">{formData.type}</span>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {getTypeDescription(formData.type)}
                                </p>
                            </div>
                        </Tabs>
                    </div>

                    {/* Participants Selection */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-medium">
                                Participants ({formData.participants.length}/{getMaxParticipants(formData.type)})
                            </Label>
                            {formData.type === 'direct' && (
                                <Badge variant="secondary">1-on-1</Badge>
                            )}
                        </div>
                        
                        <UserSearchCombobox
                            onSelect={handleParticipantAdd}
                            placeholder={`Search for ${formData.type === 'direct' ? 'a person' : 'people'} to add...`}
                            disabled={formData.participants.length >= getMaxParticipants(formData.type)}
                        />
                        
                        {formData.participants.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {formData.participants.map(participant => (
                                    <Badge key={participant.id} variant="outline" className="gap-2">
                                        {participant.avatar && (
                                            <img 
                                                src={participant.avatar} 
                                                alt={participant.name}
                                                className="w-4 h-4 rounded-full"
                                            />
                                        )}
                                        {participant.name}
                                        <button
                                            type="button"
                                            onClick={() => handleParticipantRemove(participant.id)}
                                            className="ml-1 hover:text-destructive"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Conversation Details */}
                    {(formData.type === 'group' || formData.type === 'channel') && (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name" className="text-sm font-medium">
                                    {formData.type === 'group' ? 'Group' : 'Channel'} Name *
                                </Label>
                                <Input
                                    id="name"
                                    placeholder={`Enter ${formData.type} name...`}
                                    value={formData.name}
                                    onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                    maxLength={255}
                                />
                            </div>
                            
                            <div className="space-y-2">
                                <Label htmlFor="description" className="text-sm font-medium">Description</Label>
                                <Textarea
                                    id="description"
                                    placeholder={`Describe what this ${formData.type} is for...`}
                                    value={formData.description}
                                    onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                                    maxLength={formData.type === 'channel' ? 2000 : 1000}
                                    rows={3}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="avatarUrl" className="text-sm font-medium">Avatar URL</Label>
                                <Input
                                    id="avatarUrl"
                                    placeholder="https://example.com/avatar.png"
                                    value={formData.avatarUrl}
                                    onChange={(e) => setFormData(prev => ({ ...prev, avatarUrl: e.target.value }))}
                                />
                            </div>
                        </div>
                    )}

                    {/* Security Settings */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base flex items-center gap-2">
                                <Shield className="h-4 w-4" />
                                Security Settings
                            </CardTitle>
                            <CardDescription>
                                Configure encryption and security options
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-1">
                                    <Label className="text-sm font-medium">Quantum-Resistant Encryption</Label>
                                    <p className="text-xs text-muted-foreground">
                                        Enable ML-KEM post-quantum cryptography
                                    </p>
                                </div>
                                <Switch
                                    checked={formData.enableQuantum}
                                    onCheckedChange={(checked) => setFormData(prev => ({ ...prev, enableQuantum: checked }))}
                                />
                            </div>
                            
                            {formData.enableQuantum && (
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Key Strength</Label>
                                    <Select 
                                        value={formData.keyStrength.toString()} 
                                        onValueChange={(value) => setFormData(prev => ({ ...prev, keyStrength: parseInt(value) as 512 | 768 | 1024 }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="512">ML-KEM-512 (128-bit security)</SelectItem>
                                            <SelectItem value="768">ML-KEM-768 (192-bit security) - Recommended</SelectItem>
                                            <SelectItem value="1024">ML-KEM-1024 (256-bit security)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Type-specific Settings */}
                    {formData.type === 'channel' && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Settings className="h-4 w-4" />
                                    Channel Settings
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <Label className="text-sm font-medium">Allow Member Invites</Label>
                                        <p className="text-xs text-muted-foreground">
                                            Let members invite others to this channel
                                        </p>
                                    </div>
                                    <Switch
                                        checked={formData.settings.allowMemberInvites}
                                        onCheckedChange={(checked) => setFormData(prev => ({
                                            ...prev,
                                            settings: { ...prev.settings, allowMemberInvites: checked }
                                        }))}
                                    />
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <Label className="text-sm font-medium">Moderated</Label>
                                        <p className="text-xs text-muted-foreground">
                                            Require moderator approval for messages
                                        </p>
                                    </div>
                                    <Switch
                                        checked={formData.settings.moderated}
                                        onCheckedChange={(checked) => setFormData(prev => ({
                                            ...prev,
                                            settings: { ...prev.settings, moderated: checked }
                                        }))}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {formData.type === 'group' && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Settings className="h-4 w-4" />
                                    Group Settings
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <Label className="text-sm font-medium">Everyone Can Add Members</Label>
                                        <p className="text-xs text-muted-foreground">
                                            Allow all members to invite others
                                        </p>
                                    </div>
                                    <Switch
                                        checked={formData.settings.everyoneCanAddMembers}
                                        onCheckedChange={(checked) => setFormData(prev => ({
                                            ...prev,
                                            settings: { ...prev.settings, everyoneCanAddMembers: checked }
                                        }))}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)} disabled={loading}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={loading || formData.participants.length === 0}>
                        {loading ? (
                            <>
                                <Zap className="h-4 w-4 mr-2 animate-spin" />
                                Creating...
                            </>
                        ) : (
                            <>
                                Create {formData.type === 'direct' ? 'Direct Message' : formData.type === 'group' ? 'Group' : 'Channel'}
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}