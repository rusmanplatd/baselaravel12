import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
  Users, 
  MessageSquare, 
  Shield, 
  Webhook,
  Video,
  Settings,
  Activity,
  AlertTriangle,
  CheckCircle,
  Clock,
  TrendingUp,
  TrendingDown,
  Database,
  Server,
  Lock,
  Unlock,
  RefreshCw
} from 'lucide-react';
import SecurityDashboard from '@/components/security/SecurityDashboard';
import WebhookManager from '@/components/webhooks/WebhookManager';
import { toast } from 'sonner';

interface SystemStats {
  total_users: number;
  active_users_24h: number;
  total_conversations: number;
  active_conversations_24h: number;
  total_messages: number;
  messages_24h: number;
  encrypted_messages_percentage: number;
  total_organizations: number;
  active_webhooks: number;
  failed_webhook_deliveries_24h: number;
  video_calls_24h: number;
  active_video_calls: number;
  system_health_score: number;
  storage_used_gb: number;
  storage_limit_gb: number;
}

interface ServiceStatus {
  service: string;
  status: 'healthy' | 'degraded' | 'down';
  response_time_ms: number;
  last_checked: string;
  uptime_percentage: number;
  error_message?: string;
}

interface RecentActivity {
  id: string;
  type: 'user_registration' | 'conversation_created' | 'security_event' | 'webhook_created' | 'video_call_started';
  title: string;
  description: string;
  timestamp: string;
  severity: 'info' | 'warning' | 'error';
  user?: {
    id: string;
    name: string;
  };
  organization?: {
    id: string;
    name: string;
  };
}

interface AdminDashboardProps {
  className?: string;
}

