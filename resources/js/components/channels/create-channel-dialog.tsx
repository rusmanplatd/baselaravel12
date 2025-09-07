import React, { useState, useEffect } from 'react';
import { Plus, Hash, Users, Globe, Lock, Shield } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useChannels } from '@/hooks/useChannels';

interface CreateChannelDialogProps {
    trigger?: React.ReactNode;
    onChannelCreated?: (channel: any) => void;
}

export default function CreateChannelDialog({ trigger, onChannelCreated }: CreateChannelDialogProps) {
    const [open, setOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        username: '',
        description: '',
        category: '',
        privacy: 'public',
        is_broadcast: false,
        allow_anonymous_posts: false,
        show_subscriber_count: true,
        require_join_approval: false,
        welcome_message: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    
    const { categories, loadCategories, createChannel, error } = useChannels();

    useEffect(() => {
        if (open && categories.length === 0) {
            loadCategories();
        }
    }, [open, categories.length, loadCategories]);

    const handleInputChange = (field: string, value: any) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear field error when user starts typing
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: '' }));
        }
        
        // Auto-generate username from name
        if (field === 'name' && !formData.username) {
            const username = value.toLowerCase()
                .replace(/[^a-z0-9\s]/g, '')
                .replace(/\s+/g, '_')
                .substring(0, 30);
            setFormData(prev => ({ ...prev, username }));
        }
    };

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};
        
        if (!formData.name.trim()) {
            newErrors.name = 'Channel name is required';
        } else if (formData.name.length < 3) {
            newErrors.name = 'Channel name must be at least 3 characters';
        }
        
        if (!formData.username.trim()) {
            newErrors.username = 'Username is required';
        } else if (!/^[a-z0-9_]+$/.test(formData.username)) {
            newErrors.username = 'Username can only contain lowercase letters, numbers, and underscores';
        } else if (formData.username.length < 3) {
            newErrors.username = 'Username must be at least 3 characters';
        }
        
        if (formData.description && formData.description.length > 1000) {
            newErrors.description = 'Description cannot exceed 1000 characters';
        }
        
        if (formData.welcome_message && formData.welcome_message.length > 500) {
            newErrors.welcome_message = 'Welcome message cannot exceed 500 characters';
        }
        
        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        setIsSubmitting(true);
        
        try {
            const channel = await createChannel(formData);
            
            if (channel) {
                setOpen(false);
                setFormData({
                    name: '',
                    username: '',
                    description: '',
                    category: '',
                    privacy: 'public',
                    is_broadcast: false,
                    allow_anonymous_posts: false,
                    show_subscriber_count: true,
                    require_join_approval: false,
                    welcome_message: '',
                });
                setErrors({});
                
                if (onChannelCreated) {
                    onChannelCreated(channel);
                }
            }
        } catch (err) {
            console.error('Failed to create channel:', err);
        } finally {
            setIsSubmitting(false);
        }
    };

    const privacyOptions = [
        {
            value: 'public',
            label: 'Public',
            description: 'Anyone can find and join this channel',
            icon: Globe,
        },
        {
            value: 'private',
            label: 'Private',
            description: 'Only invited users can join',
            icon: Lock,
        },
        {
            value: 'invite_only',
            label: 'Invite Only',
            description: 'Users can find the channel but need invitation to join',
            icon: Shield,
        },
    ];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger || (
                    <Button>
                        <Plus className="h-4 w-4 mr-2" />
                        Create Channel
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Hash className="h-5 w-5" />
                        Create New Channel
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="name">Channel Name *</Label>
                            <Input
                                id="name"
                                value={formData.name}
                                onChange={(e) => handleInputChange('name', e.target.value)}
                                placeholder="My Awesome Channel"
                                className={errors.name ? 'border-red-500' : ''}
                            />
                            {errors.name && (
                                <p className="text-sm text-red-600 mt-1">{errors.name}</p>
                            )}
                        </div>
                        
                        <div>
                            <Label htmlFor="username">Username *</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">@</span>
                                <Input
                                    id="username"
                                    value={formData.username}
                                    onChange={(e) => handleInputChange('username', e.target.value)}
                                    placeholder="my_awesome_channel"
                                    className={`pl-8 ${errors.username ? 'border-red-500' : ''}`}
                                />
                            </div>
                            {errors.username && (
                                <p className="text-sm text-red-600 mt-1">{errors.username}</p>
                            )}
                        </div>
                        
                        <div>
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => handleInputChange('description', e.target.value)}
                                placeholder="Tell people what your channel is about..."
                                rows={3}
                                className={errors.description ? 'border-red-500' : ''}
                            />
                            {errors.description && (
                                <p className="text-sm text-red-600 mt-1">{errors.description}</p>
                            )}
                            <p className="text-sm text-gray-500 mt-1">
                                {formData.description.length}/1000 characters
                            </p>
                        </div>
                        
                        <div>
                            <Label htmlFor="category">Category</Label>
                            <Select value={formData.category} onValueChange={(value) => handleInputChange('category', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">No category</SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem key={category.slug} value={category.slug}>
                                            <span className="flex items-center gap-2">
                                                <span>{category.icon}</span>
                                                {category.name}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Privacy Settings */}
                    <div className="space-y-4">
                        <Label>Privacy & Access</Label>
                        <RadioGroup
                            value={formData.privacy}
                            onValueChange={(value) => handleInputChange('privacy', value)}
                        >
                            {privacyOptions.map((option) => (
                                <div key={option.value} className="flex items-start space-x-3 p-3 rounded-lg border">
                                    <RadioGroupItem value={option.value} id={option.value} className="mt-1" />
                                    <div className="flex items-start space-x-3 flex-1">
                                        <option.icon className="h-5 w-5 text-gray-400 mt-0.5" />
                                        <div>
                                            <Label htmlFor={option.value} className="font-medium cursor-pointer">
                                                {option.label}
                                            </Label>
                                            <p className="text-sm text-gray-500 mt-1">
                                                {option.description}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </RadioGroup>
                    </div>

                    {/* Channel Settings */}
                    <div className="space-y-4">
                        <Label>Channel Settings</Label>
                        
                        <div className="flex items-center justify-between p-3 border rounded-lg">
                            <div>
                                <Label htmlFor="is_broadcast" className="font-medium">Broadcast Channel</Label>
                                <p className="text-sm text-gray-500">Only admins can send messages</p>
                            </div>
                            <Switch
                                id="is_broadcast"
                                checked={formData.is_broadcast}
                                onCheckedChange={(checked) => handleInputChange('is_broadcast', checked)}
                            />
                        </div>
                        
                        <div className="flex items-center justify-between p-3 border rounded-lg">
                            <div>
                                <Label htmlFor="show_subscriber_count" className="font-medium">Show Subscriber Count</Label>
                                <p className="text-sm text-gray-500">Display the number of subscribers publicly</p>
                            </div>
                            <Switch
                                id="show_subscriber_count"
                                checked={formData.show_subscriber_count}
                                onCheckedChange={(checked) => handleInputChange('show_subscriber_count', checked)}
                            />
                        </div>
                        
                        {formData.privacy === 'public' && (
                            <div className="flex items-center justify-between p-3 border rounded-lg">
                                <div>
                                    <Label htmlFor="require_join_approval" className="font-medium">Require Join Approval</Label>
                                    <p className="text-sm text-gray-500">Manually approve new subscribers</p>
                                </div>
                                <Switch
                                    id="require_join_approval"
                                    checked={formData.require_join_approval}
                                    onCheckedChange={(checked) => handleInputChange('require_join_approval', checked)}
                                />
                            </div>
                        )}
                        
                        {!formData.is_broadcast && (
                            <div className="flex items-center justify-between p-3 border rounded-lg">
                                <div>
                                    <Label htmlFor="allow_anonymous_posts" className="font-medium">Allow Anonymous Posts</Label>
                                    <p className="text-sm text-gray-500">Let users post without showing their identity</p>
                                </div>
                                <Switch
                                    id="allow_anonymous_posts"
                                    checked={formData.allow_anonymous_posts}
                                    onCheckedChange={(checked) => handleInputChange('allow_anonymous_posts', checked)}
                                />
                            </div>
                        )}
                    </div>

                    {/* Welcome Message */}
                    <div>
                        <Label htmlFor="welcome_message">Welcome Message</Label>
                        <Textarea
                            id="welcome_message"
                            value={formData.welcome_message}
                            onChange={(e) => handleInputChange('welcome_message', e.target.value)}
                            placeholder="Welcome to our channel! Here's what you can expect..."
                            rows={3}
                            className={errors.welcome_message ? 'border-red-500' : ''}
                        />
                        {errors.welcome_message && (
                            <p className="text-sm text-red-600 mt-1">{errors.welcome_message}</p>
                        )}
                        <p className="text-sm text-gray-500 mt-1">
                            {formData.welcome_message.length}/500 characters
                        </p>
                    </div>

                    {/* Error Display */}
                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {/* Submit Button */}
                    <div className="flex justify-end space-x-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Create Channel'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}