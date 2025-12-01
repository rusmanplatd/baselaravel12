/**
 * Signal Protocol Settings Component
 * Comprehensive settings panel for managing Signal Protocol E2EE features
 */

import React, { useState, useCallback, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useUserStorage } from '@/utils/localStorage';
import {
  Shield,
  Key,
  RefreshCw,
  Download,
  Upload,
  AlertTriangle,
  Check,
  Clock,
  Users,
  Settings,
  Lock,
  Unlock,
  Eye,
  EyeOff,
  Trash2,
  RotateCw,
  Database
} from 'lucide-react';
import { toast } from 'sonner';
import { SignalProtocolStatus } from './signal-protocol-status';
import { IdentityVerificationDialog } from './identity-verification-dialog';

interface SignalProtocolSettingsProps {
  signalStats: any;
  sessionInfo: any;
  onInitializeProtocol: () => Promise<void>;
  onRefreshPrekeys: () => Promise<void>;
  onRotateKeys: () => Promise<void>;
  onExportKeys: () => Promise<Blob>;
  onImportKeys: (file: File) => Promise<void>;
  onVerifyIdentity: (userId: string, fingerprint: string) => Promise<boolean>;
  onClearProtocolData: () => Promise<void>;
  className?: string;
}

interface ProtocolSettings {
  autoKeyRotation: boolean;
  keyRotationInterval: number; // days
  forwardSecrecy: boolean;
  requireVerification: boolean;
  prekeyRefreshThreshold: number;
  sessionTimeout: number; // days
}

const defaultSettings: ProtocolSettings = {
  autoKeyRotation: true,
  keyRotationInterval: 7,
  forwardSecrecy: true,
  requireVerification: false,
  prekeyRefreshThreshold: 10,
  sessionTimeout: 30,
};