const AdminDashboard: React.FC<AdminDashboardProps> = ({ className }) => {
  const [stats, setStats] = useState<SystemStats | null>(null);
  const [serviceStatuses, setServiceStatuses] = useState<ServiceStatus[]>([]);
  const [recentActivity, setRecentActivity] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    fetchDashboardData();
    const interval = setInterval(fetchDashboardData, 30000); // Refresh every 30 seconds
    return () => clearInterval(interval);
  }, []);

  const fetchDashboardData = async () => {
    try {
      setRefreshing(true);
      
      const [statsResponse, servicesResponse, activityResponse] = await Promise.all([
        fetch('/api/v1/admin/dashboard/stats'),
        fetch('/api/v1/admin/dashboard/services'),
        fetch('/api/v1/admin/dashboard/activity')
      ]);

      if (statsResponse.ok) {
        const statsData = await statsResponse.json();
        setStats(statsData.stats);
      }

      if (servicesResponse.ok) {
        const servicesData = await servicesResponse.json();
        setServiceStatuses(servicesData.services);
      }

      if (activityResponse.ok) {
        const activityData = await activityResponse.json();
        setRecentActivity(activityData.activities);
      }

    } catch (error) {
      console.error('Failed to fetch dashboard data:', error);
      toast.error('Failed to load dashboard data');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'healthy': return 'text-green-500';
      case 'degraded': return 'text-yellow-500';
      case 'down': return 'text-red-500';
      default: return 'text-gray-500';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'healthy': return <CheckCircle className="h-4 w-4" />;
      case 'degraded': return <AlertTriangle className="h-4 w-4" />;
      case 'down': return <AlertTriangle className="h-4 w-4" />;
      default: return <Clock className="h-4 w-4" />;
    }
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'user_registration': return <Users className="h-4 w-4" />;
      case 'conversation_created': return <MessageSquare className="h-4 w-4" />;
      case 'security_event': return <Shield className="h-4 w-4" />;
      case 'webhook_created': return <Webhook className="h-4 w-4" />;
      case 'video_call_started': return <Video className="h-4 w-4" />;
      default: return <Activity className="h-4 w-4" />;
    }
  };

  const getActivitySeverityColor = (severity: string) => {
    switch (severity) {
      case 'info': return 'border-blue-200 bg-blue-50';
      case 'warning': return 'border-yellow-200 bg-yellow-50';
      case 'error': return 'border-red-200 bg-red-50';
      default: return 'border-gray-200 bg-gray-50';
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center h-96 ${className}`}>
        <div className="flex items-center gap-2">
          <RefreshCw className="h-6 w-6 animate-spin" />
          <span>Loading dashboard...</span>
        </div>
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Admin Dashboard</h1>
          <p className="text-muted-foreground">
            Monitor and manage your application's performance and security
          </p>
        </div>
        <Button 
          onClick={fetchDashboardData} 
          disabled={refreshing}
          variant="outline"
          size="sm"
        >
          <RefreshCw className={`h-4 w-4 mr-2 ${refreshing ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>

      {/* System Health Alert */}
      {stats && stats.system_health_score < 80 && (
        <Alert className="border-yellow-200 bg-yellow-50">
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>
            System health score is {stats.system_health_score}%. 
            Please review service statuses and recent activity for issues.
          </AlertDescription>
        </Alert>
      )}

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="grid w-full grid-cols-6">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="users">Users</TabsTrigger>
          <TabsTrigger value="security">Security</TabsTrigger>
          <TabsTrigger value="webhooks">Webhooks</TabsTrigger>
          <TabsTrigger value="services">Services</TabsTrigger>
          <TabsTrigger value="activity">Activity</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="space-y-6">
          {/* Key Metrics Cards */}
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                <Users className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.total_users?.toLocaleString()}</div>
                <p className="text-xs text-muted-foreground">
                  {stats?.active_users_24h} active in last 24h
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Messages</CardTitle>
                <MessageSquare className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.total_messages?.toLocaleString()}</div>
                <p className="text-xs text-muted-foreground">
                  {stats?.messages_24h} sent in last 24h
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Encryption Rate</CardTitle>
                <Lock className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.encrypted_messages_percentage}%</div>
                <p className="text-xs text-muted-foreground">
                  Messages encrypted
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">System Health</CardTitle>
                <Activity className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.system_health_score}%</div>
                <Progress 
                  value={stats?.system_health_score} 
                  className="mt-2"
                />
              </CardContent>
            </Card>
          </div>

          {/* Storage and Performance */}
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Database className="h-5 w-5" />
                  Storage Usage
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span>Used Storage</span>
                    <span>{stats?.storage_used_gb}GB / {stats?.storage_limit_gb}GB</span>
                  </div>
                  <Progress 
                    value={(stats?.storage_used_gb || 0) / (stats?.storage_limit_gb || 1) * 100} 
                  />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Video className="h-5 w-5" />
                  Video Calls
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4 text-center">
                  <div>
                    <div className="text-2xl font-bold text-green-500">
                      {stats?.active_video_calls}
                    </div>
                    <p className="text-xs text-muted-foreground">Active Now</p>
                  </div>
                  <div>
                    <div className="text-2xl font-bold">
                      {stats?.video_calls_24h}
                    </div>
                    <p className="text-xs text-muted-foreground">Last 24h</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Service Status Grid */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Server className="h-5 w-5" />
                Service Status
              </CardTitle>
              <CardDescription>
                Real-time status of critical system services
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {serviceStatuses.map((service) => (
                  <div key={service.service} className="flex items-center justify-between p-3 border rounded-lg">
                    <div className="flex items-center gap-3">
                      <div className={getStatusColor(service.status)}>
                        {getStatusIcon(service.status)}
                      </div>
                      <div>
                        <div className="font-medium capitalize">
                          {service.service.replace('_', ' ')}
                        </div>
                        <div className="text-sm text-muted-foreground">
                          {service.response_time_ms}ms • {service.uptime_percentage}% uptime
                        </div>
                      </div>
                    </div>
                    <Badge 
                      variant={service.status === 'healthy' ? 'default' : 'destructive'}
                      className="capitalize"
                    >
                      {service.status}
                    </Badge>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="users" className="space-y-6">
          <div className="grid gap-4 md:grid-cols-3">
            <Card>
              <CardHeader>
                <CardTitle>User Registration</CardTitle>
                <CardDescription>User growth metrics</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.total_users}</div>
                <p className="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                  <TrendingUp className="h-3 w-3 text-green-500" />
                  +{stats?.active_users_24h} in last 24h
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Organizations</CardTitle>
                <CardDescription>Active organizations</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.total_organizations}</div>
                <p className="text-xs text-muted-foreground mt-1">
                  Registered organizations
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Conversations</CardTitle>
                <CardDescription>Communication activity</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats?.total_conversations}</div>
                <p className="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                  <Activity className="h-3 w-3" />
                  {stats?.active_conversations_24h} active in last 24h
                </p>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="security">
          <SecurityDashboard className="mt-0" />
        </TabsContent>

        <TabsContent value="webhooks">
          <WebhookManager className="mt-0" />
        </TabsContent>

        <TabsContent value="services" className="space-y-6">
          <div className="grid gap-4">
            {serviceStatuses.map((service) => (
              <Card key={service.service}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="capitalize flex items-center gap-2">
                      <div className={getStatusColor(service.status)}>
                        {getStatusIcon(service.status)}
                      </div>
                      {service.service.replace('_', ' ')}
                    </CardTitle>
                    <Badge 
                      variant={service.status === 'healthy' ? 'default' : 'destructive'}
                      className="capitalize"
                    >
                      {service.status}
                    </Badge>
                  </div>
                  <CardDescription>
                    Last checked: {new Date(service.last_checked).toLocaleString()}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-3 gap-4 text-center">
                    <div>
                      <div className="text-lg font-semibold">{service.response_time_ms}ms</div>
                      <p className="text-xs text-muted-foreground">Response Time</p>
                    </div>
                    <div>
                      <div className="text-lg font-semibold">{service.uptime_percentage}%</div>
                      <p className="text-xs text-muted-foreground">Uptime</p>
                    </div>
                    <div>
                      <div className={`text-lg font-semibold ${getStatusColor(service.status)}`}>
                        {service.status.toUpperCase()}
                      </div>
                      <p className="text-xs text-muted-foreground">Status</p>
                    </div>
                  </div>
                  {service.error_message && (
                    <Alert className="mt-4 border-red-200 bg-red-50">
                      <AlertTriangle className="h-4 w-4" />
                      <AlertDescription>{service.error_message}</AlertDescription>
                    </Alert>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="activity" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Recent System Activity</CardTitle>
              <CardDescription>
                Latest events and actions across the platform
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {recentActivity.map((activity) => (
                  <div 
                    key={activity.id} 
                    className={`p-4 border rounded-lg ${getActivitySeverityColor(activity.severity)}`}
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex items-start gap-3">
                        <div className="mt-1">
                          {getActivityIcon(activity.type)}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="font-medium">{activity.title}</div>
                          <p className="text-sm text-muted-foreground mt-1">
                            {activity.description}
                          </p>
                          <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                            <span>{new Date(activity.timestamp).toLocaleString()}</span>
                            {activity.user && (
                              <span>by {activity.user.name}</span>
                            )}
                            {activity.organization && (
                              <span>in {activity.organization.name}</span>
                            )}
                          </div>
                        </div>
                      </div>
                      <Badge 
                        variant={activity.severity === 'error' ? 'destructive' : 'secondary'}
                        className="ml-2"
                      >
                        {activity.severity}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default AdminDashboard;