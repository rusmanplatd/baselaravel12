import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import { 
  Smartphone, 
  Monitor, 
  Tablet, 
  Laptop,
  Watch,
  Tv,
  MoreVertical,
  Shield,
  ShieldCheck,
  ShieldAlert,
  ShieldOff,
  Plus,
  RefreshCw,
  Eye,
  EyeOff,
  Key,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Clock,
  Trash2,
  Edit,
  QrCode,
  Smartphone as SmartphoneIcon,
  Fingerprint,
  Cpu,
  HardDrive,
  Wifi,
  WifiOff,
  Battery,
  BatteryLow,
  Signal,
  SignalLow,
  MapPin
} from 'lucide-react';
import { useDeviceApi } from '@/hooks/useDeviceApi';
import { UserDevice } from '@/types/device';
import { toast } from 'sonner';

interface DeviceManagerProps {
  userId?: string;
  className?: string;
  showAddDevice?: boolean;
  adminMode?: boolean;
}

const DeviceManager: React.FC<DeviceManagerProps> = ({
  userId,
  className,
  showAddDevice = true,
  adminMode = false
}) => {
  const [devices, setDevices] = useState<UserDevice[]>([]);
  const [selectedDevice, setSelectedDevice] = useState<UserDevice | null>(null);
  const [showDeviceDialog, setShowDeviceDialog] = useState(false);
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [deviceToDelete, setDeviceToDelete] = useState<UserDevice | null>(null);
  const [activeTab, setActiveTab] = useState<'all' | 'trusted' | 'untrusted' | 'suspended'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [newDeviceName, setNewDeviceName] = useState('');
  const [showQRCode, setShowQRCode] = useState(false);
  const [qrCodeData, setQRCodeData] = useState<string>('');
  
  const {
    loading,
    fetchDevices,
    addDevice,
    updateDevice,
    deleteDevice,
    trustDevice,
    suspendDevice,
    generatePairingCode,
    revokeAllSessions,
    rotateDeviceKeys,
    getDeviceLocation
  } = useDeviceApi();

  useEffect(() => {
    loadDevices();
  }, [userId]);

  const loadDevices = async () => {
    try {
      const deviceList = await fetchDevices(userId);
      setDevices(deviceList);
    } catch (error) {
      toast.error('Failed to load devices');
    }
  };

  const handleAddDevice = async () => {
    if (!newDeviceName.trim()) {
      toast.error('Please enter a device name');
      return;
    }

    try {
      const result = await generatePairingCode(newDeviceName);
      setQRCodeData(result.pairing_code);
      setShowQRCode(true);
      setShowAddDialog(false);
      setNewDeviceName('');
      toast.success('Pairing code generated. Scan with your device to add it.');
    } catch (error) {
      toast.error('Failed to generate pairing code');
    }
  };

  const handleTrustDevice = async (deviceId: string) => {
    try {
      await trustDevice(deviceId);
      await loadDevices();
      toast.success('Device trusted successfully');
    } catch (error) {
      toast.error('Failed to trust device');
    }
  };

  const handleSuspendDevice = async (deviceId: string) => {
    try {
      await suspendDevice(deviceId);
      await loadDevices();
      toast.success('Device suspended successfully');
    } catch (error) {
      toast.error('Failed to suspend device');
    }
  };

  const handleDeleteDevice = async () => {
    if (!deviceToDelete) return;

    try {
      await deleteDevice(deviceToDelete.id);
      await loadDevices();
      setShowDeleteDialog(false);
      setDeviceToDelete(null);
      toast.success('Device removed successfully');
    } catch (error) {
      toast.error('Failed to remove device');
    }
  };

  const handleRotateKeys = async (deviceId: string) => {
    try {
      await rotateDeviceKeys(deviceId);
      await loadDevices();
      toast.success('Device keys rotated successfully');
    } catch (error) {
      toast.error('Failed to rotate device keys');
    }
  };

  const handleRevokeAllSessions = async (deviceId: string) => {
    try {
      await revokeAllSessions(deviceId);
      await loadDevices();
      toast.success('All device sessions revoked');
    } catch (error) {
      toast.error('Failed to revoke sessions');
    }
  };

  const getDeviceIcon = (deviceType: string) => {
    switch (deviceType.toLowerCase()) {
      case 'mobile':
      case 'phone':
        return <Smartphone className="h-5 w-5" />;
      case 'tablet':
        return <Tablet className="h-5 w-5" />;
      case 'desktop':
      case 'computer':
        return <Monitor className="h-5 w-5" />;
      case 'laptop':
        return <Laptop className="h-5 w-5" />;
      case 'watch':
      case 'wearable':
        return <Watch className="h-5 w-5" />;
      case 'tv':
      case 'smart_tv':
        return <Tv className="h-5 w-5" />;
      default:
        return <Monitor className="h-5 w-5" />;
    }
  };

  const getDeviceStatusIcon = (device: UserDevice) => {
    if (device.is_suspended) {
      return <ShieldOff className="h-4 w-4 text-red-500" />;
    }
    if (device.is_trusted) {
      return <ShieldCheck className="h-4 w-4 text-green-500" />;
    }
    return <ShieldAlert className="h-4 w-4 text-yellow-500" />;
  };

  const getDeviceStatusBadge = (device: UserDevice) => {
    if (device.is_suspended) {
      return <Badge variant="destructive">Suspended</Badge>;
    }
    if (device.is_trusted) {
      return <Badge variant="default">Trusted</Badge>;
    }
    return <Badge variant="secondary">Untrusted</Badge>;
  };

  const getOnlineStatusIcon = (device: UserDevice) => {
    const isOnline = device.last_seen_at && 
      new Date(device.last_seen_at).getTime() > Date.now() - 5 * 60 * 1000; // 5 minutes

    return isOnline ? (
      <div className="flex items-center gap-1 text-green-600">
        <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
        <span className="text-xs">Online</span>
      </div>
    ) : (
      <div className="flex items-center gap-1 text-gray-500">
        <div className="w-2 h-2 bg-gray-400 rounded-full" />
        <span className="text-xs">Offline</span>
      </div>
    );
  };

  const getEncryptionStatusIcon = (device: UserDevice) => {
    if (device.encryption_version === 'v3') {
      return <Shield className="h-4 w-4 text-green-500" title="Quantum-resistant encryption" />;
    }
    if (device.encryption_version === 'v2') {
      return <Shield className="h-4 w-4 text-blue-500" title="RSA encryption" />;
    }
    return <Shield className="h-4 w-4 text-yellow-500" title="Legacy encryption" />;
  };

  const filteredDevices = devices.filter(device => {
    // Tab filter
    if (activeTab === 'trusted' && !device.is_trusted) return false;
    if (activeTab === 'untrusted' && device.is_trusted) return false;
    if (activeTab === 'suspended' && !device.is_suspended) return false;
    
    // Search filter
    if (searchQuery && !device.name.toLowerCase().includes(searchQuery.toLowerCase()) &&
        !device.device_type.toLowerCase().includes(searchQuery.toLowerCase()) &&
        !device.os_name?.toLowerCase().includes(searchQuery.toLowerCase())) {
      return false;
    }
    
    return true;
  });

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Device Management</h2>
          <p className="text-muted-foreground">
            Manage trusted devices and security settings
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            onClick={loadDevices}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          {showAddDevice && (
            <Button onClick={() => setShowAddDialog(true)}>
              <Plus className="h-4 w-4 mr-2" />
              Add Device
            </Button>
          )}
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={(value: any) => setActiveTab(value)}>
        <div className="flex items-center justify-between">
          <TabsList>
            <TabsTrigger value="all">
              All Devices ({devices.length})
            </TabsTrigger>
            <TabsTrigger value="trusted">
              Trusted ({devices.filter(d => d.is_trusted).length})
            </TabsTrigger>
            <TabsTrigger value="untrusted">
              Untrusted ({devices.filter(d => !d.is_trusted).length})
            </TabsTrigger>
            <TabsTrigger value="suspended">
              Suspended ({devices.filter(d => d.is_suspended).length})
            </TabsTrigger>
          </TabsList>
          
          <div className="flex-1 max-w-sm ml-4">
            <Input
              placeholder="Search devices..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
        </div>

        <TabsContent value={activeTab} className="space-y-4">
          {loading ? (
            <div className="flex items-center justify-center h-32">
              <RefreshCw className="h-6 w-6 animate-spin mr-2" />
              <span>Loading devices...</span>
            </div>
          ) : filteredDevices.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center justify-center h-32">
                <Smartphone className="h-12 w-12 text-muted-foreground mb-2" />
                <p className="text-muted-foreground">No devices found</p>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {filteredDevices.map((device) => (
                <Card key={device.id} className="relative">
                  <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        {getDeviceIcon(device.device_type)}
                        <div className="min-w-0 flex-1">
                          <CardTitle className="text-lg truncate">{device.name}</CardTitle>
                          <CardDescription className="flex items-center gap-2">
                            {device.os_name} {device.os_version}
                            {getEncryptionStatusIcon(device)}
                          </CardDescription>
                        </div>
                      </div>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuLabel>Device Actions</DropdownMenuLabel>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem
                            onClick={() => setSelectedDevice(device)}
                          >
                            <Eye className="h-4 w-4 mr-2" />
                            View Details
                          </DropdownMenuItem>
                          {!device.is_trusted && (
                            <DropdownMenuItem
                              onClick={() => handleTrustDevice(device.id)}
                            >
                              <ShieldCheck className="h-4 w-4 mr-2" />
                              Trust Device
                            </DropdownMenuItem>
                          )}
                          {!device.is_suspended ? (
                            <DropdownMenuItem
                              onClick={() => handleSuspendDevice(device.id)}
                            >
                              <ShieldOff className="h-4 w-4 mr-2" />
                              Suspend Device
                            </DropdownMenuItem>
                          ) : (
                            <DropdownMenuItem
                              onClick={() => handleTrustDevice(device.id)}
                            >
                              <ShieldCheck className="h-4 w-4 mr-2" />
                              Restore Device
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuSeparator />
                          <DropdownMenuItem
                            onClick={() => handleRotateKeys(device.id)}
                          >
                            <Key className="h-4 w-4 mr-2" />
                            Rotate Keys
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            onClick={() => handleRevokeAllSessions(device.id)}
                          >
                            <XCircle className="h-4 w-4 mr-2" />
                            Revoke Sessions
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem
                            onClick={() => {
                              setDeviceToDelete(device);
                              setShowDeleteDialog(true);
                            }}
                            className="text-red-600"
                          >
                            <Trash2 className="h-4 w-4 mr-2" />
                            Remove Device
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                    
                    {/* Status Badges */}
                    <div className="flex items-center gap-2 mt-2">
                      {getDeviceStatusBadge(device)}
                      <Badge variant="outline" className="text-xs">
                        {device.device_type}
                      </Badge>
                    </div>
                  </CardHeader>
                  
                  <CardContent className="pt-0 space-y-3">
                    {/* Status Icons */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-1">
                        {getDeviceStatusIcon(device)}
                        <span className="text-sm text-muted-foreground">Security</span>
                      </div>
                      {getOnlineStatusIcon(device)}
                    </div>
                    
                    {/* Device Info */}
                    <div className="space-y-2 text-sm text-muted-foreground">
                      <div className="flex items-center justify-between">
                        <span>Last seen:</span>
                        <span>{device.last_seen_at ? new Date(device.last_seen_at).toLocaleDateString() : 'Never'}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span>Added:</span>
                        <span>{new Date(device.created_at).toLocaleDateString()}</span>
                      </div>
                      {device.ip_address && (
                        <div className="flex items-center justify-between">
                          <span>IP:</span>
                          <span className="font-mono text-xs">{device.ip_address}</span>
                        </div>
                      )}
                    </div>
                    
                    {/* Quick Actions */}
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        className="flex-1"
                        onClick={() => setSelectedDevice(device)}
                      >
                        Details
                      </Button>
                      {!device.is_trusted && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleTrustDevice(device.id)}
                        >
                          <ShieldCheck className="h-4 w-4" />
                        </Button>
                      )}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </TabsContent>
      </Tabs>

      {/* Add Device Dialog */}
      <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add New Device</DialogTitle>
            <DialogDescription>
              Generate a pairing code to add a new device to your account
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="device-name">Device Name</Label>
              <Input
                id="device-name"
                placeholder="e.g., John's iPhone"
                value={newDeviceName}
                onChange={(e) => setNewDeviceName(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setShowAddDialog(false)}
            >
              Cancel
            </Button>
            <Button onClick={handleAddDevice}>
              Generate Pairing Code
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* QR Code Dialog */}
      <Dialog open={showQRCode} onOpenChange={setShowQRCode}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <QrCode className="h-5 w-5" />
              Device Pairing Code
            </DialogTitle>
            <DialogDescription>
              Scan this QR code with your device to pair it
            </DialogDescription>
          </DialogHeader>
          <div className="flex flex-col items-center space-y-4">
            <div className="w-64 h-64 bg-muted flex items-center justify-center border-2 border-dashed">
              <div className="text-center">
                <QrCode className="h-12 w-12 mx-auto mb-2 text-muted-foreground" />
                <p className="text-sm text-muted-foreground">QR Code would appear here</p>
              </div>
            </div>
            <div className="text-center">
              <p className="text-sm text-muted-foreground mb-2">Or enter this code manually:</p>
              <code className="bg-muted px-2 py-1 rounded text-sm font-mono">
                {qrCodeData}
              </code>
            </div>
          </div>
          <DialogFooter>
            <Button onClick={() => setShowQRCode(false)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Device Details Dialog */}
      {selectedDevice && (
        <DeviceDetailsDialog
          device={selectedDevice}
          open={!!selectedDevice}
          onOpenChange={() => setSelectedDevice(null)}
          onDeviceUpdate={loadDevices}
        />
      )}

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove Device</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove "{deviceToDelete?.name}"? This action cannot be undone and will revoke all access for this device.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteDevice}
              className="bg-red-600 hover:bg-red-700"
            >
              Remove Device
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
};

// Device Details Dialog Component
interface DeviceDetailsDialogProps {
  device: UserDevice;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onDeviceUpdate: () => void;
}

const DeviceDetailsDialog: React.FC<DeviceDetailsDialogProps> = ({
  device,
  open,
  onOpenChange,
  onDeviceUpdate
}) => {
  const [deviceName, setDeviceName] = useState(device.name);
  const [saving, setSaving] = useState(false);
  
  const { updateDevice } = useDeviceApi();

  const handleSave = async () => {
    if (deviceName.trim() === device.name) {
      onOpenChange(false);
      return;
    }

    setSaving(true);
    try {
      await updateDevice(device.id, { name: deviceName.trim() });
      onDeviceUpdate();
      toast.success('Device updated successfully');
      onOpenChange(false);
    } catch (error) {
      toast.error('Failed to update device');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Smartphone className="h-5 w-5" />
            Device Details
          </DialogTitle>
        </DialogHeader>
        
        <div className="space-y-6">
          {/* Basic Info */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="device-name-edit">Device Name</Label>
              <Input
                id="device-name-edit"
                value={deviceName}
                onChange={(e) => setDeviceName(e.target.value)}
              />
            </div>
            <div>
              <Label>Device Type</Label>
              <Input value={device.device_type} disabled />
            </div>
          </div>
          
          {/* System Info */}
          <div className="space-y-4">
            <h4 className="text-sm font-medium">System Information</h4>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-muted-foreground">Operating System:</span>
                <p className="font-medium">{device.os_name} {device.os_version}</p>
              </div>
              <div>
                <span className="text-muted-foreground">Browser:</span>
                <p className="font-medium">{device.browser_name || 'Unknown'}</p>
              </div>
              <div>
                <span className="text-muted-foreground">IP Address:</span>
                <p className="font-mono text-xs">{device.ip_address || 'Unknown'}</p>
              </div>
              <div>
                <span className="text-muted-foreground">User Agent:</span>
                <p className="font-mono text-xs truncate" title={device.user_agent || ''}>
                  {device.user_agent || 'Unknown'}
                </p>
              </div>
            </div>
          </div>
          
          {/* Security Info */}
          <div className="space-y-4">
            <h4 className="text-sm font-medium">Security Information</h4>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-muted-foreground">Trust Status:</span>
                <div className="flex items-center gap-2 mt-1">
                  {device.is_trusted ? (
                    <Badge variant="default">Trusted</Badge>
                  ) : (
                    <Badge variant="secondary">Untrusted</Badge>
                  )}
                </div>
              </div>
              <div>
                <span className="text-muted-foreground">Encryption Version:</span>
                <p className="font-medium">{device.encryption_version || 'Unknown'}</p>
              </div>
              <div>
                <span className="text-muted-foreground">Public Key Fingerprint:</span>
                <p className="font-mono text-xs truncate">
                  {device.public_key_fingerprint || 'Not available'}
                </p>
              </div>
              <div>
                <span className="text-muted-foreground">Last Key Rotation:</span>
                <p>{device.keys_rotated_at ? new Date(device.keys_rotated_at).toLocaleString() : 'Never'}</p>
              </div>
            </div>
          </div>
          
          {/* Activity Info */}
          <div className="space-y-4">
            <h4 className="text-sm font-medium">Activity Information</h4>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-muted-foreground">First Seen:</span>
                <p>{new Date(device.created_at).toLocaleString()}</p>
              </div>
              <div>
                <span className="text-muted-foreground">Last Seen:</span>
                <p>{device.last_seen_at ? new Date(device.last_seen_at).toLocaleString() : 'Never'}</p>
              </div>
              <div>
                <span className="text-muted-foreground">Total Sessions:</span>
                <p>{device.session_count || 0}</p>
              </div>
              <div>
                <span className="text-muted-foreground">Messages Sent:</span>
                <p>{device.message_count || 0}</p>
              </div>
            </div>
          </div>
        </div>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving ? 'Saving...' : 'Save Changes'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

export default DeviceManager;