import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  DevicePhoneMobileIcon, 
  ComputerDesktopIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  TrashIcon,
  ShieldCheckIcon
} from '@heroicons/react/24/outline';

interface Device {
  id: string;
  name: string;
  type: 'desktop' | 'mobile' | 'tablet';
  trusted: boolean;
  lastUsed: string;
  status: 'active' | 'pending' | 'revoked';
}

interface DeviceManagementDialogProps {
  isOpen: boolean;
  onClose: () => void;
  devices: Device[];
  onTrustDevice: (deviceId: string) => void;
  onRevokeDevice: (deviceId: string) => void;
  onRotateKeys: () => void;
}

export default function DeviceManagementDialog({
  isOpen,
  onClose,
  devices = [
    { id: '1', name: 'iPhone 15 Pro', type: 'mobile', trusted: true, lastUsed: '2 minutes ago', status: 'active' },
    { id: '2', name: 'MacBook Pro', type: 'desktop', trusted: false, lastUsed: '1 hour ago', status: 'pending' },
  ],
  onTrustDevice,
  onRevokeDevice,
  onRotateKeys
}: DeviceManagementDialogProps) {
  const [selectedDevice, setSelectedDevice] = useState<string | null>(null);
  const [showTrustDialog, setShowTrustDialog] = useState(false);
  const [showRemoveDialog, setShowRemoveDialog] = useState(false);
  
  const getDeviceIcon = (type: string) => {
    switch (type) {
      case 'mobile':
        return <DevicePhoneMobileIcon className="h-5 w-5" />;
      default:
        return <ComputerDesktopIcon className="h-5 w-5" />;
    }
  };

  const getStatusBadge = (device: Device) => {
    switch (device.status) {
      case 'active':
        return <Badge variant="success">Active</Badge>;
      case 'pending':
        return <Badge variant="warning">Pending</Badge>;
      case 'revoked':
        return <Badge variant="destructive">Revoked</Badge>;
    }
  };

  const calculateSecurityScore = () => {
    const trustedDevices = devices.filter(d => d.trusted).length;
    const totalDevices = devices.length;
    return Math.max(50, 100 - (totalDevices - trustedDevices) * 10);
  };

  return (
    <>
      <Dialog open={isOpen} onOpenChange={onClose}>
        <DialogContent 
          className="sm:max-w-2xl max-h-[80vh] overflow-y-auto"
          data-testid="device-management-dialog"
        >
          <DialogHeader>
            <DialogTitle>Device Management</DialogTitle>
          </DialogHeader>
          
          <Tabs defaultValue="devices" className="w-full">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="devices">Devices</TabsTrigger>
              <TabsTrigger value="security" data-testid="security-tab">Security</TabsTrigger>
            </TabsList>
            
            <TabsContent value="devices" className="space-y-4">
              <div className="space-y-3">
                {devices.map((device) => (
                  <div 
                    key={device.id} 
                    className="border rounded-lg p-4 hover:bg-gray-50"
                    data-testid="device-card"
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-3">
                        {getDeviceIcon(device.type)}
                        <div>
                          <h4 className="font-medium">{device.name}</h4>
                          <p className="text-sm text-gray-500">Last used {device.lastUsed}</p>
                        </div>
                      </div>
                      
                      <div className="flex items-center space-x-2">
                        {device.trusted && (
                          <CheckCircleIcon className="h-5 w-5 text-green-500" />
                        )}
                        {getStatusBadge(device)}
                        
                        {!device.trusted && device.status === 'pending' && (
                          <Button
                            size="sm"
                            onClick={() => {
                              setSelectedDevice(device.id);
                              setShowTrustDialog(true);
                            }}
                          >
                            Trust
                          </Button>
                        )}
                        
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => {
                            setSelectedDevice(device.id);
                            setShowRemoveDialog(true);
                          }}
                        >
                          <TrashIcon className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
              
              <div className="flex justify-between pt-4 border-t">
                <Button onClick={onRotateKeys} variant="outline">
                  Rotate Keys
                </Button>
                <Button onClick={onClose} data-testid="close-dialog">
                  Close
                </Button>
              </div>
            </TabsContent>
            
            <TabsContent value="security" className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="bg-green-50 p-4 rounded-lg text-center">
                  <div className="text-2xl font-bold text-green-600" data-testid="security-score">
                    {calculateSecurityScore()}
                  </div>
                  <div className="text-sm text-green-600" data-testid="security-status">
                    {calculateSecurityScore() >= 90 ? 'Excellent' : 'Good'} Security
                  </div>
                </div>
                
                <div className="bg-blue-50 p-4 rounded-lg">
                  <ShieldCheckIcon className="h-8 w-8 text-blue-600 mx-auto mb-2" />
                  <div className="text-sm text-blue-600 text-center">
                    E2EE Enabled
                  </div>
                </div>
              </div>
              
              <div className="space-y-3">
                <h4 className="font-medium">Security Recommendations</h4>
                <ul className="text-sm text-gray-600 space-y-2">
                  <li className="flex items-center">
                    <CheckCircleIcon className="h-4 w-4 text-green-500 mr-2" />
                    Regular key rotation enabled
                  </li>
                  <li className="flex items-center">
                    <CheckCircleIcon className="h-4 w-4 text-green-500 mr-2" />
                    All devices use strong encryption
                  </li>
                  <li className="flex items-center">
                    <ExclamationTriangleIcon className="h-4 w-4 text-yellow-500 mr-2" />
                    Review untrusted devices regularly
                  </li>
                </ul>
              </div>
            </TabsContent>
          </Tabs>
        </DialogContent>
      </Dialog>

      {/* Trust Device Dialog */}
      <Dialog open={showTrustDialog} onOpenChange={setShowTrustDialog}>
        <DialogContent data-testid="trust-device-dialog">
          <DialogHeader>
            <DialogTitle>Trust Device</DialogTitle>
          </DialogHeader>
          <p className="text-gray-600 mb-4">
            Are you sure you want to trust this device? Trusted devices can access all your encrypted conversations.
          </p>
          <div className="flex justify-end space-x-2">
            <Button variant="outline" onClick={() => setShowTrustDialog(false)}>
              Cancel
            </Button>
            <Button 
              onClick={() => {
                if (selectedDevice) {
                  onTrustDevice(selectedDevice);
                  setShowTrustDialog(false);
                  setSelectedDevice(null);
                }
              }}
            >
              Trust Device
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Remove Device Dialog */}
      <Dialog open={showRemoveDialog} onOpenChange={setShowRemoveDialog}>
        <DialogContent data-testid="remove-device-dialog">
          <DialogHeader>
            <DialogTitle>Remove Device</DialogTitle>
          </DialogHeader>
          <p className="text-gray-600 mb-4">
            Are you sure you want to remove this device? This will revoke its access to encrypted conversations.
          </p>
          <div className="flex justify-end space-x-2">
            <Button variant="outline" onClick={() => setShowRemoveDialog(false)}>
              Cancel
            </Button>
            <Button 
              variant="destructive"
              onClick={() => {
                if (selectedDevice) {
                  onRevokeDevice(selectedDevice);
                  setShowRemoveDialog(false);
                  setSelectedDevice(null);
                }
              }}
            >
              Remove Device
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}