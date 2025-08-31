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
  Globe
} from 'lucide-react';
import { useQuantumE2EE } from '@/hooks/useQuantumE2EE';
import { cn } from '@/lib/utils';

interface QuantumHealthIndicatorProps {
  className?: string;
  showDetails?: boolean;
  autoRefresh?: boolean;
  refreshInterval?: number;
}

export function QuantumHealthIndicator({
  className,
  showDetails = true,
  autoRefresh = true,
  refreshInterval = 60000
}: QuantumHealthIndicatorProps) {
  const {
    quantumStatus,
    deviceStatus,
    isLoading,
    error,
    checkQuantumHealth,
    initializeQuantumSupport,
    getQuantumCapableDevices
  } = useQuantumE2EE();

  const [devices, setDevices] = useState<any[]>([]);
  const [lastCheck, setLastCheck] = useState<Date | null>(null);

  useEffect(() => {
    if (autoRefresh) {
      const interval = setInterval(async () => {
        await checkQuantumHealth();
        setLastCheck(new Date());
      }, refreshInterval);

      return () => clearInterval(interval);
    }
  }, [autoRefresh, refreshInterval, checkQuantumHealth]);

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

  const getHealthStatus = () => {
    if (error) return 'error';
    if (!quantumStatus.quantumAvailable) return 'unavailable';
    if (quantumStatus.quantumReady) return 'healthy';
    if (deviceStatus.quantumReadinessPercentage > 0) return 'partial';
    return 'not-ready';
  };

  const getHealthIcon = () => {
    const status = getHealthStatus();
    switch (status) {
      case 'healthy':
        return <CheckCircle className="h-5 w-5 text-green-500" />;
      case 'partial':
        return <Shield className="h-5 w-5 text-amber-500" />;
      case 'error':
      case 'unavailable':
        return <AlertCircle className="h-5 w-5 text-red-500" />;
      default:
        return <Shield className="h-5 w-5 text-gray-500" />;
    }
  };

  const getHealthColor = () => {
    const status = getHealthStatus();
    switch (status) {
      case 'healthy':
        return 'border-green-200 bg-green-50';
      case 'partial':
        return 'border-amber-200 bg-amber-50';
      case 'error':
      case 'unavailable':
        return 'border-red-200 bg-red-50';
      default:
        return 'border-gray-200 bg-gray-50';
    }
  };

  const getStatusMessage = () => {
    const status = getHealthStatus();
    switch (status) {
      case 'healthy':
        return 'Quantum cryptography is fully operational';
      case 'partial':
        return `${deviceStatus.quantumReadyDevices}/${deviceStatus.totalDevices} devices quantum-ready`;
      case 'error':
        return error || 'Quantum cryptography error detected';
      case 'unavailable':
        return 'Quantum cryptography not available';
      default:
        return 'Setting up quantum cryptography...';
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

  const handleRefresh = async () => {
    try {
      await checkQuantumHealth();
      await loadDevices();
      setLastCheck(new Date());
    } catch (error) {
      console.error('Refresh failed:', error);
    }
  };

  const handleInitialize = async () => {
    try {
      await initializeQuantumSupport();
      await loadDevices();
    } catch (error) {
      console.error('Initialization failed:', error);
    }
  };

  return (
    <Card className={cn('w-full', getHealthColor(), className)}>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            {getHealthIcon()}
            <div>
              <CardTitle className="text-lg">Quantum Security Status</CardTitle>
              <CardDescription className="text-sm">
                {getStatusMessage()}
              </CardDescription>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant={quantumStatus.quantumReady ? 'default' : 'secondary'}>
              {quantumStatus.currentAlgorithm || 'Not configured'}
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

      {showDetails && (
        <CardContent className="space-y-4">
          {/* Device Readiness Progress */}
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span>Device Readiness</span>
              <span>{deviceStatus.quantumReadinessPercentage.toFixed(0)}%</span>
            </div>
            <Progress 
              value={deviceStatus.quantumReadinessPercentage} 
              className="h-2"
            />
            <div className="text-xs text-muted-foreground">
              {deviceStatus.quantumReadyDevices} of {deviceStatus.totalDevices} devices quantum-ready
            </div>
          </div>

          {/* System Status */}
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div className="space-y-1">
              <div className="font-medium">System Status</div>
              <div className="flex items-center gap-2">
                {quantumStatus.quantumAvailable ? (
                  <CheckCircle className="h-3 w-3 text-green-500" />
                ) : (
                  <AlertCircle className="h-3 w-3 text-red-500" />
                )}
                <span className="text-xs">
                  {quantumStatus.quantumAvailable ? 'Available' : 'Unavailable'}
                </span>
              </div>
            </div>
            <div className="space-y-1">
              <div className="font-medium">Recommended</div>
              <div className="text-xs font-mono">
                {deviceStatus.recommendedAlgorithm}
              </div>
            </div>
          </div>

          {/* Device List */}
          {devices.length > 0 && (
            <div className="space-y-2">
              <div className="font-medium text-sm">Quantum-Ready Devices</div>
              <div className="space-y-2">
                {devices.map((device) => (
                  <div 
                    key={device.deviceId}
                    className="flex items-center justify-between p-2 bg-background rounded border"
                  >
                    <div className="flex items-center gap-2">
                      {getDeviceIcon(device.deviceType)}
                      <div>
                        <div className="text-sm font-medium">{device.deviceName}</div>
                        <div className="text-xs text-muted-foreground">
                          {device.deviceType} • v{device.encryptionVersion}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {device.quantumReady && (
                        <Zap className="h-3 w-3 text-blue-500" />
                      )}
                      <Badge 
                        variant={device.quantumReady ? 'default' : 'secondary'}
                        className="text-xs"
                      >
                        {device.quantumReady ? 'Ready' : 'Classic'}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-2 pt-2">
            {!quantumStatus.quantumReady && (
              <Button 
                size="sm" 
                onClick={handleInitialize}
                disabled={isLoading}
                className="flex items-center gap-2"
              >
                <Zap className="h-3 w-3" />
                Enable Quantum Protection
              </Button>
            )}
            
            {deviceStatus.needsUpgrade && (
              <Button 
                variant="outline" 
                size="sm"
                className="flex items-center gap-2"
              >
                <Shield className="h-3 w-3" />
                Upgrade Devices
              </Button>
            )}
          </div>

          {/* Error Display */}
          {error && (
            <Alert className="border-red-200 bg-red-50">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription className="text-sm">
                {error}
              </AlertDescription>
            </Alert>
          )}

          {/* Last Check */}
          {lastCheck && (
            <div className="text-xs text-muted-foreground text-right">
              Last checked: {lastCheck.toLocaleTimeString()}
            </div>
          )}
        </CardContent>
      )}
    </Card>
  );
}