export function SignalProtocolSettings({
  signalStats,
  sessionInfo,
  onInitializeProtocol,
  onRefreshPrekeys,
  onRotateKeys,
  onExportKeys,
  onImportKeys,
  onVerifyIdentity,
  onClearProtocolData,
  className = ''
}: SignalProtocolSettingsProps) {
  const { getItem, setItem } = useUserStorage();
  const [settings, setSettings] = useState<ProtocolSettings>(defaultSettings);
  const [isInitializing, setIsInitializing] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isRotating, setIsRotating] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [showVerificationDialog, setShowVerificationDialog] = useState(false);
  const [protocolInitialized, setProtocolInitialized] = useState(false);

  // Load settings from localStorage on component mount
  useEffect(() => {
    try {
      const savedSettings = getItem('signal-protocol-settings');
      if (savedSettings) {
        const parsedSettings = JSON.parse(savedSettings);
        setSettings({ ...defaultSettings, ...parsedSettings });
      }
    } catch (error) {
      console.error('Failed to load Signal protocol settings from localStorage:', error);
      // Continue with default settings
    }
  }, []);

  // Check if protocol is initialized
  useEffect(() => {
    setProtocolInitialized(signalStats?.x3dhStats?.identityKeyExists || false);
  }, [signalStats]);

  // Initialize Signal Protocol
  const handleInitialize = useCallback(async () => {
    setIsInitializing(true);
    try {
      await onInitializeProtocol();
      toast.success('Signal Protocol initialized successfully');
      setProtocolInitialized(true);
    } catch (error) {
      toast.error('Failed to initialize Signal Protocol');
    } finally {
      setIsInitializing(false);
    }
  }, [onInitializeProtocol]);

  // Refresh prekeys
  const handleRefreshPrekeys = useCallback(async () => {
    setIsRefreshing(true);
    try {
      await onRefreshPrekeys();
      toast.success('Prekeys refreshed successfully');
    } catch (error) {
      toast.error('Failed to refresh prekeys');
    } finally {
      setIsRefreshing(false);
    }
  }, [onRefreshPrekeys]);

  // Rotate session keys
  const handleRotateKeys = useCallback(async () => {
    setIsRotating(true);
    try {
      await onRotateKeys();
      toast.success('Session keys rotated successfully');
    } catch (error) {
      toast.error('Failed to rotate session keys');
    } finally {
      setIsRotating(false);
    }
  }, [onRotateKeys]);

  // Export keys
  const handleExportKeys = useCallback(async () => {
    setIsExporting(true);
    try {
      const blob = await onExportKeys();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `signal-keys-backup-${Date.now()}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      toast.success('Keys exported successfully');
    } catch (error) {
      toast.error('Failed to export keys');
    } finally {
      setIsExporting(false);
    }
  }, [onExportKeys]);

  // Import keys
  const handleImportKeys = useCallback(async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    try {
      await onImportKeys(file);
      toast.success('Keys imported successfully');
    } catch (error) {
      toast.error('Failed to import keys');
    }
  }, [onImportKeys]);

  // Clear protocol data
  const handleClearProtocolData = useCallback(async () => {
    if (!confirm('Are you sure you want to clear all Signal Protocol data? This action cannot be undone.')) {
      return;
    }

    try {
      await onClearProtocolData();
      toast.success('Signal Protocol data cleared');
      setProtocolInitialized(false);
    } catch (error) {
      toast.error('Failed to clear protocol data');
    }
  }, [onClearProtocolData]);

  // Update settings
  const handleSettingChange = useCallback(async (key: keyof ProtocolSettings, value: any) => {
    const newSettings = { ...settings, [key]: value };
    setSettings(newSettings);
    
    try {
      // Save to localStorage for persistence across sessions
      setItem('signal-protocol-settings', JSON.stringify(newSettings));
      
      // In a production environment, you would also save to the server:
      // await fetch('/api/v1/users/signal-settings', {
      //   method: 'PUT',
      //   headers: { 'Content-Type': 'application/json' },
      //   body: JSON.stringify({ [key]: value })
      // });
      
      toast.success(`${key.replace(/([A-Z])/g, ' $1').toLowerCase()} updated successfully`);
    } catch (error) {
      console.error('Failed to save settings:', error);
      toast.error('Failed to save settings');
      // Revert the state change on error
      setSettings(settings);
    }
  }, [settings]);

  const getHealthColor = (score: number) => {
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getPrekeyStatus = () => {
    const available = signalStats?.x3dhStats?.oneTimePreKeys || 0;
    if (available < 5) return { status: 'critical', color: 'text-red-600' };
    if (available < 15) return { status: 'warning', color: 'text-yellow-600' };
    return { status: 'healthy', color: 'text-green-600' };
  };

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">Signal Protocol Settings</h2>
          <p className="text-gray-600">Manage your end-to-end encryption settings</p>
        </div>
        <Badge className={protocolInitialized ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
          {protocolInitialized ? (
            <><Shield className="h-3 w-3 mr-1" /> Active</>
          ) : (
            <><Lock className="h-3 w-3 mr-1" /> Inactive</>
          )}
        </Badge>
      </div>

      {/* Initialization */}
      {!protocolInitialized && (
        <Alert>
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription className="flex items-center justify-between">
            <span>Signal Protocol is not initialized. Initialize it to enable advanced E2EE features.</span>
            <Button
              onClick={handleInitialize}
              disabled={isInitializing}
              size="sm"
            >
              {isInitializing ? (
                <><RefreshCw className="h-4 w-4 mr-2 animate-spin" /> Initializing...</>
              ) : (
                <><Shield className="h-4 w-4 mr-2" /> Initialize</>
              )}
            </Button>
          </AlertDescription>
        </Alert>
      )}

      <Tabs defaultValue="overview" className="w-full">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="keys">Key Management</TabsTrigger>
          <TabsTrigger value="sessions">Sessions</TabsTrigger>
          <TabsTrigger value="settings">Settings</TabsTrigger>
        </TabsList>

        {/* Overview Tab */}
        <TabsContent value="overview" className="space-y-4">
          <SignalProtocolStatus
            sessionInfo={sessionInfo}
            statistics={signalStats}
            healthScore={signalStats?.healthScore}
            onVerifyIdentity={() => setShowVerificationDialog(true)}
            onRotateKeys={handleRotateKeys}
          />
        </TabsContent>

        {/* Key Management Tab */}
        <TabsContent value="keys" className="space-y-4">
          <div className="grid gap-4">
            {/* Identity Key Status */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Key className="h-5 w-5" />
                  <span>Identity Key</span>
                </CardTitle>
                <CardDescription>Your long-term identity key for the Signal Protocol</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Badge className={protocolInitialized ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
                      {protocolInitialized ? 'Active' : 'Not Generated'}
                    </Badge>
                    {protocolInitialized && (
                      <span className="text-sm text-gray-500">
                        Registration ID: {signalStats?.sessionStats?.localRegistrationId || 'N/A'}
                      </span>
                    )}
                  </div>
                  {protocolInitialized && (
                    <Button variant="outline" size="sm" onClick={() => setShowVerificationDialog(true)}>
                      <Eye className="h-4 w-4 mr-1" />
                      View Fingerprint
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Prekey Status */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <RefreshCw className="h-5 w-5" />
                  <span>Prekeys</span>
                </CardTitle>
                <CardDescription>Keys used for establishing new encrypted sessions</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label className="text-sm text-gray-600">Signed Prekeys</Label>
                    <div className="text-2xl font-bold">
                      {signalStats?.x3dhStats?.signedPreKeys || 0}
                    </div>
                  </div>
                  <div>
                    <Label className="text-sm text-gray-600">One-time Prekeys</Label>
                    <div className={`text-2xl font-bold ${getPrekeyStatus().color}`}>
                      {signalStats?.x3dhStats?.oneTimePreKeys || 0}
                    </div>
                  </div>
                </div>

                {protocolInitialized && (
                  <div className="flex space-x-2">
                    <Button
                      onClick={handleRefreshPrekeys}
                      disabled={isRefreshing}
                      variant="outline"
                      className="flex-1"
                    >
                      {isRefreshing ? (
                        <><RefreshCw className="h-4 w-4 mr-2 animate-spin" /> Refreshing...</>
                      ) : (
                        <><RefreshCw className="h-4 w-4 mr-2" /> Refresh Prekeys</>
                      )}
                    </Button>
                    <Button
                      onClick={handleRotateKeys}
                      disabled={isRotating}
                      variant="outline"
                      className="flex-1"
                    >
                      {isRotating ? (
                        <><RotateCw className="h-4 w-4 mr-2 animate-spin" /> Rotating...</>
                      ) : (
                        <><RotateCw className="h-4 w-4 mr-2" /> Rotate Keys</>
                      )}
                    </Button>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Key Backup & Recovery */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Database className="h-5 w-5" />
                  <span>Key Backup & Recovery</span>
                </CardTitle>
                <CardDescription>Export and import your encryption keys</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex space-x-2">
                  <Button
                    onClick={handleExportKeys}
                    disabled={!protocolInitialized || isExporting}
                    variant="outline"
                    className="flex-1"
                  >
                    {isExporting ? (
                      <><Download className="h-4 w-4 mr-2 animate-pulse" /> Exporting...</>
                    ) : (
                      <><Download className="h-4 w-4 mr-2" /> Export Keys</>
                    )}
                  </Button>
                  <Label htmlFor="import-keys" className="flex-1">
                    <Button variant="outline" className="w-full" asChild>
                      <span>
                        <Upload className="h-4 w-4 mr-2" />
                        Import Keys
                      </span>
                    </Button>
                    <input
                      id="import-keys"
                      type="file"
                      accept=".json"
                      onChange={handleImportKeys}
                      className="hidden"
                    />
                  </Label>
                </div>
                <Alert>
                  <Shield className="h-4 w-4" />
                  <AlertDescription className="text-xs">
                    Keep your key backups secure. Anyone with access to your keys can read your encrypted messages.
                  </AlertDescription>
                </Alert>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* Sessions Tab */}
        <TabsContent value="sessions" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Users className="h-5 w-5" />
                <span>Active Sessions</span>
              </CardTitle>
              <CardDescription>Manage your encrypted sessions with other users</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="text-center">
                  <div className="text-2xl font-bold text-blue-600">
                    {signalStats?.sessionStats?.activeSessions || 0}
                  </div>
                  <div className="text-sm text-gray-500">Active Sessions</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-green-600">
                    {signalStats?.sessionStats?.verifiedSessions || 0}
                  </div>
                  <div className="text-sm text-gray-500">Verified Sessions</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-purple-600">
                    {signalStats?.sessionStats?.totalMessagesExchanged || 0}
                  </div>
                  <div className="text-sm text-gray-500">Total Messages</div>
                </div>
              </div>

              {/* Session list would go here */}
              <div className="text-center text-gray-500 py-8">
                <Users className="h-12 w-12 mx-auto mb-4 opacity-50" />
                <p>No active sessions</p>
                <p className="text-sm">Sessions will appear here once you start chatting</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Settings Tab */}
        <TabsContent value="settings" className="space-y-4">
          <div className="grid gap-4">
            {/* Automatic Key Management */}
            <Card>
              <CardHeader>
                <CardTitle>Automatic Key Management</CardTitle>
                <CardDescription>Configure automatic key rotation and maintenance</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <Label className="font-medium">Automatic Key Rotation</Label>
                    <p className="text-sm text-gray-500">Automatically rotate session keys periodically</p>
                  </div>
                  <Switch
                    checked={settings.autoKeyRotation}
                    onCheckedChange={(checked) => handleSettingChange('autoKeyRotation', checked)}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <Label className="font-medium">Forward Secrecy</Label>
                    <p className="text-sm text-gray-500">Enable perfect forward secrecy for all messages</p>
                  </div>
                  <Switch
                    checked={settings.forwardSecrecy}
                    onCheckedChange={(checked) => handleSettingChange('forwardSecrecy', checked)}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <Label className="font-medium">Require Identity Verification</Label>
                    <p className="text-sm text-gray-500">Require manual identity verification for new sessions</p>
                  </div>
                  <Switch
                    checked={settings.requireVerification}
                    onCheckedChange={(checked) => handleSettingChange('requireVerification', checked)}
                  />
                </div>
              </CardContent>
            </Card>

            {/* Advanced Settings */}
            <Card>
              <CardHeader>
                <CardTitle>Advanced Settings</CardTitle>
                <CardDescription>Advanced configuration options</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <Label className="font-medium">Prekey Refresh Threshold</Label>
                  <p className="text-sm text-gray-500 mb-2">
                    Refresh prekeys when count drops below this number
                  </p>
                  <div className="flex items-center space-x-2">
                    <span className="text-sm">5</span>
                    <input
                      type="range"
                      min="5"
                      max="50"
                      value={settings.prekeyRefreshThreshold}
                      onChange={(e) => handleSettingChange('prekeyRefreshThreshold', parseInt(e.target.value))}
                      className="flex-1"
                    />
                    <span className="text-sm">50</span>
                    <span className="text-sm font-medium w-8">{settings.prekeyRefreshThreshold}</span>
                  </div>
                </div>

                <div>
                  <Label className="font-medium">Session Timeout (days)</Label>
                  <p className="text-sm text-gray-500 mb-2">
                    Automatically close inactive sessions after this period
                  </p>
                  <div className="flex items-center space-x-2">
                    <span className="text-sm">7</span>
                    <input
                      type="range"
                      min="7"
                      max="90"
                      value={settings.sessionTimeout}
                      onChange={(e) => handleSettingChange('sessionTimeout', parseInt(e.target.value))}
                      className="flex-1"
                    />
                    <span className="text-sm">90</span>
                    <span className="text-sm font-medium w-8">{settings.sessionTimeout}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Danger Zone */}
            <Card className="border-red-200">
              <CardHeader>
                <CardTitle className="text-red-600">Danger Zone</CardTitle>
                <CardDescription>Irreversible actions that affect your encryption</CardDescription>
              </CardHeader>
              <CardContent>
                <Button
                  onClick={handleClearProtocolData}
                  variant="destructive"
                  className="w-full"
                >
                  <Trash2 className="h-4 w-4 mr-2" />
                  Clear All Signal Protocol Data
                </Button>
                <p className="text-xs text-gray-500 mt-2">
                  This will delete all your keys, sessions, and settings. You'll need to re-initialize the protocol.
                </p>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>

      {/* Identity Verification Dialog */}
      {showVerificationDialog && sessionInfo && (
        <IdentityVerificationDialog
          isOpen={showVerificationDialog}
          onOpenChange={setShowVerificationDialog}
          sessionInfo={sessionInfo}
          remoteUserName="Remote User" // This would come from props
          localFingerprint="your-local-fingerprint" // This would be calculated
          remoteFingerprint="remote-fingerprint" // This would come from session
          onVerifyIdentity={async (fingerprint, method) => {
            return await onVerifyIdentity('userId', fingerprint);
          }}
        />
      )}
    </div>
  );
}

export default SignalProtocolSettings;
