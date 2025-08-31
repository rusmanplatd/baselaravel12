import React, { useEffect, useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { 
  Shield, 
  AlertCircle, 
  CheckCircle, 
  RefreshCw, 
  Zap, 
  Smartphone,
  Laptop,
  Monitor,
  Globe,
  Settings,
  Trash2,
  Plus,
  ArrowUpCircle,
  Clock
} from 'lucide-react';
import { useQuantumE2EE } from '@/hooks/useQuantumE2EE';
import { QuantumStatusBadge } from './quantum-status-badge';
import { cn } from '@/lib/utils';

interface QuantumDeviceManagerProps {
  className?: string;
  showAddDevice?: boolean;
}

export function QuantumDeviceManager({
  className,
  showAddDevice = true
}: QuantumDeviceManagerProps) {
  const {
    quantumStatus,
    deviceStatus,
    isLoading,
    error,
    getQuantumCapableDevices,
    registerQuantumDevice,
    updateDeviceCapabilities,
    migrateDeviceToQuantum,
    checkQuantumHealth
  } = useQuantumE2EE();

  const [devices, setDevices] = useState<any[]>([]);
  const [selectedDevice, setSelectedDevice] = useState<string | null>(null);
  const [managingDevice, setManagingDevice] = useState<string | null>(null);

  useEffect(() => {
    loadDevices();
  }, [quantumStatus]);

  const loadDevices = async () => {
    try {
      const deviceList = await getQuantumCapableDevices();
      setDevices(deviceList);
    } catch (error) {
      console.error('Failed to load devices:', error);
    }
  };

  const getDeviceIcon = (deviceType: string) => {
    switch (deviceType) {
      case 'mobile':
        return <Smartphone className="h-4 w-4" />;
      case 'desktop':
        return <Monitor className="h-4 w-4" />;
      case 'tablet':
        return <Laptop className="h-4 w-4" />;
      default:
        return <Globe className="h-4 w-4" />;
    }
  };

  const getDeviceStatusColor = (device: any) => {
    if (device.quantumReady) {
      return 'border-green-200 bg-green-50';
    }
    if (device.encryptionVersion >= 3) {
      return 'border-amber-200 bg-amber-50';
    }
    return 'border-gray-200 bg-gray-50';
  };

  const handleRegisterDevice = async () => {
    setManagingDevice('new');
    try {
      await registerQuantumDevice(['ml-kem-768']);
      await loadDevices();
    } catch (error) {
      console.error('Device registration failed:', error);
    } finally {
      setManagingDevice(null);
    }
  };

  const handleUpgradeDevice = async (deviceId: string) => {
    setManagingDevice(deviceId);
    try {
      await migrateDeviceToQuantum(deviceId);
      await loadDevices();
    } catch (error) {
      console.error('Device upgrade failed:', error);
    } finally {
      setManagingDevice(null);
    }
  };

  const handleUpdateCapabilities = async (deviceId: string, capabilities: string[]) => {
    setManagingDevice(deviceId);
    try {
      await updateDeviceCapabilities(deviceId, capabilities);
      await loadDevices();
    } catch (error) {
      console.error('Capability update failed:', error);
    } finally {
      setManagingDevice(null);
    }
  };

  const handleRefresh = async () => {
    try {
      await checkQuantumHealth();
      await loadDevices();
    } catch (error) {
      console.error('Refresh failed:', error);
    }
  };

  const getSecurityLevelInfo = (device: any) => {
    if (device.quantumReady) {
      return {
        level: 'Quantum-Safe',
        color: 'text-green-600',
        description: 'Protected against quantum attacks'
      };
    }
    if (device.encryptionVersion >= 3) {
      return {
        level: 'Modern',
        color: 'text-amber-600',
        description: 'Classical encryption with quantum readiness'
      };
    }
    return {
      level: 'Legacy',
      color: 'text-gray-600',
      description: 'Vulnerable to quantum attacks'
    };
  };

  return (
    <Card className={cn('w-full', className)}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-lg">Quantum Device Management</CardTitle>
            <CardDescription>
              Manage devices and their quantum cryptography capabilities
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant="outline" className="text-sm">
              {devices.length} device{devices.length !== 1 ? 's' : ''}
            </Badge>
            <Button
              variant="ghost"
              size="icon"
              onClick={handleRefresh}
              disabled={isLoading}
              className="h-8 w-8"
            >
              <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} />
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        {/* Overall Status */}
        <div className="grid grid-cols-3 gap-4 p-4 bg-muted/30 rounded-lg">
          <div className="text-center">
            <div className="text-2xl font-bold text-green-600">
              {deviceStatus.quantumReadyDevices}
            </div>
            <div className="text-sm text-muted-foreground">Quantum Ready</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold">
              {Math.round(deviceStatus.quantumReadinessPercentage)}%
            </div>
            <div className="text-sm text-muted-foreground">Readiness</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-blue-600">
              {deviceStatus.totalDevices}
            </div>
            <div className="text-sm text-muted-foreground">Total Devices</div>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="space-y-2">
          <div className="flex justify-between text-sm">
            <span>Quantum Migration Progress</span>
            <span>{Math.round(deviceStatus.quantumReadinessPercentage)}%</span>
          </div>
          <Progress 
            value={deviceStatus.quantumReadinessPercentage} 
            className="h-2"
          />
        </div>

        {/* Device List */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium">Registered Devices</h3>
            {showAddDevice && (
              <Button
                size="sm"
                onClick={handleRegisterDevice}
                disabled={isLoading || managingDevice === 'new'}
                className="flex items-center gap-2"
              >
                {managingDevice === 'new' ? (
                  <RefreshCw className="h-3 w-3 animate-spin" />
                ) : (
                  <Plus className="h-3 w-3" />
                )}
                Register Device
              </Button>
            )}
          </div>

          {devices.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              <Globe className="h-12 w-12 mx-auto mb-3 opacity-50" />
              <p className="text-sm">No quantum-capable devices registered</p>
              <p className="text-xs">Register your first device to get started</p>
            </div>
          ) : (
            <div className="space-y-2">
              {devices.map((device) => {
                const securityInfo = getSecurityLevelInfo(device);
                const isManaging = managingDevice === device.deviceId;

                return (
                  <div 
                    key={device.deviceId}
                    className={cn(
                      'p-4 rounded-lg border transition-all duration-200',
                      getDeviceStatusColor(device),
                      selectedDevice === device.deviceId && 'ring-2 ring-blue-500'
                    )}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        {getDeviceIcon(device.deviceType)}
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <h4 className="text-sm font-medium">{device.deviceName}</h4>
                            {device.quantumReady && (
                              <Zap className="h-3 w-3 text-blue-500" />
                            )}
                          </div>
                          <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span>{device.deviceType}</span>
                            <span>•</span>
                            <span>v{device.encryptionVersion}</span>
                            <span>•</span>
                            <span className={securityInfo.color}>
                              {securityInfo.level}
                            </span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <QuantumStatusBadge
                          algorithm={device.preferredAlgorithm || 'RSA-4096-OAEP'}
                          quantumResistant={device.quantumReady}
                          encryptionVersion={device.encryptionVersion}
                          size="sm"
                          showText={false}
                        />

                        {!device.quantumReady && (
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleUpgradeDevice(device.deviceId)}
                            disabled={isManaging}
                            className="flex items-center gap-1 h-7 text-xs"
                          >
                            {isManaging ? (
                              <RefreshCw className="h-3 w-3 animate-spin" />
                            ) : (
                              <ArrowUpCircle className="h-3 w-3" />
                            )}
                            Upgrade
                          </Button>
                        )}

                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => setSelectedDevice(
                            selectedDevice === device.deviceId ? null : device.deviceId
                          )}
                          className="h-7 w-7 p-0"
                        >
                          <Settings className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>

                    {/* Device Details */}
                    {selectedDevice === device.deviceId && (
                      <div className="mt-3 pt-3 border-t border-border/50 space-y-3">
                        <div className="grid grid-cols-2 gap-4 text-xs">
                          <div>
                            <div className="font-medium mb-1">Capabilities</div>
                            <div className="space-y-1">
                              {device.quantumCapabilities.map((cap: string) => (
                                <Badge key={cap} variant="outline" className="text-xs">
                                  {cap.toUpperCase()}
                                </Badge>
                              ))}
                            </div>
                          </div>
                          <div>
                            <div className="font-medium mb-1">Status</div>
                            <div className="space-y-1">
                              <div className={`flex items-center gap-1 ${securityInfo.color}`}>
                                {device.quantumReady ? (
                                  <CheckCircle className="h-3 w-3" />
                                ) : (
                                  <AlertCircle className="h-3 w-3" />
                                )}
                                <span>{securityInfo.description}</span>
                              </div>
                            </div>
                          </div>
                        </div>

                        {device.lastUsedAt && (
                          <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Clock className="h-3 w-3" />
                            <span>
                              Last used: {new Date(device.lastUsedAt).toLocaleDateString()}
                            </span>
                          </div>
                        )}

                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleUpdateCapabilities(device.deviceId, ['ml-kem-1024'])}
                            disabled={isManaging}
                            className="flex items-center gap-1 text-xs"
                          >
                            <Shield className="h-3 w-3" />
                            Enhance Security
                          </Button>
                          
                          <Button
                            size="sm"
                            variant="outline"
                            className="flex items-center gap-1 text-xs text-red-600 hover:text-red-700"
                          >
                            <Trash2 className="h-3 w-3" />
                            Remove
                          </Button>
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Migration Actions */}
        {deviceStatus.needsUpgrade && (
          <Alert className="border-amber-200 bg-amber-50">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription className="text-sm">
              Some devices need quantum security upgrades. 
              <Button
                variant="link"
                size="sm"
                className="px-2 h-auto text-sm underline"
                onClick={() => {
                  devices
                    .filter(d => !d.quantumReady)
                    .forEach(d => handleUpgradeDevice(d.deviceId));
                }}
              >
                Upgrade all devices
              </Button>
            </AlertDescription>
          </Alert>
        )}

        {error && (
          <Alert className="border-red-200 bg-red-50">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription className="text-sm">
              {error}
            </AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}