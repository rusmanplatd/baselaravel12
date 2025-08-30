import React, { useState, useEffect } from 'react';
import { 
  Shield, 
  Smartphone, 
  Monitor, 
  Tablet, 
  Globe,
  CheckCircle, 
  AlertCircle, 
  XCircle, 
  MoreVertical,
  Plus,
  Sync,
  RotateCcw,
  Eye,
  EyeOff,
  Copy,
  QrCode,
  Trash2
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { toast } from 'sonner';
import { useE2EE } from '@/hooks/useE2EE';
import { cn } from '@/lib/utils';
import type { DeviceInfo, MultiDeviceSecurityMetrics } from '@/services/QuantumMultiDeviceE2EE';

interface MultiDeviceManagerProps {
  userId: string;
  onClose?: () => void;
}

export function MultiDeviceManager({ userId, onClose }: MultiDeviceManagerProps) {
  const { 
    multiDeviceEnabled, 
    trustedDevices, 
    currentDevice,
    registerNewDevice,
    verifyDevice,
    revokeDevice,
    syncDeviceKeys,
    rotateDeviceKeys,
    getMultiDeviceMetrics,
    exportMultiDeviceAudit
  } = useE2EE(userId);

  const [metrics, setMetrics] = useState<MultiDeviceSecurityMetrics | null>(null);
  const [showAddDevice, setShowAddDevice] = useState(false);
  const [showVerification, setShowVerification] = useState<string | null>(null);
  const [verificationCode, setVerificationCode] = useState('');
  const [newDeviceForm, setNewDeviceForm] = useState({
    deviceName: '',
    deviceType: 'web' as const,
    platform: navigator.platform || 'Unknown'
  });
  const [loading, setLoading] = useState(false);
  const [syncing, setSyncing] = useState(false);

  useEffect(() => {
    if (multiDeviceEnabled) {
      loadMetrics();
    }
  }, [multiDeviceEnabled]);

  const loadMetrics = async () => {
    try {
      const deviceMetrics = await getMultiDeviceMetrics();
      setMetrics(deviceMetrics);
    } catch (error) {
      console.error('Failed to load multi-device metrics:', error);
    }
  };

  const handleAddDevice = async () => {
    if (!newDeviceForm.deviceName.trim()) {
      toast.error('Device name is required');
      return;
    }

    setLoading(true);
    try {
      const deviceId = await registerNewDevice({
        deviceName: newDeviceForm.deviceName.trim(),
        deviceType: newDeviceForm.deviceType,
        platform: newDeviceForm.platform,
        isTrusted: false,
        trustLevel: 0,
        verificationStatus: 'pending',
        quantumSecurityLevel: 8
      });

      setShowAddDevice(false);
      setShowVerification(deviceId);
      setNewDeviceForm({
        deviceName: '',
        deviceType: 'web',
        platform: navigator.platform || 'Unknown'
      });
      
      await loadMetrics();
      toast.success('Device registered. Please verify it on the new device.');
    } catch (error) {
      console.error('Failed to add device:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyDevice = async () => {
    if (!showVerification || !verificationCode.trim()) {
      toast.error('Verification code is required');
      return;
    }

    setLoading(true);
    try {
      const success = await verifyDevice(showVerification, verificationCode.trim());
      if (success) {
        setShowVerification(null);
        setVerificationCode('');
        await loadMetrics();
      }
    } catch (error) {
      console.error('Device verification failed:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleRevokeDevice = async (deviceId: string) => {
    if (window.confirm('Are you sure you want to revoke this device? This action cannot be undone.')) {
      try {
        await revokeDevice(deviceId);
        await loadMetrics();
      } catch (error) {
        console.error('Failed to revoke device:', error);
      }
    }
  };

  const handleSyncKeys = async (deviceId?: string) => {
    setSyncing(true);
    try {
      await syncDeviceKeys(deviceId);
      await loadMetrics();
      toast.success(deviceId ? 'Device keys synchronized' : 'All device keys synchronized');
    } catch (error) {
      console.error('Key sync failed:', error);
    } finally {
      setSyncing(false);
    }
  };

  const handleRotateKeys = async (deviceId?: string) => {
    try {
      await rotateDeviceKeys(deviceId);
      await loadMetrics();
      toast.success(deviceId ? 'Device keys rotated' : 'All device keys rotated');
    } catch (error) {
      console.error('Key rotation failed:', error);
    }
  };

  const handleExportAudit = async () => {
    try {
      const audit = await exportMultiDeviceAudit();
      const blob = new Blob([JSON.stringify(audit, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `multi-device-audit-${new Date().toISOString().split('T')[0]}.json`;
      link.click();
      URL.revokeObjectURL(url);
      toast.success('Multi-device audit exported');
    } catch (error) {
      console.error('Failed to export audit:', error);
    }
  };

  const getDeviceIcon = (deviceType: string) => {
    switch (deviceType) {
      case 'mobile': return Smartphone;
      case 'desktop': return Monitor;
      case 'tablet': return Tablet;
      case 'web': return Globe;
      default: return Monitor;
    }
  };

  const getStatusBadge = (device: DeviceInfo) => {
    if (device.isCurrentDevice) {
      return <Badge variant="secondary">Current Device</Badge>;
    }
    
    switch (device.verificationStatus) {
      case 'verified':
        return <Badge variant="default" className="bg-green-100 text-green-800">Verified</Badge>;
      case 'pending':
        return <Badge variant="secondary" className="bg-yellow-100 text-yellow-800">Pending</Badge>;
      case 'rejected':
        return <Badge variant="destructive">Rejected</Badge>;
      case 'expired':
        return <Badge variant="outline">Expired</Badge>;
      default:
        return <Badge variant="outline">Unknown</Badge>;
    }
  };

  const getTrustLevelColor = (trustLevel: number) => {
    if (trustLevel >= 9) return 'text-green-600';
    if (trustLevel >= 7) return 'text-blue-600';
    if (trustLevel >= 5) return 'text-yellow-600';
    return 'text-red-600';
  };

  if (!multiDeviceEnabled) {
    return (
      <div className="p-6 text-center">
        <Shield className="h-12 w-12 mx-auto mb-4 text-gray-400" />
        <h3 className="text-lg font-semibold mb-2">Multi-Device E2EE Not Available</h3>
        <p className="text-gray-600 mb-4">
          Multi-device end-to-end encryption is not enabled for this account.
        </p>
        <Button onClick={onClose} variant="outline">Close</Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Multi-Device Security</h2>
          <p className="text-gray-600">Manage your quantum-safe encrypted devices</p>
        </div>
        <div className="flex gap-2">
          <Button 
            variant="outline" 
            size="sm"
            onClick={() => handleSyncKeys()}
            disabled={syncing}
          >
            <Sync className={cn("h-4 w-4 mr-2", syncing && "animate-spin")} />
            Sync All
          </Button>
          <Button 
            variant="outline" 
            size="sm"
            onClick={handleExportAudit}
          >
            Export Audit
          </Button>
        </div>
      </div>

      {/* Security Metrics */}
      {metrics && (
        <Card>
          <CardHeader>
            <CardTitle>Security Overview</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600">{metrics.totalDevices}</div>
                <div className="text-sm text-gray-600">Total Devices</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600">{metrics.trustedDevices}</div>
                <div className="text-sm text-gray-600">Trusted Devices</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-purple-600">{metrics.activeDevices}</div>
                <div className="text-sm text-gray-600">Active Devices</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-indigo-600">
                  {Math.round(metrics.quantumReadinessScore)}/10
                </div>
                <div className="text-sm text-gray-600">Quantum Ready</div>
              </div>
            </div>
            
            <div className="mt-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span>Average Trust Level</span>
                <span className={getTrustLevelColor(metrics.averageTrustLevel)}>
                  {metrics.averageTrustLevel.toFixed(1)}/10
                </span>
              </div>
              <Progress value={metrics.averageTrustLevel * 10} className="h-2" />
              
              <div className="flex justify-between text-sm">
                <span>Key Consistency</span>
                <span className="text-green-600">{metrics.keyConsistencyScore.toFixed(1)}/10</span>
              </div>
              <Progress value={metrics.keyConsistencyScore * 10} className="h-2" />
            </div>

            {metrics.crossDeviceThreats > 0 && (
              <Alert className="mt-4">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  {metrics.crossDeviceThreats} potential security threat(s) detected across devices.
                </AlertDescription>
              </Alert>
            )}
          </CardContent>
        </Card>
      )}

      {/* Device List */}
      <Tabs defaultValue="devices">
        <TabsList>
          <TabsTrigger value="devices">Devices ({trustedDevices.length})</TabsTrigger>
          <TabsTrigger value="pending">Pending Verification</TabsTrigger>
        </TabsList>

        <TabsContent value="devices" className="space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="text-lg font-semibold">Trusted Devices</h3>
            <Dialog open={showAddDevice} onOpenChange={setShowAddDevice}>
              <DialogTrigger asChild>
                <Button size="sm">
                  <Plus className="h-4 w-4 mr-2" />
                  Add Device
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Add New Device</DialogTitle>
                  <DialogDescription>
                    Register a new device for quantum-safe E2EE access.
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                  <div>
                    <Label htmlFor="deviceName">Device Name</Label>
                    <Input
                      id="deviceName"
                      value={newDeviceForm.deviceName}
                      onChange={(e) => setNewDeviceForm(prev => ({
                        ...prev,
                        deviceName: e.target.value
                      }))}
                      placeholder="My iPhone, Work Laptop, etc."
                    />
                  </div>
                  <div>
                    <Label htmlFor="deviceType">Device Type</Label>
                    <select
                      id="deviceType"
                      className="w-full p-2 border rounded-md"
                      value={newDeviceForm.deviceType}
                      onChange={(e) => setNewDeviceForm(prev => ({
                        ...prev,
                        deviceType: e.target.value as any
                      }))}
                    >
                      <option value="web">Web Browser</option>
                      <option value="desktop">Desktop App</option>
                      <option value="mobile">Mobile App</option>
                      <option value="tablet">Tablet App</option>
                    </select>
                  </div>
                  <div>
                    <Label htmlFor="platform">Platform</Label>
                    <Input
                      id="platform"
                      value={newDeviceForm.platform}
                      onChange={(e) => setNewDeviceForm(prev => ({
                        ...prev,
                        platform: e.target.value
                      }))}
                      placeholder="iOS, Android, Windows, etc."
                    />
                  </div>
                  <div className="flex gap-2 pt-4">
                    <Button onClick={handleAddDevice} disabled={loading} className="flex-1">
                      {loading ? 'Registering...' : 'Register Device'}
                    </Button>
                    <Button 
                      variant="outline" 
                      onClick={() => setShowAddDevice(false)}
                      disabled={loading}
                    >
                      Cancel
                    </Button>
                  </div>
                </div>
              </DialogContent>
            </Dialog>
          </div>

          <div className="space-y-3">
            {trustedDevices.map((device) => {
              const DeviceIcon = getDeviceIcon(device.deviceType);
              const isOnline = Date.now() - device.lastSeen.getTime() < 300000; // 5 minutes

              return (
                <Card key={device.deviceId} className={cn(
                  "transition-all duration-200",
                  device.isCurrentDevice && "ring-2 ring-blue-500"
                )}>
                  <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="relative">
                          <DeviceIcon className="h-6 w-6 text-gray-600" />
                          {isOnline && (
                            <div className="absolute -top-1 -right-1 h-3 w-3 bg-green-500 rounded-full" />
                          )}
                        </div>
                        <div>
                          <div className="font-medium">{device.deviceName}</div>
                          <div className="text-sm text-gray-600 flex items-center gap-2">
                            <span>{device.platform}</span>
                            <span>•</span>
                            <span>Last seen: {device.lastSeen.toLocaleDateString()}</span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-3">
                        <div className="text-right">
                          <div className="flex items-center gap-2">
                            {getStatusBadge(device)}
                            <div className={cn(
                              "text-sm font-medium",
                              getTrustLevelColor(device.trustLevel)
                            )}>
                              Trust: {device.trustLevel}/10
                            </div>
                          </div>
                          <div className="text-xs text-gray-600 mt-1">
                            Quantum Level: {device.quantumSecurityLevel}/10
                          </div>
                        </div>

                        {!device.isCurrentDevice && (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="sm">
                                <MoreVertical className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                              <DropdownMenuItem onClick={() => handleSyncKeys(device.deviceId)}>
                                <Sync className="h-4 w-4 mr-2" />
                                Sync Keys
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => handleRotateKeys(device.deviceId)}>
                                <RotateCcw className="h-4 w-4 mr-2" />
                                Rotate Keys
                              </DropdownMenuItem>
                              <DropdownMenuItem 
                                onClick={() => handleRevokeDevice(device.deviceId)}
                                className="text-red-600"
                              >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Revoke Device
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        </TabsContent>

        <TabsContent value="pending">
          <Card>
            <CardHeader>
              <CardTitle>Pending Device Verification</CardTitle>
              <CardDescription>
                Devices waiting for verification will appear here.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {trustedDevices.filter(d => d.verificationStatus === 'pending').length === 0 ? (
                <p className="text-gray-600 text-center py-8">
                  No devices pending verification.
                </p>
              ) : (
                <div className="space-y-3">
                  {trustedDevices
                    .filter(d => d.verificationStatus === 'pending')
                    .map((device) => (
                      <div key={device.deviceId} className="flex items-center justify-between p-3 border rounded-lg">
                        <div>
                          <div className="font-medium">{device.deviceName}</div>
                          <div className="text-sm text-gray-600">{device.platform}</div>
                        </div>
                        <Button 
                          size="sm" 
                          onClick={() => setShowVerification(device.deviceId)}
                        >
                          Verify
                        </Button>
                      </div>
                    ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Verification Dialog */}
      <Dialog open={!!showVerification} onOpenChange={() => setShowVerification(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Verify Device</DialogTitle>
            <DialogDescription>
              Enter the verification code displayed on the device you want to verify.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="verificationCode">Verification Code</Label>
              <Input
                id="verificationCode"
                value={verificationCode}
                onChange={(e) => setVerificationCode(e.target.value)}
                placeholder="Enter 6-digit code"
                maxLength={6}
              />
            </div>
            <div className="flex gap-2 pt-4">
              <Button onClick={handleVerifyDevice} disabled={loading} className="flex-1">
                {loading ? 'Verifying...' : 'Verify Device'}
              </Button>
              <Button 
                variant="outline" 
                onClick={() => {
                  setShowVerification(null);
                  setVerificationCode('');
                }}
                disabled={loading}
              >
                Cancel
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}