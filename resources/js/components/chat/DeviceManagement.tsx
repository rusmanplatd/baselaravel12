import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { 
  DevicePhoneMobileIcon, 
  ComputerDesktopIcon,
  GlobeAltIcon,
  DeviceTabletIcon,
  ShieldCheckIcon,
  ShieldExclamationIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon,
  TrashIcon,
  CheckCircleIcon,
  ClockIcon,
  SignalIcon
} from '@heroicons/react/24/outline';
import { multiDeviceE2EEService, DeviceInfo, SecurityReport } from '@/services/MultiDeviceE2EEService';
import { formatDistanceToNow } from 'date-fns';

interface DeviceManagementProps {
  onDeviceRegistered?: () => void;
  onDeviceRemoved?: (deviceId: string) => void;
}

const DeviceTypeIcon = ({ type, className = "h-5 w-5" }: { type: string; className?: string }) => {
  switch (type) {
    case 'mobile':
      return <DevicePhoneMobileIcon className={className} />;
    case 'desktop':
      return <ComputerDesktopIcon className={className} />;
    case 'tablet':
      return <DeviceTabletIcon className={className} />;
    default:
      return <GlobeAltIcon className={className} />;
  }
};

const SecurityLevelBadge = ({ level, score }: { level: string; score?: number }) => {
  const getVariant = (level: string) => {
    switch (level) {
      case 'maximum':
        return 'default'; // green
      case 'high':
        return 'secondary'; // blue  
      case 'medium':
        return 'outline'; // yellow
      case 'low':
        return 'destructive'; // red
      default:
        return 'outline';
    }
  };

  const getColor = (level: string) => {
    switch (level) {
      case 'maximum':
        return 'text-green-700 bg-green-50 border-green-200';
      case 'high':
        return 'text-blue-700 bg-blue-50 border-blue-200';
      case 'medium':
        return 'text-yellow-700 bg-yellow-50 border-yellow-200';
      case 'low':
        return 'text-red-700 bg-red-50 border-red-200';
      default:
        return 'text-gray-700 bg-gray-50 border-gray-200';
    }
  };

  return (
    <Badge className={getColor(level)}>
      {level.toUpperCase()}
      {score && ` (${score})`}
    </Badge>
  );
};

