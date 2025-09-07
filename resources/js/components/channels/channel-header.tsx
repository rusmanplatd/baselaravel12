import React, { useState } from 'react';
import { 
    Hash, 
    Users, 
    Eye, 
    Verified, 
    Bell, 
    BellOff, 
    Settings, 
    Share, 
    MoreVertical,
    UserPlus,
    UserMinus,
    Copy,
    ExternalLink
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuSeparator, 
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';

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
    avatar_url?: string;
    is_subscribed: boolean;
    subscription_status?: string;
    creator: {
        id: string;
        name: string;
    };
    category_info?: {
        name: string;
        icon: string;
        color: string;
    };
}

interface ChannelHeaderProps {
    channel: Channel;
    isAdmin?: boolean;
    onSubscriptionChange: (isSubscribed: boolean) => void;
    onNotificationToggle: () => void;
    onOpenSettings?: () => void;
    className?: string;
}

export default function ChannelHeader({ 
    channel, 
    isAdmin = false,
    onSubscriptionChange, 
    onNotificationToggle, 
    onOpenSettings,
    className 
}: ChannelHeaderProps) {
    const [isLoading, setIsLoading] = useState(false);

    const formatNumber = (num: number): string => {
        if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
        return num.toString();
    };

    const handleSubscriptionToggle = async () => {
        setIsLoading(true);
        try {
            await onSubscriptionChange(!channel.is_subscribed);
        } finally {
            setIsLoading(false);
        }
    };

    const handleShare = async () => {
        const channelUrl = `${window.location.origin}/channels/${channel.username}`;
        
        if (navigator.share) {
            try {
                await navigator.share({
                    title: channel.name,
                    text: channel.description || `Check out ${channel.name} channel`,
                    url: channelUrl,
                });
            } catch (err) {
                // User cancelled share
            }
        } else {
            // Fallback to clipboard
            try {
                await navigator.clipboard.writeText(channelUrl);
                toast.success('Channel link copied to clipboard');
            } catch (err) {
                toast.error('Failed to copy link');
            }
        }
    };

    const handleCopyLink = async () => {
        const channelUrl = `${window.location.origin}/channels/${channel.username}`;
        try {
            await navigator.clipboard.writeText(channelUrl);
            toast.success('Channel link copied to clipboard');
        } catch (err) {
            toast.error('Failed to copy link');
        }
    };

    const handleOpenInNew = () => {
        const channelUrl = `${window.location.origin}/channels/${channel.username}`;
        window.open(channelUrl, '_blank');
    };

    return (
        <div className={`bg-white border-b border-gray-200 ${className}`}>
            <div className="px-6 py-4">
                {/* Main Channel Info */}
                <div className="flex items-start justify-between">
                    <div className="flex items-start space-x-4">
                        {/* Avatar */}
                        <Avatar className="h-16 w-16">
                            <AvatarImage src={channel.avatar_url} alt={channel.name} />
                            <AvatarFallback className="bg-blue-100 text-blue-600 text-lg font-semibold">
                                <Hash className="h-8 w-8" />
                            </AvatarFallback>
                        </Avatar>

                        {/* Channel Details */}
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center space-x-2 mb-1">
                                <h1 className="text-2xl font-bold text-gray-900 truncate">
                                    {channel.name}
                                </h1>
                                {channel.is_verified && (
                                    <Verified className="h-6 w-6 text-blue-500 flex-shrink-0" />
                                )}
                            </div>
                            
                            <p className="text-gray-600 mb-1">@{channel.username}</p>
                            
                            {channel.description && (
                                <p className="text-gray-700 mb-3 max-w-2xl">
                                    {channel.description}
                                </p>
                            )}
                            
                            {/* Stats and Badges */}
                            <div className="flex items-center flex-wrap gap-4">
                                <div className="flex items-center space-x-4 text-sm text-gray-600">
                                    <div className="flex items-center space-x-1">
                                        <Users className="h-4 w-4" />
                                        <span>{formatNumber(channel.subscriber_count)} subscribers</span>
                                    </div>
                                    <div className="flex items-center space-x-1">
                                        <Eye className="h-4 w-4" />
                                        <span>{formatNumber(channel.view_count)} views</span>
                                    </div>
                                </div>
                                
                                <div className="flex items-center space-x-2">
                                    {channel.category_info && (
                                        <Badge 
                                            variant="secondary"
                                            className="text-xs"
                                            style={{ backgroundColor: `${channel.category_info.color}20`, color: channel.category_info.color }}
                                        >
                                            <span className="mr-1">{channel.category_info.icon}</span>
                                            {channel.category_info.name}
                                        </Badge>
                                    )}
                                    
                                    <Badge variant="outline" className="text-xs">
                                        {channel.privacy === 'public' ? 'Public' : 'Private'}
                                    </Badge>
                                    
                                    {channel.is_broadcast && (
                                        <Badge variant="secondary" className="text-xs">
                                            Broadcast Only
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center space-x-2 flex-shrink-0">
                        {/* Subscription Button */}
                        <Button
                            variant={channel.is_subscribed ? "outline" : "default"}
                            onClick={handleSubscriptionToggle}
                            disabled={isLoading}
                            className="flex items-center space-x-2"
                        >
                            {channel.is_subscribed ? (
                                <>
                                    <UserMinus className="h-4 w-4" />
                                    <span>Unsubscribe</span>
                                </>
                            ) : (
                                <>
                                    <UserPlus className="h-4 w-4" />
                                    <span>Subscribe</span>
                                </>
                            )}
                        </Button>

                        {/* Notification Toggle (only for subscribers) */}
                        {channel.is_subscribed && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onNotificationToggle}
                                className="px-3"
                                title={channel.subscription_status === 'muted' ? 'Enable notifications' : 'Disable notifications'}
                            >
                                {channel.subscription_status === 'muted' ? (
                                    <BellOff className="h-4 w-4" />
                                ) : (
                                    <Bell className="h-4 w-4" />
                                )}
                            </Button>
                        )}

                        {/* More Actions Menu */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="px-3">
                                    <MoreVertical className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={handleShare}>
                                    <Share className="h-4 w-4 mr-2" />
                                    Share Channel
                                </DropdownMenuItem>
                                
                                <DropdownMenuItem onClick={handleCopyLink}>
                                    <Copy className="h-4 w-4 mr-2" />
                                    Copy Link
                                </DropdownMenuItem>
                                
                                <DropdownMenuItem onClick={handleOpenInNew}>
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    Open in New Tab
                                </DropdownMenuItem>

                                {isAdmin && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem onClick={onOpenSettings}>
                                            <Settings className="h-4 w-4 mr-2" />
                                            Channel Settings
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Channel Creator Info */}
                <div className="mt-4 text-sm text-gray-500">
                    Created by {channel.creator.name}
                </div>
            </div>
        </div>
    );
}