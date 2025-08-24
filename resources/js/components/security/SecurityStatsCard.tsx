import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { type SessionStats } from '@/types';
import { Activity, Shield, BarChart3, MapPin, Smartphone, Monitor, Tablet } from 'lucide-react';

interface SecurityStatsCardProps {
    stats: SessionStats;
}

export function SecurityStatsCard({ stats }: SecurityStatsCardProps) {
    const getDeviceIcon = (deviceType: string) => {
        switch (deviceType) {
            case 'mobile':
                return <Smartphone className="h-3 w-3" />;
            case 'tablet':
                return <Tablet className="h-3 w-3" />;
            default:
                return <Monitor className="h-3 w-3" />;
        }
    };

    const getDeviceTypeColor = (deviceType: string) => {
        switch (deviceType) {
            case 'mobile':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
            case 'tablet':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
        }
    };

    const trustedDevicePercentage = stats.active_sessions > 0 
        ? Math.round((stats.trusted_device_sessions / stats.active_sessions) * 100)
        : 0;

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* Active Sessions */}
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Active Sessions</p>
                            <p className="text-2xl font-bold text-green-600">{stats.active_sessions}</p>
                        </div>
                        <Activity className="h-8 w-8 text-green-600" />
                    </div>
                </CardContent>
            </Card>

            {/* Trusted Device Sessions */}
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Trusted Devices</p>
                            <div className="flex items-center gap-2">
                                <p className="text-2xl font-bold text-blue-600">{stats.trusted_device_sessions}</p>
                                {stats.active_sessions > 0 && (
                                    <Badge 
                                        variant="secondary" 
                                        className="text-xs"
                                    >
                                        {trustedDevicePercentage}%
                                    </Badge>
                                )}
                            </div>
                        </div>
                        <Shield className="h-8 w-8 text-blue-600" />
                    </div>
                </CardContent>
            </Card>

            {/* Recent Logins */}
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Recent Logins (7d)</p>
                            <p className="text-2xl font-bold">{stats.recent_logins}</p>
                        </div>
                        <BarChart3 className="h-8 w-8 text-muted-foreground" />
                    </div>
                </CardContent>
            </Card>

            {/* Unique IPs */}
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">Unique IPs</p>
                            <p className="text-2xl font-bold">{stats.unique_ips}</p>
                            {stats.unique_ips > 3 && (
                                <p className="text-xs text-orange-600 mt-1">High variance detected</p>
                            )}
                        </div>
                        <MapPin className="h-8 w-8 text-muted-foreground" />
                    </div>
                </CardContent>
            </Card>

            {/* Device Types Distribution - spans full width on larger screens */}
            {Object.keys(stats.device_types).length > 0 && (
                <Card className="md:col-span-2 lg:col-span-4">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-lg">Device Distribution</CardTitle>
                        <CardDescription>
                            Active sessions by device type
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-3">
                            {Object.entries(stats.device_types).map(([type, count]) => (
                                <div key={type} className="flex items-center gap-2">
                                    <Badge 
                                        variant="secondary" 
                                        className={`flex items-center gap-1 ${getDeviceTypeColor(type)}`}
                                    >
                                        {getDeviceIcon(type)}
                                        <span className="capitalize">{type}</span>
                                        <span className="ml-1 font-bold">{count}</span>
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}