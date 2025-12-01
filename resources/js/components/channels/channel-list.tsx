import React, { useState, useEffect } from 'react';
import { Hash, Users, Verified, MoreVertical, Settings, Bell, BellOff, UserMinus } from 'lucide-react';
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
import { useChannels } from '@/hooks/useChannels';

interface Channel {
    id: string;
    name: string;
    username: string;
    description: string;
    is_verified: boolean;
    subscriber_count: number;
    privacy: string;
    avatar_url?: string;
    is_subscribed: boolean;
    subscription_status?: string;
    unread_count?: number;
    last_message_at?: string;
    category_info?: {
        name: string;
        icon: string;
        color: string;
    };
}

interface ChannelListProps {
    selectedChannelId?: string;
    onChannelSelect: (channel: Channel) => void;
    onChannelAction: (action: string, channelId: string) => void;
    className?: string;
}

export default function ChannelList({ 
    selectedChannelId, 
    onChannelSelect, 
    onChannelAction, 
    className 
}: ChannelListProps) {
    const [filter, setFilter] = useState<'all' | 'subscribed' | 'owned'>('subscribed');
    
    const {
        channels,
        isLoading,
        error,
        loadUserChannels,
        unsubscribeFromChannel,
        updateNotificationSettings,
    } = useChannels();

    useEffect(() => {
        loadUserChannels({
            subscribed: filter === 'subscribed',
            owned: filter === 'owned',
        });
    }, [filter]);

    const handleUnsubscribe = async (channelId: string, event: React.MouseEvent) => {
        event.stopPropagation();
        try {
            await unsubscribeFromChannel(channelId);
            // Refresh the channel list
            loadUserChannels({ subscribed: filter === 'subscribed' });
        } catch (error) {
            console.error('Failed to unsubscribe:', error);
        }
    };

    const handleToggleNotifications = async (channelId: string, event: React.MouseEvent) => {
        event.stopPropagation();
        try {
            await updateNotificationSettings(channelId);
        } catch (error) {
            console.error('Failed to update notifications:', error);
        }
    };

    const formatNumber = (num: number): string => {
        if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
        return num.toString();
    };

    const formatLastActivity = (dateStr?: string): string => {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffHours / 24);

        if (diffHours < 1) return 'now';
        if (diffHours < 24) return `${diffHours}h`;
        if (diffDays < 7) return `${diffDays}d`;
        return date.toLocaleDateString();
    };

    if (isLoading) {
        return (
            <div className={`space-y-2 ${className}`}>
                {[...Array(5)].map((_, i) => (
                    <div key={i} className="flex items-center space-x-3 p-3 animate-pulse">
                        <div className="w-10 h-10 bg-gray-200 rounded-full" />
                        <div className="flex-1">
                            <div className="w-32 h-4 bg-gray-200 rounded mb-1" />
                            <div className="w-24 h-3 bg-gray-200 rounded" />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <div className={`p-4 text-center text-red-600 ${className}`}>
                <p>Failed to load channels</p>
                <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={() => loadUserChannels({ subscribed: filter === 'subscribed' })}
                    className="mt-2"
                >
                    Try again
                </Button>
            </div>
        );
    }

    return (
        <div className={`space-y-1 ${className}`}>
            {/* Filter Tabs */}
            <div className="flex space-x-1 p-2 bg-gray-50 rounded-lg mb-4">
                <Button
                    variant={filter === 'subscribed' ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => setFilter('subscribed')}
                    className="flex-1 text-xs"
                >
                    Subscribed
                </Button>
                <Button
                    variant={filter === 'all' ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => setFilter('all')}
                    className="flex-1 text-xs"
                >
                    Discover
                </Button>
            </div>

            {/* Channel List */}
            {channels.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                    <Hash className="mx-auto h-8 w-8 mb-2" />
                    <p className="text-sm">
                        {filter === 'subscribed' ? 'No subscribed channels' : 'No channels found'}
                    </p>
                </div>
            ) : (
                <div className="space-y-1">
                    {channels.map((channel: Channel) => (
                        <div
                            key={channel.id}
                            className={`
                                flex items-center space-x-3 p-3 rounded-lg cursor-pointer transition-colors group
                                ${selectedChannelId === channel.id 
                                    ? 'bg-blue-50 border border-blue-200' 
                                    : 'hover:bg-gray-50'
                                }
                            `}
                            onClick={() => onChannelSelect(channel)}
                        >
                            {/* Channel Avatar */}
                            <div className="relative">
                                <Avatar className="h-10 w-10">
                                    <AvatarImage src={channel.avatar_url} alt={channel.name} />
                                    <AvatarFallback className="bg-blue-100 text-blue-600">
                                        <Hash className="h-5 w-5" />
                                    </AvatarFallback>
                                </Avatar>
                                {channel.category_info && (
                                    <div 
                                        className="absolute -bottom-1 -right-1 w-4 h-4 rounded-full flex items-center justify-center text-xs"
                                        style={{ backgroundColor: channel.category_info.color }}
                                    >
                                        {channel.category_info.icon}
                                    </div>
                                )}
                            </div>

                            {/* Channel Info */}
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-1 min-w-0">
                                        <span className="font-medium text-sm truncate">
                                            {channel.name}
                                        </span>
                                        {channel.is_verified && (
                                            <Verified className="h-3 w-3 text-blue-500 flex-shrink-0" />
                                        )}
                                    </div>
                                    
                                    <div className="flex items-center space-x-1">
                                        {channel.unread_count && channel.unread_count > 0 && (
                                            <Badge variant="default" className="text-xs min-w-[20px] h-5 flex items-center justify-center">
                                                {channel.unread_count > 99 ? '99+' : channel.unread_count}
                                            </Badge>
                                        )}
                                        
                                        {channel.last_message_at && (
                                            <span className="text-xs text-gray-500">
                                                {formatLastActivity(channel.last_message_at)}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                
                                <div className="flex items-center justify-between mt-1">
                                    <div className="flex items-center space-x-2 min-w-0">
                                        <span className="text-xs text-gray-500 truncate">
                                            @{channel.username}
                                        </span>
                                        <div className="flex items-center space-x-1">
                                            <Users className="h-3 w-3 text-gray-400" />
                                            <span className="text-xs text-gray-500">
                                                {formatNumber(channel.subscriber_count)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Actions Menu */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <MoreVertical className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuItem
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onChannelAction('view', channel.id);
                                        }}
                                    >
                                        <Hash className="h-4 w-4 mr-2" />
                                        View Channel
                                    </DropdownMenuItem>
                                    
                                    <DropdownMenuItem
                                        onClick={(e) => handleToggleNotifications(channel.id, e)}
                                    >
                                        {channel.subscription_status === 'muted' ? (
                                            <Bell className="h-4 w-4 mr-2" />
                                        ) : (
                                            <BellOff className="h-4 w-4 mr-2" />
                                        )}
                                        {channel.subscription_status === 'muted' ? 'Unmute' : 'Mute'}
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator />
                                    
                                    <DropdownMenuItem
                                        onClick={(e) => handleUnsubscribe(channel.id, e)}
                                        className="text-red-600 focus:text-red-600"
                                    >
                                        <UserMinus className="h-4 w-4 mr-2" />
                                        Unsubscribe
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}