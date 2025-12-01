import React, { useState, useEffect } from 'react';
import { Search, Filter, Verified, Users, Eye, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useChannels } from '@/hooks/useChannels';

interface Channel {
    id: string;
    name: string;
    username: string;
    description: string;
    category: string;
    is_verified: boolean;
    subscriber_count: number;
    view_count: number;
    privacy: string;
    avatar_url?: string;
    created_at: string;
    is_subscribed: boolean;
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

interface ChannelDiscoveryProps {
    onChannelSelect: (channel: Channel) => void;
    className?: string;
}

export default function ChannelDiscovery({ onChannelSelect, className }: ChannelDiscoveryProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [sortBy, setSortBy] = useState('popular');
    const [showNewChannels, setShowNewChannels] = useState(false);
    
    const {
        channels,
        categories,
        isLoading,
        error,
        discoverChannels,
        subscribeToChannel,
        unsubscribeFromChannel,
    } = useChannels();

    useEffect(() => {
        discoverChannels({
            search: searchQuery || undefined,
            category: selectedCategory !== 'all' ? selectedCategory : undefined,
            popular: sortBy === 'popular',
            new: showNewChannels,
        });
    }, [searchQuery, selectedCategory, sortBy, showNewChannels]);

    const handleSubscribe = async (channelId: string, isSubscribed: boolean) => {
        try {
            if (isSubscribed) {
                await unsubscribeFromChannel(channelId);
            } else {
                await subscribeToChannel(channelId);
            }
        } catch (error) {
            console.error('Failed to update subscription:', error);
        }
    };

    const formatNumber = (num: number): string => {
        if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
        return num.toString();
    };

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Search and Filters */}
            <div className="space-y-4">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                    <Input
                        placeholder="Search channels..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-10"
                    />
                </div>
                
                <div className="flex flex-wrap gap-3">
                    <Select value={selectedCategory} onValueChange={setSelectedCategory}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
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
                    
                    <Select value={sortBy} onValueChange={setSortBy}>
                        <SelectTrigger className="w-32">
                            <SelectValue placeholder="Sort by" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="popular">Popular</SelectItem>
                            <SelectItem value="recent">Recent</SelectItem>
                            <SelectItem value="name">Name</SelectItem>
                        </SelectContent>
                    </Select>
                    
                    <Button
                        variant={showNewChannels ? "default" : "outline"}
                        size="sm"
                        onClick={() => setShowNewChannels(!showNewChannels)}
                    >
                        <Calendar className="h-4 w-4 mr-2" />
                        New
                    </Button>
                </div>
            </div>

            {/* Loading State */}
            {isLoading && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {[...Array(6)].map((_, i) => (
                        <Card key={i} className="animate-pulse">
                            <CardHeader className="pb-2">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center space-x-2">
                                        <div className="w-10 h-10 bg-gray-200 rounded-full" />
                                        <div className="space-y-1">
                                            <div className="w-24 h-4 bg-gray-200 rounded" />
                                            <div className="w-16 h-3 bg-gray-200 rounded" />
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div className="w-full h-3 bg-gray-200 rounded" />
                                    <div className="w-3/4 h-3 bg-gray-200 rounded" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Error State */}
            {error && (
                <Card className="border-red-200 bg-red-50">
                    <CardContent className="pt-6">
                        <p className="text-red-600">Failed to load channels: {error}</p>
                    </CardContent>
                </Card>
            )}

            {/* Channels Grid */}
            {!isLoading && !error && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {channels.map((channel: Channel) => (
                        <Card key={channel.id} className="hover:shadow-md transition-shadow cursor-pointer group">
                            <CardHeader className="pb-2">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center space-x-3">
                                        <Avatar className="h-10 w-10">
                                            <AvatarImage src={channel.avatar_url} alt={channel.name} />
                                            <AvatarFallback>
                                                {channel.name.slice(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-base font-semibold flex items-center gap-1">
                                                    {channel.name}
                                                    {channel.is_verified && (
                                                        <Verified className="h-4 w-4 text-blue-500 fill-current" />
                                                    )}
                                                </CardTitle>
                                            </div>
                                            <p className="text-sm text-gray-500">@{channel.username}</p>
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant={channel.is_subscribed ? "outline" : "default"}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            handleSubscribe(channel.id, channel.is_subscribed);
                                        }}
                                        className="opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        {channel.is_subscribed ? 'Unsubscribe' : 'Subscribe'}
                                    </Button>
                                </div>
                            </CardHeader>
                            
                            <CardContent onClick={() => onChannelSelect(channel)}>
                                {channel.description && (
                                    <p className="text-sm text-gray-600 mb-3 line-clamp-2">
                                        {channel.description}
                                    </p>
                                )}
                                
                                <div className="flex items-center justify-between text-sm text-gray-500 mb-3">
                                    <div className="flex items-center gap-4">
                                        <div className="flex items-center gap-1">
                                            <Users className="h-4 w-4" />
                                            <span>{formatNumber(channel.subscriber_count)}</span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Eye className="h-4 w-4" />
                                            <span>{formatNumber(channel.view_count)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="flex items-center justify-between">
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
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Empty State */}
            {!isLoading && !error && channels.length === 0 && (
                <Card className="text-center py-8">
                    <CardContent>
                        <div className="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <Search className="h-6 w-6 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No channels found</h3>
                        <p className="text-gray-500 mb-4">Try adjusting your search filters to find channels.</p>
                        <Button 
                            variant="outline" 
                            onClick={() => {
                                setSearchQuery('');
                                setSelectedCategory('all');
                                setShowNewChannels(false);
                            }}
                        >
                            Clear filters
                        </Button>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}