import React, { useState, useEffect } from 'react';
import { Shield, AlertTriangle, CheckCircle, XCircle, RefreshCw, Clock, Users, Key } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { multiDeviceE2EEService } from '@/services/MultiDeviceE2EEService';

interface SecurityMetrics {
  overallSecurityScore: number;
  devicesCount: number;
  trustedDevicesCount: number;
  activeSessionsCount: number;
  pendingKeysharesCount: number;
  lastKeyRotation: string | null;
  suspiciousActivities: number;
  encryptedConversations: number;
}

interface SecurityAlert {
  id: string;
  type: 'critical' | 'warning' | 'info';
  title: string;
  message: string;
  timestamp: Date;
  deviceId?: string;
  deviceName?: string;
  resolved: boolean;
}

interface HealthCheckResult {
  category: string;
  status: 'healthy' | 'warning' | 'critical';
  score: number;
  details: string;
  recommendation?: string;
}

export function SecurityMonitor() {
  const [metrics, setMetrics] = useState<SecurityMetrics | null>(null);
  const [alerts, setAlerts] = useState<SecurityAlert[]>([]);
  const [healthChecks, setHealthChecks] = useState<HealthCheckResult[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadSecurityData();
    
    // Set up periodic refresh
    const interval = setInterval(loadSecurityData, 30000); // 30 seconds
    
    return () => clearInterval(interval);
  }, []);

  const loadSecurityData = async () => {
    try {
      setRefreshing(true);
      
      // Load security report
      const report = await multiDeviceE2EEService.getDeviceSecurityReport();
      
      setMetrics({
        overallSecurityScore: report.integrityReport.securityScore,
        devicesCount: 1, // This device
        trustedDevicesCount: report.encryptionSummary.isTrusted ? 1 : 0,
        activeSessionsCount: 1, // Current session
        pendingKeysharesCount: report.encryptionSummary.pendingKeyShares,
        lastKeyRotation: report.encryptionSummary.lastKeyRotation,
        suspiciousActivities: 0,
        encryptedConversations: report.encryptionSummary.activeConversationKeys,
      });

      // Transform integrity issues into security alerts
      const securityAlerts: SecurityAlert[] = report.integrityReport.issues.map((issue: any, index: number) => ({
        id: `alert_${index}`,
        type: issue.severity === 'high' ? 'critical' : issue.severity === 'medium' ? 'warning' : 'info',
        title: issue.type.replace('_', ' ').toUpperCase(),
        message: issue.message,
        timestamp: new Date(report.generatedAt),
        deviceId: report.deviceId,
        deviceName: report.deviceName,
        resolved: false,
      }));

      setAlerts(securityAlerts);

      // Generate health checks
      const healthResults: HealthCheckResult[] = [
        {
          category: 'Device Security',
          status: report.integrityReport.securityScore >= 80 ? 'healthy' : 
                 report.integrityReport.securityScore >= 50 ? 'warning' : 'critical',
          score: report.integrityReport.securityScore,
          details: `Security score: ${report.integrityReport.securityScore}/100`,
          recommendation: report.integrityReport.securityScore < 80 ? 
            'Consider improving device security settings' : undefined,
        },
        {
          category: 'Encryption Status',
          status: report.encryptionSummary.isTrusted ? 'healthy' : 'warning',
          score: report.encryptionSummary.isTrusted ? 100 : 50,
          details: report.encryptionSummary.isTrusted ? 
            'Device is trusted and verified' : 'Device verification pending',
          recommendation: !report.encryptionSummary.isTrusted ? 
            'Complete device verification process' : undefined,
        },
        {
          category: 'Key Management',
          status: report.encryptionSummary.requiresKeyRotation ? 'warning' : 'healthy',
          score: report.encryptionSummary.requiresKeyRotation ? 60 : 100,
          details: `${report.encryptionSummary.activeConversationKeys} active encryption keys`,
          recommendation: report.encryptionSummary.requiresKeyRotation ? 
            'Consider rotating encryption keys' : undefined,
        },
      ];

      setHealthChecks(healthResults);

    } catch (error) {
      console.error('Failed to load security data:', error);
      // Set error state or show notification
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const handleRefresh = () => {
    loadSecurityData();
  };

  const handleResolveAlert = (alertId: string) => {
    setAlerts(alerts.map(alert => 
      alert.id === alertId ? { ...alert, resolved: true } : alert
    ));
  };

  const getSecurityScoreColor = (score: number) => {
    if (score >= 80) return 'text-green-600';
    if (score >= 50) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'healthy': return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'warning': return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      case 'critical': return <XCircle className="h-4 w-4 text-red-500" />;
      default: return null;
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <RefreshCw className="h-6 w-6 animate-spin" />
        <span className="ml-2">Loading security data...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-2">
          <Shield className="h-6 w-6" />
          <h2 className="text-2xl font-bold">Security Monitor</h2>
        </div>
        <Button onClick={handleRefresh} disabled={refreshing}>
          <RefreshCw className={`h-4 w-4 mr-2 ${refreshing ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>

      {/* Security Score Overview */}
      {metrics && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Shield className="h-5 w-5" />
              <span>Overall Security Score</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-center">
              <div className={`text-4xl font-bold ${getSecurityScoreColor(metrics.overallSecurityScore)}`}>
                {metrics.overallSecurityScore}/100
              </div>
              <Progress value={metrics.overallSecurityScore} className="mt-4" />
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
              <div className="text-center">
                <Users className="h-8 w-8 mx-auto mb-2 text-gray-500" />
                <div className="text-2xl font-bold">{metrics.trustedDevicesCount}/{metrics.devicesCount}</div>
                <div className="text-sm text-gray-500">Trusted Devices</div>
              </div>
              <div className="text-center">
                <Key className="h-8 w-8 mx-auto mb-2 text-gray-500" />
                <div className="text-2xl font-bold">{metrics.encryptedConversations}</div>
                <div className="text-sm text-gray-500">Encrypted Chats</div>
              </div>
              <div className="text-center">
                <Clock className="h-8 w-8 mx-auto mb-2 text-gray-500" />
                <div className="text-2xl font-bold">{metrics.activeSessionsCount}</div>
                <div className="text-sm text-gray-500">Active Sessions</div>
              </div>
              <div className="text-center">
                <AlertTriangle className="h-8 w-8 mx-auto mb-2 text-gray-500" />
                <div className="text-2xl font-bold">{alerts.filter(a => !a.resolved).length}</div>
                <div className="text-sm text-gray-500">Active Alerts</div>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Tabs for detailed view */}
      <Tabs defaultValue="alerts" className="space-y-4">
        <TabsList className="grid w-full grid-cols-3">
          <TabsTrigger value="alerts">Security Alerts</TabsTrigger>
          <TabsTrigger value="health">Health Checks</TabsTrigger>
          <TabsTrigger value="activity">Recent Activity</TabsTrigger>
        </TabsList>

        <TabsContent value="alerts" className="space-y-4">
          {alerts.length === 0 ? (
            <Card>
              <CardContent className="p-6 text-center">
                <CheckCircle className="h-12 w-12 mx-auto mb-4 text-green-500" />
                <h3 className="text-lg font-medium">No Security Alerts</h3>
                <p className="text-gray-500">Your security status looks good!</p>
              </CardContent>
            </Card>
          ) : (
            alerts.map((alert) => (
              <Alert key={alert.id} variant={alert.type === 'critical' ? 'destructive' : 'default'}>
                <div className="flex items-start justify-between">
                  <div className="flex items-start space-x-2">
                    {alert.type === 'critical' && <XCircle className="h-4 w-4 text-red-500 mt-0.5" />}
                    {alert.type === 'warning' && <AlertTriangle className="h-4 w-4 text-yellow-500 mt-0.5" />}
                    <div>
                      <h4 className="font-medium">{alert.title}</h4>
                      <AlertDescription className="mt-1">
                        {alert.message}
                        {alert.deviceName && (
                          <div className="text-sm text-gray-500 mt-1">
                            Device: {alert.deviceName}
                          </div>
                        )}
                      </AlertDescription>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Badge variant={alert.resolved ? 'secondary' : 'destructive'}>
                      {alert.resolved ? 'Resolved' : 'Active'}
                    </Badge>
                    {!alert.resolved && (
                      <Button size="sm" onClick={() => handleResolveAlert(alert.id)}>
                        Mark Resolved
                      </Button>
                    )}
                  </div>
                </div>
              </Alert>
            ))
          )}
        </TabsContent>

        <TabsContent value="health" className="space-y-4">
          {healthChecks.map((check, index) => (
            <Card key={index}>
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center space-x-2">
                    {getStatusIcon(check.status)}
                    <h3 className="font-medium">{check.category}</h3>
                  </div>
                  <Badge variant={check.status === 'healthy' ? 'default' : 'secondary'}>
                    {check.status}
                  </Badge>
                </div>
                <p className="text-gray-600 mb-2">{check.details}</p>
                {check.recommendation && (
                  <p className="text-sm text-blue-600">{check.recommendation}</p>
                )}
                <Progress value={check.score} className="mt-4" />
              </CardContent>
            </Card>
          ))}
        </TabsContent>

        <TabsContent value="activity" className="space-y-4">
          <Card>
            <CardContent className="p-6 text-center">
              <Clock className="h-12 w-12 mx-auto mb-4 text-gray-400" />
              <h3 className="text-lg font-medium">Activity Log Coming Soon</h3>
              <p className="text-gray-500">
                Detailed security activity logging will be available in the next update.
              </p>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}