import React, { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Shield, 
  AlertTriangle,
  CheckCircle,
  RefreshCw, 
  Zap, 
  Settings,
  BarChart3,
  Users,
  MessageSquare,
  Clock,
  TrendingUp,
  AlertCircle,
  Play,
  Pause,
  RotateCcw
} from 'lucide-react';
import { useQuantumE2EE } from '@/hooks/useQuantumE2EE';
import { quantumMigrationUtils, type MigrationReport, type MigrationAssessment } from '@/utils/QuantumMigrationUtils';
import { QuantumHealthIndicator } from './quantum-health-indicator';
import { QuantumDeviceManager } from './quantum-device-manager';
import { cn } from '@/lib/utils';

interface QuantumAdminPanelProps {
  className?: string;
}

export function QuantumAdminPanel({ className }: QuantumAdminPanelProps) {
  const { quantumStatus, deviceStatus } = useQuantumE2EE();
  const [assessment, setAssessment] = useState<MigrationAssessment | null>(null);
  const [migrationReport, setMigrationReport] = useState<MigrationReport | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    loadAssessment();
    const interval = setInterval(checkMigrationStatus, 5000);
    return () => clearInterval(interval);
  }, []);

  const loadAssessment = async () => {
    try {
      setIsLoading(true);
      const result = await quantumMigrationUtils.assessMigrationReadiness();
      setAssessment(result);
    } catch (error) {
      console.error('Failed to load assessment:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const checkMigrationStatus = () => {
    const status = quantumMigrationUtils.getMigrationStatus();
    setMigrationReport(status);
  };

  const handleStartMigration = async (strategy: 'immediate' | 'gradual' | 'hybrid') => {
    try {
      setIsLoading(true);
      await quantumMigrationUtils.startMigration(strategy);
    } catch (error) {
      console.error('Failed to start migration:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancelMigration = async () => {
    try {
      await quantumMigrationUtils.cancelMigration('Cancelled by admin');
    } catch (error) {
      console.error('Failed to cancel migration:', error);
    }
  };

  const getRiskColor = (level: string) => {
    switch (level) {
      case 'low': return 'text-green-600';
      case 'medium': return 'text-amber-600';
      case 'high': return 'text-red-600';
      default: return 'text-gray-600';
    }
  };

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'critical': return 'border-red-500 bg-red-50';
      case 'high': return 'border-orange-500 bg-orange-50';
      case 'medium': return 'border-amber-500 bg-amber-50';
      case 'low': return 'border-blue-500 bg-blue-50';
      default: return 'border-gray-500 bg-gray-50';
    }
  };

  return (
    <div className={cn('w-full space-y-6', className)}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Quantum Security Administration</h2>
          <p className="text-muted-foreground">
            Manage quantum-resistant encryption across your organization
          </p>
        </div>
        <Button
          variant="outline"
          onClick={loadAssessment}
          disabled={isLoading}
          className="flex items-center gap-2"
        >
          <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} />
          Refresh
        </Button>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className="grid w-full grid-cols-5">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="migration">Migration</TabsTrigger>
          <TabsTrigger value="devices">Devices</TabsTrigger>
          <TabsTrigger value="analytics">Analytics</TabsTrigger>
          <TabsTrigger value="settings">Settings</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="space-y-6">
          {/* System Status Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center gap-2">
                  <Shield className="h-5 w-5 text-blue-500" />
                  <div>
                    <p className="text-sm font-medium">Quantum Status</p>
                    <p className="text-2xl font-bold">
                      {quantumStatus.quantumReady ? 'Active' : 'Inactive'}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center gap-2">
                  <Users className="h-5 w-5 text-green-500" />
                  <div>
                    <p className="text-sm font-medium">Ready Devices</p>
                    <p className="text-2xl font-bold">
                      {deviceStatus.quantumReadyDevices}/{deviceStatus.totalDevices}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center gap-2">
                  <MessageSquare className="h-5 w-5 text-purple-500" />
                  <div>
                    <p className="text-sm font-medium">Conversations</p>
                    <p className="text-2xl font-bold">
                      {assessment?.totalConversations || 0}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <div className="flex items-center gap-2">
                  <TrendingUp className="h-5 w-5 text-amber-500" />
                  <div>
                    <p className="text-sm font-medium">Risk Level</p>
                    <p className={cn('text-2xl font-bold', assessment && getRiskColor(assessment.riskLevel))}>
                      {assessment?.riskLevel?.toUpperCase() || 'Unknown'}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Health Indicator */}
          <QuantumHealthIndicator />

          {/* Assessment Results */}
          {assessment && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <BarChart3 className="h-5 w-5" />
                  System Assessment
                </CardTitle>
                <CardDescription>
                  Analysis of quantum migration readiness
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-blue-600">
                      {Math.round(deviceStatus.quantumReadinessPercentage)}%
                    </div>
                    <div className="text-sm text-muted-foreground">Device Readiness</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {assessment.estimatedDuration}min
                    </div>
                    <div className="text-sm text-muted-foreground">Est. Migration</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-purple-600">
                      {assessment.compatibilityIssues.length}
                    </div>
                    <div className="text-sm text-muted-foreground">Issues Found</div>
                  </div>
                  <div className="text-center">
                    <Badge variant="outline" className="text-sm">
                      {assessment.recommendedStrategy}
                    </Badge>
                    <div className="text-sm text-muted-foreground">Strategy</div>
                  </div>
                </div>

                {assessment.compatibilityIssues.length > 0 && (
                  <div className="space-y-2">
                    <h4 className="font-medium">Compatibility Issues</h4>
                    {assessment.compatibilityIssues.map((issue, index) => (
                      <Alert key={index} className={getSeverityColor(issue.severity)}>
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                          <span className="font-medium">{issue.description}</span>
                          <br />
                          <span className="text-sm">{issue.solution}</span>
                        </AlertDescription>
                      </Alert>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="migration" className="space-y-6">
          {migrationReport && migrationReport.status === 'in_progress' ? (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                  <span className="flex items-center gap-2">
                    <RefreshCw className="h-5 w-5 animate-spin" />
                    Migration in Progress
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleCancelMigration}
                    className="flex items-center gap-2"
                  >
                    <Pause className="h-4 w-4" />
                    Cancel
                  </Button>
                </CardTitle>
                <CardDescription>
                  {migrationReport.progress.stepDescription}
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span>Progress</span>
                    <span>{migrationReport.progress.progress}%</span>
                  </div>
                  <Progress value={migrationReport.progress.progress} className="h-2" />
                  <div className="text-xs text-muted-foreground">
                    Step {migrationReport.progress.currentStep} of {migrationReport.progress.totalSteps}
                  </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-blue-600">
                      {migrationReport.results.conversationsMigrated}
                    </div>
                    <div className="text-sm text-muted-foreground">Conversations</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {migrationReport.results.devicesUpgraded}
                    </div>
                    <div className="text-sm text-muted-foreground">Devices</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-purple-600">
                      {migrationReport.results.errors.length}
                    </div>
                    <div className="text-sm text-muted-foreground">Errors</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-amber-600">
                      {migrationReport.progress.warnings.length}
                    </div>
                    <div className="text-sm text-muted-foreground">Warnings</div>
                  </div>
                </div>

                {migrationReport.results.errors.length > 0 && (
                  <Alert className="border-red-200 bg-red-50">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      {migrationReport.results.errors.length} errors occurred during migration.
                      Check logs for details.
                    </AlertDescription>
                  </Alert>
                )}
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Zap className="h-5 w-5" />
                  Start Quantum Migration
                </CardTitle>
                <CardDescription>
                  Migrate your system to quantum-resistant encryption
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {assessment && (
                  <Alert className={`border-${assessment.riskLevel === 'high' ? 'red' : assessment.riskLevel === 'medium' ? 'amber' : 'green'}-200`}>
                    <AlertTriangle className="h-4 w-4" />
                    <AlertDescription>
                      Migration risk level: <span className={cn('font-medium', getRiskColor(assessment.riskLevel))}>
                        {assessment.riskLevel.toUpperCase()}
                      </span>
                      <br />
                      Recommended strategy: <span className="font-medium">{assessment.recommendedStrategy}</span>
                    </AlertDescription>
                  </Alert>
                )}

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Button
                    onClick={() => handleStartMigration('immediate')}
                    disabled={isLoading}
                    className="flex items-center gap-2 h-auto p-4"
                    variant="outline"
                  >
                    <div className="text-left">
                      <div className="font-medium">Immediate</div>
                      <div className="text-sm text-muted-foreground">
                        Migrate all at once
                      </div>
                    </div>
                  </Button>

                  <Button
                    onClick={() => handleStartMigration('gradual')}
                    disabled={isLoading}
                    className="flex items-center gap-2 h-auto p-4"
                    variant="outline"
                  >
                    <div className="text-left">
                      <div className="font-medium">Gradual</div>
                      <div className="text-sm text-muted-foreground">
                        Migrate in batches
                      </div>
                    </div>
                  </Button>

                  <Button
                    onClick={() => handleStartMigration('hybrid')}
                    disabled={isLoading}
                    className="flex items-center gap-2 h-auto p-4"
                    variant="outline"
                  >
                    <div className="text-left">
                      <div className="font-medium">Hybrid</div>
                      <div className="text-sm text-muted-foreground">
                        Use transition mode
                      </div>
                    </div>
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {migrationReport && migrationReport.status !== 'in_progress' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  {migrationReport.status === 'completed' ? (
                    <CheckCircle className="h-5 w-5 text-green-500" />
                  ) : (
                    <AlertCircle className="h-5 w-5 text-red-500" />
                  )}
                  Migration {migrationReport.status === 'completed' ? 'Completed' : 'Failed'}
                </CardTitle>
                <CardDescription>
                  {migrationReport.completedAt && (
                    <>Completed at {migrationReport.completedAt.toLocaleString()}</>
                  )}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-blue-600">
                      {migrationReport.results.conversationsMigrated}
                    </div>
                    <div className="text-sm text-muted-foreground">Conversations</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {migrationReport.results.devicesUpgraded}
                    </div>
                    <div className="text-sm text-muted-foreground">Devices</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-purple-600">
                      {Object.values(migrationReport.results.algorithmsUpgraded).reduce((a, b) => a + b, 0)}
                    </div>
                    <div className="text-sm text-muted-foreground">Algorithms</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-red-600">
                      {migrationReport.results.errors.length}
                    </div>
                    <div className="text-sm text-muted-foreground">Errors</div>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="devices">
          <QuantumDeviceManager />
        </TabsContent>

        <TabsContent value="analytics" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <BarChart3 className="h-5 w-5" />
                Encryption Analytics
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-center py-8 text-muted-foreground">
                <BarChart3 className="h-12 w-12 mx-auto mb-4 opacity-50" />
                <p>Analytics dashboard coming soon</p>
                <p className="text-sm">Track quantum encryption usage and performance</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="settings" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Settings className="h-5 w-5" />
                Quantum Settings
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-center py-8 text-muted-foreground">
                <Settings className="h-12 w-12 mx-auto mb-4 opacity-50" />
                <p>Settings panel coming soon</p>
                <p className="text-sm">Configure quantum encryption preferences</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}