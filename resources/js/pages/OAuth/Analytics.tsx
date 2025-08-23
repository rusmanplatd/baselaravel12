import { Head } from '@inertiajs/react'
import AppLayout from '@/layouts/app-layout'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { 
  Activity, 
  Users, 
  Shield, 
  CheckCircle, 
  XCircle, 
  Globe,
  Clock,
  BarChart3
} from 'lucide-react'

interface Stats {
  total_requests: number
  successful_requests: number
  failed_requests: number
  requests_today: number
  requests_this_week: number
  unique_clients: number
  unique_users: number
  success_rate: number
}

interface ClientStat {
  client_id: string
  client_name: string
  request_count: number
  success_rate: number
}

interface Activity {
  id: number
  event_type: string
  client_name: string
  user_name: string
  success: boolean
  error_code?: string
  ip_address: string
  created_at: string
}

interface ErrorRate {
  event_type: string
  total_requests: number
  failed_requests: number
  error_rate: number
}

interface Props {
  stats: Stats
  clientStats: ClientStat[]
  recentActivity: Activity[]
  errorRates: ErrorRate[]
}

const eventTypeColors = {
  'authorize': 'bg-blue-500',
  'token': 'bg-green-500',
  'userinfo': 'bg-purple-500',
  'revoke': 'bg-red-500',
  'introspect': 'bg-yellow-500',
  'default': 'bg-gray-500',
}

export default function Analytics({ stats, clientStats, recentActivity, errorRates }: Props) {
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString()
  }

  const getEventTypeColor = (eventType: string) => {
    return eventTypeColors[eventType as keyof typeof eventTypeColors] || eventTypeColors.default
  }

  return (
    <AppLayout>
      <Head title="OAuth Analytics" />
      
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">OAuth Analytics</h1>
            <p className="text-sm text-gray-600">
              Monitor OAuth server performance and usage statistics
            </p>
          </div>
          
          <Button variant="outline">
            <BarChart3 className="h-4 w-4 mr-2" />
            Export Report
          </Button>
        </div>

        {/* Overview Stats */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Requests</CardTitle>
              <Activity className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total_requests.toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">
                {stats.requests_today} today
              </p>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
              <CheckCircle className="h-4 w-4 text-green-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.success_rate}%</div>
              <p className="text-xs text-muted-foreground">
                {stats.successful_requests.toLocaleString()} successful
              </p>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active Clients</CardTitle>
              <Globe className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.unique_clients}</div>
              <p className="text-xs text-muted-foreground">
                Unique applications
              </p>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active Users</CardTitle>
              <Users className="h-4 w-4 text-purple-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.unique_users}</div>
              <p className="text-xs text-muted-foreground">
                This week: {stats.requests_this_week}
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          {/* Top Clients */}
          <Card>
            <CardHeader>
              <CardTitle>Top OAuth Clients</CardTitle>
              <CardDescription>
                Most active applications by request volume
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {clientStats.slice(0, 8).map((client) => (
                  <div key={client.client_id} className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <Shield className="h-4 w-4 text-blue-600" />
                      </div>
                      <div>
                        <p className="text-sm font-medium">{client.client_name}</p>
                        <p className="text-xs text-gray-500">{client.request_count} requests</p>
                      </div>
                    </div>
                    <Badge variant={client.success_rate >= 95 ? "default" : "destructive"}>
                      {client.success_rate}%
                    </Badge>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Error Rates */}
          <Card>
            <CardHeader>
              <CardTitle>Error Rates by Event Type</CardTitle>
              <CardDescription>
                OAuth endpoint error statistics (last 30 days)
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {errorRates.map((error) => (
                  <div key={error.event_type} className="space-y-2">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <div className={`w-3 h-3 rounded-full ${getEventTypeColor(error.event_type)}`} />
                        <span className="text-sm font-medium capitalize">{error.event_type}</span>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-medium">{error.error_rate}%</p>
                        <p className="text-xs text-gray-500">
                          {error.failed_requests}/{error.total_requests}
                        </p>
                      </div>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className={`h-2 rounded-full ${
                          error.error_rate > 5 ? 'bg-red-500' : 
                          error.error_rate > 1 ? 'bg-yellow-500' : 'bg-green-500'
                        }`}
                        style={{ width: `${Math.min(error.error_rate * 10, 100)}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Recent Activity */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
            <CardDescription>
              Latest OAuth server events and requests
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {recentActivity.slice(0, 15).map((activity) => (
                <div key={activity.id} className="flex items-center justify-between p-3 border rounded-lg">
                  <div className="flex items-center space-x-3">
                    <div className="flex items-center space-x-2">
                      {activity.success ? (
                        <CheckCircle className="h-4 w-4 text-green-600" />
                      ) : (
                        <XCircle className="h-4 w-4 text-red-600" />
                      )}
                      <Badge variant="outline" className="text-xs">
                        {activity.event_type}
                      </Badge>
                    </div>
                    
                    <div>
                      <p className="text-sm font-medium">
                        {activity.client_name}
                      </p>
                      <p className="text-xs text-gray-500">
                        User: {activity.user_name} • IP: {activity.ip_address}
                      </p>
                    </div>
                  </div>
                  
                  <div className="text-right">
                    <div className="flex items-center space-x-2">
                      <Clock className="h-3 w-3 text-gray-400" />
                      <span className="text-xs text-gray-500">
                        {formatDate(activity.created_at)}
                      </span>
                    </div>
                    {activity.error_code && (
                      <Badge variant="destructive" className="text-xs mt-1">
                        {activity.error_code}
                      </Badge>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}