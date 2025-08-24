import React, { useState, useEffect } from 'react';
import {
  Activity,
  TrendingUp,
  Clock,
  Zap,
  Database,
  Settings,
  RefreshCw,
  CheckCircle,
  AlertTriangle,
  BarChart3,
  Download,
  Upload
} from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { e2eePerformanceMonitor, type PerformanceStats } from '@/utils/E2EEPerformanceMonitor';
import { optimizedE2EEService } from '@/services/OptimizedE2EEService';

interface E2EEPerformanceDashboardProps {
  onClose?: () => void;
  autoRefresh?: boolean;
  refreshInterval?: number;
}

export function E2EEPerformanceDashboard({
  onClose,
  autoRefresh = true,
  refreshInterval = 5000
}: E2EEPerformanceDashboardProps) {
  const [stats, setStats] = useState<PerformanceStats>({
    averageDuration: 0,
    totalOperations: 0,
    successRate: 0,
    slowestOperations: [],
    recommendations: []
  });

  const [realtimeData, setRealtimeData] = useState({
    currentLoad: 'low' as 'low' | 'medium' | 'high',
    recentOperations: 0,
    averageLatency: 0,
    errorRate: 0
  });

  const [cacheStats, setCacheStats] = useState({
    size: 0,
    hitRate: 0,
    totalHits: 0,
    entries: []
  });

  const [optimizationNeeded, setOptimizationNeeded] = useState({
    needed: false,
    priority: 'low' as 'low' | 'medium' | 'high',
    reasons: []
  });

  const [settings, setSettings] = useState({
    keyCacheTTL: 5,
    batchSize: 10,
    compressionThreshold: 1000,
    chunkThreshold: 10000,
    autoOptimization: true
  });

  // Refresh data
  const refreshData = () => {
    setStats(e2eePerformanceMonitor.getStats());
    setRealtimeData(e2eePerformanceMonitor.getRealTimeSummary());
    setCacheStats(optimizedE2EEService.getCacheStats());
    setOptimizationNeeded(e2eePerformanceMonitor.needsOptimization());
  };

  // Auto-refresh effect
  useEffect(() => {
    refreshData();

    if (autoRefresh) {
      const interval = setInterval(refreshData, refreshInterval);
      return () => clearInterval(interval);
    }
  }, [autoRefresh, refreshInterval]);

  // Apply optimization settings
  const applySettings = () => {
    optimizedE2EEService.configure({
      keyCacheTTL: settings.keyCacheTTL * 60 * 1000, // Convert to ms
      batchSize: settings.batchSize,
      compressionThreshold: settings.compressionThreshold,
      chunkThreshold: settings.chunkThreshold
    });
    refreshData();
  };

  // Export performance data
  const exportData = () => {
    const data = e2eePerformanceMonitor.exportMetrics();
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `e2ee-performance-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  // Clear performance data
  const clearData = () => {
    e2eePerformanceMonitor.clearMetrics();
    optimizedE2EEService.clearCaches();
    refreshData();
  };

  const getLoadColor = (load: string) => {
    switch (load) {
      case 'high': return 'text-red-600';
      case 'medium': return 'text-yellow-600';
      default: return 'text-green-600';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high': return 'destructive';
      case 'medium': return 'secondary';
      default: return 'outline';
    }
  };

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold flex items-center space-x-2">
            <Activity className="h-8 w-8" />
            <span>E2EE Performance Dashboard</span>
          </h1>
          <p className="text-gray-600 mt-1">Monitor encryption performance and optimize system resources</p>
        </div>
        <div className="flex items-center space-x-2">
          <Button variant="outline" onClick={refreshData}>
            <RefreshCw className="h-4 w-4 mr-2" />
            Refresh
          </Button>
          {onClose && (
            <Button variant="outline" onClick={onClose}>
              Close
            </Button>
          )}
        </div>
      </div>

      {/* Real-time Status Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">System Load</p>
                <p className={`text-2xl font-bold ${getLoadColor(realtimeData.currentLoad)}`}>
                  {realtimeData.currentLoad.toUpperCase()}
                </p>
              </div>
              <Activity className={`h-8 w-8 ${getLoadColor(realtimeData.currentLoad)}`} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Recent Ops</p>
                <p className="text-2xl font-bold">{realtimeData.recentOperations}</p>
                <p className="text-xs text-gray-500">Last 5 minutes</p>
              </div>
              <BarChart3 className="h-8 w-8 text-blue-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Avg Latency</p>
                <p className="text-2xl font-bold">{Math.round(realtimeData.averageLatency)}ms</p>
              </div>
              <Clock className="h-8 w-8 text-orange-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Success Rate</p>
                <p className="text-2xl font-bold">{Math.round(100 - realtimeData.errorRate)}%</p>
              </div>
              <CheckCircle className="h-8 w-8 text-green-600" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Optimization Alert */}
      {optimizationNeeded.needed && (
        <Alert variant={optimizationNeeded.priority === 'high' ? 'destructive' : 'default'}>
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>
            Optimization Recommended
            <Badge variant={getPriorityColor(optimizationNeeded.priority)} className="ml-2">
              {optimizationNeeded.priority.toUpperCase()}
            </Badge>
          </AlertTitle>
          <AlertDescription>
            <ul className="list-disc list-inside mt-2 space-y-1">
              {optimizationNeeded.reasons.map((reason, index) => (
                <li key={index} className="text-sm">{reason}</li>
              ))}
            </ul>
          </AlertDescription>
        </Alert>
      )}

      {/* Main Dashboard Tabs */}
      <Tabs defaultValue="overview" className="space-y-4">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="performance">Performance</TabsTrigger>
          <TabsTrigger value="cache">Cache</TabsTrigger>
          <TabsTrigger value="settings">Settings</TabsTrigger>
        </TabsList>

        {/* Overview Tab */}
        <TabsContent value="overview" className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <TrendingUp className="h-5 w-5" />
                  <span>Performance Summary</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm font-medium">Average Duration</span>
                    <span className="text-sm">{Math.round(stats.averageDuration)}ms</span>
                  </div>
                  <Progress
                    value={Math.min((stats.averageDuration / 1000) * 100, 100)}
                    className="h-2"
                  />
                </div>

                <div>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm font-medium">Success Rate</span>
                    <span className="text-sm">{Math.round(stats.successRate)}%</span>
                  </div>
                  <Progress value={stats.successRate} className="h-2" />
                </div>

                <div className="pt-2 border-t">
                  <p className="text-sm text-gray-600">Total Operations: <span className="font-medium">{stats.totalOperations}</span></p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Database className="h-5 w-5" />
                  <span>Cache Performance</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm font-medium">Cache Hit Rate</span>
                    <span className="text-sm">{Math.round(cacheStats.hitRate)}%</span>
                  </div>
                  <Progress value={cacheStats.hitRate} className="h-2" />
                </div>

                <div className="grid grid-cols-2 gap-4 pt-2 border-t">
                  <div>
                    <p className="text-xs text-gray-600">Cached Keys</p>
                    <p className="text-lg font-medium">{cacheStats.size}</p>
                  </div>
                  <div>
                    <p className="text-xs text-gray-600">Total Hits</p>
                    <p className="text-lg font-medium">{cacheStats.totalHits}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Recommendations */}
          {stats.recommendations.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Zap className="h-5 w-5" />
                  <span>Optimization Recommendations</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-2">
                  {stats.recommendations.map((recommendation, index) => (
                    <li key={index} className="flex items-start space-x-2">
                      <TrendingUp className="h-4 w-4 text-green-600 mt-0.5" />
                      <span className="text-sm">{recommendation}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* Performance Tab */}
        <TabsContent value="performance" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Slowest Operations</CardTitle>
              <CardDescription>Operations with the highest latency</CardDescription>
            </CardHeader>
            <CardContent>
              {stats.slowestOperations.length === 0 ? (
                <p className="text-center py-8 text-gray-500">No slow operations recorded</p>
              ) : (
                <div className="space-y-3">
                  {stats.slowestOperations.map((op, index) => (
                    <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded">
                      <div>
                        <p className="font-medium">{op.operation}</p>
                        <p className="text-sm text-gray-600">
                          {new Date(op.timestamp).toLocaleTimeString()}
                          {op.dataSize && ` • ${op.dataSize} bytes`}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="font-medium">{Math.round(op.duration)}ms</p>
                        <Badge variant={op.success ? 'default' : 'destructive'}>
                          {op.success ? 'Success' : 'Failed'}
                        </Badge>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Cache Tab */}
        <TabsContent value="cache" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center justify-between">
                <span>Cache Details</span>
                <Button variant="outline" size="sm" onClick={() => optimizedE2EEService.clearCaches()}>
                  Clear Cache
                </Button>
              </CardTitle>
            </CardHeader>
            <CardContent>
              {cacheStats.entries.length === 0 ? (
                <p className="text-center py-8 text-gray-500">No cached keys</p>
              ) : (
                <div className="space-y-2">
                  {cacheStats.entries.map((entry, index) => (
                    <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded">
                      <div>
                        <p className="font-medium font-mono text-sm">{entry.keyId}</p>
                        <p className="text-xs text-gray-600">
                          Last used: {new Date(entry.lastUsed).toLocaleTimeString()}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="font-medium">{entry.hitCount} hits</p>
                        <p className="text-xs text-gray-600">
                          Expires: {new Date(entry.expiresAt).toLocaleTimeString()}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Settings Tab */}
        <TabsContent value="settings" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Settings className="h-5 w-5" />
                <span>Performance Settings</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <div>
                  <Label>Cache TTL (minutes): {settings.keyCacheTTL}</Label>
                  <Slider
                    value={[settings.keyCacheTTL]}
                    onValueChange={(value) => setSettings(prev => ({ ...prev, keyCacheTTL: value[0] }))}
                    min={1}
                    max={60}
                    step={1}
                    className="mt-2"
                  />
                </div>

                <div>
                  <Label>Batch Size: {settings.batchSize}</Label>
                  <Slider
                    value={[settings.batchSize]}
                    onValueChange={(value) => setSettings(prev => ({ ...prev, batchSize: value[0] }))}
                    min={1}
                    max={50}
                    step={1}
                    className="mt-2"
                  />
                </div>

                <div>
                  <Label>Compression Threshold (bytes): {settings.compressionThreshold}</Label>
                  <Slider
                    value={[settings.compressionThreshold]}
                    onValueChange={(value) => setSettings(prev => ({ ...prev, compressionThreshold: value[0] }))}
                    min={100}
                    max={10000}
                    step={100}
                    className="mt-2"
                  />
                </div>

                <div>
                  <Label>Chunk Threshold (bytes): {settings.chunkThreshold}</Label>
                  <Slider
                    value={[settings.chunkThreshold]}
                    onValueChange={(value) => setSettings(prev => ({ ...prev, chunkThreshold: value[0] }))}
                    min={1000}
                    max={50000}
                    step={1000}
                    className="mt-2"
                  />
                </div>

                <div className="flex items-center space-x-2">
                  <Switch
                    id="auto-optimization"
                    checked={settings.autoOptimization}
                    onCheckedChange={(checked) => setSettings(prev => ({ ...prev, autoOptimization: checked }))}
                  />
                  <Label htmlFor="auto-optimization">Enable Auto-optimization</Label>
                </div>
              </div>

              <div className="flex space-x-2 pt-4 border-t">
                <Button onClick={applySettings}>
                  Apply Settings
                </Button>
                <Button variant="outline" onClick={exportData}>
                  <Download className="h-4 w-4 mr-2" />
                  Export Data
                </Button>
                <Button variant="outline" onClick={clearData}>
                  <Upload className="h-4 w-4 mr-2" />
                  Clear Data
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