export default function DeviceManagement({ onDeviceRegistered, onDeviceRemoved }: DeviceManagementProps) {
  const [devices, setDevices] = useState<DeviceInfo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedDevice, setSelectedDevice] = useState<DeviceInfo | null>(null);
  const [securityReport, setSecurityReport] = useState<SecurityReport | null>(null);
  const [showRemoveDialog, setShowRemoveDialog] = useState<string | null>(null);
  const [showTrustDialog, setShowTrustDialog] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  useEffect(() => {
    loadDevices();
  }, []);

  const loadDevices = async () => {
    try {
      setLoading(true);
      const deviceList = await multiDeviceE2EEService.getUserDevices();
      setDevices(deviceList);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load devices');
    } finally {
      setLoading(false);
    }
  };

  const loadSecurityReport = async () => {
    try {
      const report = await multiDeviceE2EEService.getDeviceSecurityReport();
      setSecurityReport(report);
    } catch (err) {
      console.error('Failed to load security report:', err);
    }
  };

  const handleTrustDevice = async (deviceId: string) => {
    try {
      setActionLoading(deviceId);
      await multiDeviceE2EEService.trustDevice(deviceId);
      await loadDevices();
      setShowTrustDialog(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to trust device');
    } finally {
      setActionLoading(null);
    }
  };

  const handleRemoveDevice = async (deviceId: string) => {
    try {
      setActionLoading(deviceId);
      await multiDeviceE2EEService.removeDevice(deviceId);
      await loadDevices();
      setShowRemoveDialog(null);
      onDeviceRemoved?.(deviceId);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to remove device');
    } finally {
      setActionLoading(null);
    }
  };

  const handleShareKeys = async (deviceId: string) => {
    try {
      setActionLoading(deviceId);
      await multiDeviceE2EEService.shareKeysWithDevice(deviceId);
      await loadDevices();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to share keys');
    } finally {
      setActionLoading(null);
    }
  };

  const handleRotateKeys = async () => {
    try {
      setActionLoading('rotate');
      // For now, we'll just rotate keys for the current device
      // In a real implementation, you'd need to specify which conversations
      await Promise.resolve(); // Placeholder
      await loadDevices();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to rotate keys');
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {error && (
        <Alert variant="destructive">
          <ExclamationTriangleIcon className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Tabs defaultValue="devices" className="w-full">
        <TabsList className="grid w-full grid-cols-2">
          <TabsTrigger value="devices">My Devices</TabsTrigger>
          <TabsTrigger value="security" onClick={loadSecurityReport}>Security Report</TabsTrigger>
        </TabsList>
        
        <TabsContent value="devices" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h3 className="text-lg font-semibold">Connected Devices</h3>
              <p className="text-sm text-gray-600">Manage devices that can access your encrypted messages</p>
            </div>
            <Button onClick={loadDevices} variant="outline" size="sm">
              <ArrowPathIcon className="h-4 w-4 mr-2" />
              Refresh
            </Button>
          </div>

          <div className="grid gap-4">
            {devices.map((device) => (
              <Card key={device.id} className="relative">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <div className="flex items-center space-x-3">
                      <DeviceTypeIcon type={device.type} className="h-6 w-6 text-gray-600" />
                      <div>
                        <CardTitle className="text-base">{device.name}</CardTitle>
                        <CardDescription className="flex items-center space-x-2">
                          <span>{device.platform}</span>
                          <span>•</span>
                          <span className="text-xs font-mono">{device.fingerprint}</span>
                        </CardDescription>
                      </div>
                    </div>
                    <div className="flex items-center space-x-2">
                      {device.isTrusted ? (
                        <ShieldCheckIcon className="h-5 w-5 text-green-500" title="Trusted Device" />
                      ) : (
                        <ShieldExclamationIcon className="h-5 w-5 text-yellow-500" title="Untrusted Device" />
                      )}
                      <SecurityLevelBadge level={device.securityLevel || 'medium'} />
                    </div>
                  </div>
                </CardHeader>
                
                <CardContent className="pt-0">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4 text-sm text-gray-600">
                      <div className="flex items-center">
                        <ClockIcon className="h-4 w-4 mr-1" />
                        {device.lastUsed 
                          ? `Last used ${formatDistanceToNow(device.lastUsed)} ago`
                          : 'Never used'
                        }
                      </div>
                      {device.verifiedAt && (
                        <div className="flex items-center">
                          <CheckCircleIcon className="h-4 w-4 mr-1 text-green-500" />
                          Verified {formatDistanceToNow(device.verifiedAt)} ago
                        </div>
                      )}
                    </div>
                    
                    <div className="flex space-x-2">
                      {!device.isTrusted && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setShowTrustDialog(device.id!)}
                          disabled={actionLoading === device.id}
                        >
                          <ShieldCheckIcon className="h-4 w-4 mr-1" />
                          Trust
                        </Button>
                      )}
                      
                      {device.isTrusted && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleShareKeys(device.id!)}
                          disabled={actionLoading === device.id}
                        >
                          <SignalIcon className="h-4 w-4 mr-1" />
                          Share Keys
                        </Button>
                      )}
                      
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setShowRemoveDialog(device.id!)}
                        disabled={actionLoading === device.id}
                      >
                        <TrashIcon className="h-4 w-4 mr-1 text-red-500" />
                        Remove
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>

          {devices.length === 0 && (
            <Card>
              <CardContent className="flex flex-col items-center justify-center h-32">
                <DevicePhoneMobileIcon className="h-8 w-8 text-gray-400 mb-2" />
                <p className="text-gray-600">No devices registered</p>
              </CardContent>
            </Card>
          )}
        </TabsContent>
        
        <TabsContent value="security" className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h3 className="text-lg font-semibold">Security Report</h3>
              <p className="text-sm text-gray-600">View security status and recommendations</p>
            </div>
            <Button
              onClick={handleRotateKeys}
              variant="outline"
              size="sm"
              disabled={actionLoading === 'rotate'}
            >
              <ArrowPathIcon className="h-4 w-4 mr-2" />
              Rotate Keys
            </Button>
          </div>

          {securityReport ? (
            <div className="space-y-4">
              {/* Security Score */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Security Score</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center space-x-4">
                    <div className="flex-1">
                      <Progress 
                        value={securityReport.integrityReport.securityScore} 
                        className="h-2" 
                      />
                    </div>
                    <div className="text-2xl font-bold">
                      {securityReport.integrityReport.securityScore}/100
                    </div>
                  </div>
                  <p className="text-sm text-gray-600 mt-2">
                    Status: <span className="font-medium capitalize">{securityReport.integrityReport.status}</span>
                  </p>
                </CardContent>
              </Card>

              {/* Issues */}
              {securityReport.integrityReport.issues.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base text-red-700">Security Issues</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-3">
                      {securityReport.integrityReport.issues.map((issue, index) => (
                        <Alert key={index} variant={issue.severity === 'high' ? 'destructive' : 'default'}>
                          <ExclamationTriangleIcon className="h-4 w-4" />
                          <AlertDescription>
                            <div className="flex justify-between">
                              <span>{issue.message}</span>
                              <Badge variant={issue.severity === 'high' ? 'destructive' : 'outline'}>
                                {issue.severity}
                              </Badge>
                            </div>
                          </AlertDescription>
                        </Alert>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Recommendations */}
              {securityReport.integrityReport.recommendations.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base">Recommendations</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <ul className="space-y-2">
                      {securityReport.integrityReport.recommendations.map((recommendation, index) => (
                        <li key={index} className="flex items-center space-x-2">
                          <CheckCircleIcon className="h-4 w-4 text-blue-500 flex-shrink-0" />
                          <span className="text-sm">{recommendation}</span>
                        </li>
                      ))}
                    </ul>
                  </CardContent>
                </Card>
              )}

              {/* Encryption Summary */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Encryption Summary</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p className="text-gray-600">Active Conversation Keys</p>
                      <p className="font-medium">{securityReport.encryptionSummary.activeConversationKeys}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Pending Key Shares</p>
                      <p className="font-medium">{securityReport.encryptionSummary.pendingKeyShares}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Encryption Version</p>
                      <p className="font-medium">v{securityReport.encryptionSummary.encryptionVersion}</p>
                    </div>
                    <div>
                      <p className="text-gray-600">Key Rotation Required</p>
                      <p className={`font-medium ${securityReport.encryptionSummary.requiresKeyRotation ? 'text-red-600' : 'text-green-600'}`}>
                        {securityReport.encryptionSummary.requiresKeyRotation ? 'Yes' : 'No'}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          ) : (
            <Card>
              <CardContent className="flex items-center justify-center h-32">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
              </CardContent>
            </Card>
          )}
        </TabsContent>
      </Tabs>

      {/* Trust Device Dialog */}
      <Dialog open={!!showTrustDialog} onOpenChange={() => setShowTrustDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Trust Device</DialogTitle>
            <DialogDescription>
              Are you sure you want to trust this device? Trusted devices can access all your encrypted conversations.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowTrustDialog(null)}>
              Cancel
            </Button>
            <Button 
              onClick={() => showTrustDialog && handleTrustDevice(showTrustDialog)}
              disabled={!!actionLoading}
            >
              Trust Device
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Remove Device Dialog */}
      <Dialog open={!!showRemoveDialog} onOpenChange={() => setShowRemoveDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Remove Device</DialogTitle>
            <DialogDescription>
              Are you sure you want to remove this device? This action cannot be undone and the device will lose access to all encrypted conversations.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowRemoveDialog(null)}>
              Cancel
            </Button>
            <Button 
              variant="destructive"
              onClick={() => showRemoveDialog && handleRemoveDevice(showRemoveDialog)}
              disabled={!!actionLoading}
            >
              Remove Device
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}