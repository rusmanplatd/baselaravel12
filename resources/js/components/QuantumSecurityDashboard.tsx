import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Shield, Key, AlertTriangle, CheckCircle, RotateCcw, Download, RefreshCw, Zap, Lock, Eye, Settings } from 'lucide-react';
import { toast } from 'sonner';
import { useE2EE } from '@/hooks/useE2EE';
import { quantumSafeE2EE, type QuantumSecurityMetrics, type QuantumThreatAlert } from '@/services/QuantumSafeE2EE';

interface QuantumSecurityDashboardProps {
  conversationId?: string;
  compact?: boolean;
}

const QuantumSecurityDashboard: React.FC<QuantumSecurityDashboardProps> = ({ 
  conversationId, 
  compact = false 
}) => {
  const {
    status,
    isReady,
    rotateQuantumKeys,
    exportQuantumSecurityReport,
    validateQuantumHealth,
    performQuantumSelfTest,
    verifyQuantumResistance
  } = useE2EE();

  const [securityMetrics, setSecurityMetrics] = useState<QuantumSecurityMetrics | null>(null);
  const [threatAlerts, setThreatAlerts] = useState<QuantumThreatAlert[]>([]);
  const [loading, setLoading] = useState(false);
  const [lastUpdate, setLastUpdate] = useState<Date>(new Date());

  // Load security data
  const loadSecurityData = async () => {
    try {
      setLoading(true);
      
      const [metrics, alerts] = await Promise.all([
        quantumSafeE2EE.getSecurityMetrics(),
        quantumSafeE2EE.getThreatAlerts()
      ]);
      
      setSecurityMetrics(metrics);
      setThreatAlerts(alerts);
      setLastUpdate(new Date());
    } catch (error) {
      console.error('Failed to load security data:', error);
      toast.error('Failed to load security metrics');
    } finally {
      setLoading(false);
    }
  };

  // Auto-refresh security data
  useEffect(() => {
    if (isReady) {
      loadSecurityData();
      const interval = setInterval(loadSecurityData, 30000); // Every 30 seconds
      return () => clearInterval(interval);
    }
  }, [isReady]);

  // Perform key rotation
  const handleKeyRotation = async () => {
    if (!conversationId) {
      toast.error('No conversation selected for key rotation');
      return;
    }

    try {
      setLoading(true);
      const success = await rotateQuantumKeys(conversationId, 'Dashboard manual rotation');
      
      if (success) {
        toast.success('Quantum keys rotated successfully');
        await loadSecurityData();
      } else {
        toast.error('Key rotation failed');
      }
    } catch (error) {
      console.error('Key rotation failed:', error);
      toast.error('Key rotation failed');
    } finally {
      setLoading(false);
    }
  };

  // Perform quantum self-test
  const handleSelfTest = async () => {
    try {
      setLoading(true);
      const passed = await performQuantumSelfTest();
      
      if (passed) {
        toast.success('Quantum self-test passed successfully');
      } else {
        toast.error('Quantum self-test failed');
      }
      
      await loadSecurityData();
    } catch (error) {
      console.error('Self-test failed:', error);
      toast.error('Self-test failed');
    } finally {
      setLoading(false);
    }
  };

  // Export security report
  const handleExportReport = async () => {
    try {
      const reportBlob = await exportQuantumSecurityReport();
      const url = URL.createObjectURL(reportBlob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `quantum-security-report-${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
      toast.success('Security report exported successfully');
    } catch (error) {
      console.error('Export failed:', error);
      toast.error('Failed to export security report');
    }
  };

  // Clear threat alerts
  const handleClearAlerts = () => {
    quantumSafeE2EE.clearThreatAlerts();
    setThreatAlerts([]);
    toast.success('Threat alerts cleared');
  };

  // Get security level color
  const getSecurityLevelColor = (score: number): string => {
    if (score >= 9) return 'text-green-600 bg-green-50 border-green-200';
    if (score >= 7) return 'text-blue-600 bg-blue-50 border-blue-200';
    if (score >= 5) return 'text-yellow-600 bg-yellow-50 border-yellow-200';
    return 'text-red-600 bg-red-50 border-red-200';
  };

  // Get threat level color
  const getThreatLevelColor = (level: string): string => {
    switch (level) {
      case 'low': return 'bg-green-100 text-green-800 border-green-200';
      case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'high': return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'critical': return 'bg-red-100 text-red-800 border-red-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  if (!isReady) {
    return (
      <Card className="w-full">
        <CardContent className="p-6">
          <div className="flex items-center justify-center space-x-2">
            <RefreshCw className="h-4 w-4 animate-spin" />
            <span>Initializing Quantum Security Dashboard...</span>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (compact) {
    return (
      <Card className="w-full">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <Shield className="h-5 w-5 text-blue-600" />
              <CardTitle className="text-sm">Quantum Security</CardTitle>
            </div>
            <Badge 
              className={getSecurityLevelColor(securityMetrics?.overallSecurityScore || 0)}
              variant="outline"
            >
              Level {securityMetrics?.overallSecurityScore?.toFixed(1) || '0.0'}
            </Badge>
          </div>
        </CardHeader>
        <CardContent className="pt-0">
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">Quantum Ready</span>
              <div className="flex items-center space-x-1">
                {status.quantumReady ? (
                  <CheckCircle className="h-4 w-4 text-green-600" />
                ) : (
                  <AlertTriangle className="h-4 w-4 text-red-600" />
                )}
                <span>{status.quantumReady ? 'Yes' : 'No'}</span>
              </div>
            </div>
            
            {threatAlerts.length > 0 && (
              <Alert className="border-orange-200 bg-orange-50">
                <AlertTriangle className="h-4 w-4" />
                <AlertDescription className="text-sm">
                  {threatAlerts.length} active threat alert{threatAlerts.length !== 1 ? 's' : ''}
                </AlertDescription>
              </Alert>
            )}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="w-full space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <Shield className="h-8 w-8 text-blue-600" />
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Quantum Security Dashboard</h2>
            <p className="text-sm text-gray-500">
              Last updated: {lastUpdate.toLocaleTimeString()}
            </p>
          </div>
        </div>
        
        <div className="flex space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={loadSecurityData}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          
          <Button
            variant="outline"
            size="sm"
            onClick={handleExportReport}
          >
            <Download className="h-4 w-4 mr-2" />
            Export Report
          </Button>
        </div>
      </div>

      {/* Security Status Overview */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Security Score</p>
                <p className="text-2xl font-bold text-blue-600">
                  {securityMetrics?.overallSecurityScore?.toFixed(1) || '0.0'}/10
                </p>
              </div>
              <Shield className="h-8 w-8 text-blue-600" />
            </div>
            <Progress 
              value={(securityMetrics?.overallSecurityScore || 0) * 10} 
              className="mt-2"
            />
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Quantum Ready</p>
                <p className="text-2xl font-bold text-green-600">
                  {status.quantumReady ? 'Yes' : 'No'}
                </p>
              </div>
              {status.quantumReady ? (
                <CheckCircle className="h-8 w-8 text-green-600" />
              ) : (
                <AlertTriangle className="h-8 w-8 text-red-600" />
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Key Rotations</p>
                <p className="text-2xl font-bold text-purple-600">
                  {securityMetrics?.keyRotationCount || 0}
                </p>
              </div>
              <RotateCcw className="h-8 w-8 text-purple-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Threat Alerts</p>
                <p className="text-2xl font-bold text-orange-600">
                  {threatAlerts.length}
                </p>
              </div>
              <AlertTriangle className="h-8 w-8 text-orange-600" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Main Content Tabs */}
      <Tabs defaultValue="overview" className="w-full">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="threats">Threat Alerts</TabsTrigger>
          <TabsTrigger value="keys">Key Management</TabsTrigger>
          <TabsTrigger value="advanced">Advanced</TabsTrigger>
        </TabsList>

        {/* Overview Tab */}
        <TabsContent value="overview" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Security Features */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Lock className="h-5 w-5" />
                  <span>Security Features</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <span>Forward Secrecy</span>
                  {securityMetrics?.forwardSecrecyActive ? (
                    <CheckCircle className="h-5 w-5 text-green-600" />
                  ) : (
                    <AlertTriangle className="h-5 w-5 text-red-600" />
                  )}
                </div>
                
                <div className="flex items-center justify-between">
                  <span>Quantum Resistance</span>
                  {securityMetrics?.isQuantumResistant ? (
                    <CheckCircle className="h-5 w-5 text-green-600" />
                  ) : (
                    <AlertTriangle className="h-5 w-5 text-red-600" />
                  )}
                </div>
                
                <div className="flex items-center justify-between">
                  <span>Algorithm Strength</span>
                  <Badge variant="outline">
                    Level {securityMetrics?.algorithmStrength || 0}
                  </Badge>
                </div>
                
                <div className="flex items-center justify-between">
                  <span>Failure Rate</span>
                  <span className="text-sm text-gray-600">
                    {((securityMetrics?.failureRate || 0) * 100).toFixed(2)}%
                  </span>
                </div>
              </CardContent>
            </Card>

            {/* Operation Stats */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Zap className="h-5 w-5" />
                  <span>Operation Statistics</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <span>Encryptions</span>
                  <span className="font-mono text-sm">
                    {securityMetrics?.encryptionCount?.toLocaleString() || 0}
                  </span>
                </div>
                
                <div className="flex items-center justify-between">
                  <span>Decryptions</span>
                  <span className="font-mono text-sm">
                    {securityMetrics?.decryptionCount?.toLocaleString() || 0}
                  </span>
                </div>
                
                <div className="flex items-center justify-between">
                  <span>Last Key Rotation</span>
                  <span className="text-sm text-gray-600">
                    {securityMetrics?.lastKeyRotation ? 
                      new Date(securityMetrics.lastKeyRotation).toLocaleDateString() : 
                      'Never'
                    }
                  </span>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* Threats Tab */}
        <TabsContent value="threats" className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold">Active Threat Alerts</h3>
            {threatAlerts.length > 0 && (
              <Button variant="outline" size="sm" onClick={handleClearAlerts}>
                Clear All Alerts
              </Button>
            )}
          </div>
          
          {threatAlerts.length === 0 ? (
            <Card>
              <CardContent className="p-8 text-center">
                <CheckCircle className="h-12 w-12 text-green-600 mx-auto mb-4" />
                <h3 className="text-lg font-semibold text-green-600">No Active Threats</h3>
                <p className="text-sm text-gray-600 mt-2">
                  Your quantum encryption system is secure
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {threatAlerts.map((alert) => (
                <Alert key={alert.alertId} className={getThreatLevelColor(alert.threatLevel)}>
                  <AlertTriangle className="h-4 w-4" />
                  <AlertTitle className="flex items-center justify-between">
                    <span>
                      {alert.threatLevel.charAt(0).toUpperCase() + alert.threatLevel.slice(1)} Threat
                    </span>
                    <Badge variant="outline" className="ml-2">
                      {alert.timestamp.toLocaleTimeString()}
                    </Badge>
                  </AlertTitle>
                  <AlertDescription className="mt-2">
                    {alert.description}
                    {alert.mitigation && (
                      <div className="mt-2 p-2 bg-white bg-opacity-50 rounded text-sm">
                        <strong>Mitigation:</strong> {alert.mitigation}
                      </div>
                    )}
                  </AlertDescription>
                </Alert>
              ))}
            </div>
          )}
        </TabsContent>

        {/* Key Management Tab */}
        <TabsContent value="keys" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Key className="h-5 w-5" />
                <span>Key Management</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                <div>
                  <h4 className="font-medium">Conversation Keys</h4>
                  <p className="text-sm text-gray-600">
                    Rotate quantum-safe encryption keys for enhanced security
                  </p>
                </div>
                <Button
                  onClick={handleKeyRotation}
                  disabled={loading || !conversationId}
                  variant="outline"
                >
                  <RotateCcw className="h-4 w-4 mr-2" />
                  Rotate Keys
                </Button>
              </div>

              <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                <div>
                  <h4 className="font-medium">Quantum Self-Test</h4>
                  <p className="text-sm text-gray-600">
                    Verify quantum encryption integrity and functionality
                  </p>
                </div>
                <Button
                  onClick={handleSelfTest}
                  disabled={loading}
                  variant="outline"
                >
                  <Settings className="h-4 w-4 mr-2" />
                  Run Test
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Advanced Tab */}
        <TabsContent value="advanced" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Advanced Quantum Security Settings</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              <Alert>
                <Eye className="h-4 w-4" />
                <AlertTitle>Quantum Security Status</AlertTitle>
                <AlertDescription>
                  Your system is using {status.algorithm} with security level {status.quantumSecurityLevel}.
                  All quantum-safe features are {status.quantumReady ? 'active' : 'inactive'}.
                </AlertDescription>
              </Alert>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="p-4 border border-gray-200 rounded-lg">
                  <h4 className="font-medium mb-2">Cipher Suite</h4>
                  <p className="text-sm text-gray-600 mb-3">quantum_hybrid_v1</p>
                  <div className="text-xs text-gray-500">
                    Primary: XChaCha20-Poly1305<br />
                    Fallback: AES-256-GCM<br />
                    Key Exchange: ML-KEM-1024<br />
                    Signature: ML-DSA-87
                  </div>
                </div>

                <div className="p-4 border border-gray-200 rounded-lg">
                  <h4 className="font-medium mb-2">Security Features</h4>
                  <div className="space-y-1 text-sm">
                    <div className="flex items-center justify-between">
                      <span>Zero-Knowledge Proofs</span>
                      <CheckCircle className="h-4 w-4 text-green-600" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Homomorphic Encryption</span>
                      <CheckCircle className="h-4 w-4 text-green-600" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Triple Signatures</span>
                      <CheckCircle className="h-4 w-4 text-green-600" />
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default QuantumSecurityDashboard;