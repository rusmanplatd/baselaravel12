import React, { useState, useEffect } from 'react';
import { 
  Shield, 
  Key, 
  Download, 
  Upload, 
  RefreshCw, 
  AlertTriangle, 
  CheckCircle, 
  Settings,
  Lock,
  Eye,
  EyeOff
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { useE2EE } from '@/hooks/useE2EE';
import { E2EEStatusIndicator, E2EEStatusBadge } from './E2EEStatusIndicator';

interface E2EESettingsProps {
  userId: string;
  onClose?: () => void;
}

export function E2EESettings({ userId, onClose }: E2EESettingsProps) {
  const {
    status,
    initializeE2EE,
    generateKeyPair,
    clearEncryptionData,
    createBackup,
    verifyKeyIntegrity,
    error,
    isReady
  } = useE2EE(userId);

  const [backupPassword, setBackupPassword] = useState('');
  const [showBackupPassword, setShowBackupPassword] = useState(false);
  const [isCreatingBackup, setIsCreatingBackup] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [backupData, setBackupData] = useState<string | null>(null);
  const [settings, setSettings] = useState({
    autoKeyRotation: false,
    keyRotationDays: 30,
    requireBackupReminder: true,
    showEncryptionIndicators: true,
    enableAdvancedLogging: false
  });

  const [keyStats, setKeyStats] = useState<{
    totalKeys: number;
    oldestKey: string | null;
    averageKeyAge: number;
  }>({
    totalKeys: 0,
    oldestKey: null,
    averageKeyAge: 0
  });

  useEffect(() => {
    // Load settings from localStorage
    const savedSettings = localStorage.getItem('e2ee_settings');
    if (savedSettings) {
      try {
        setSettings({ ...settings, ...JSON.parse(savedSettings) });
      } catch (e) {
        console.warn('Failed to load E2EE settings');
      }
    }
  }, []);

  const handleSettingChange = (key: keyof typeof settings, value: boolean | number) => {
    const newSettings = { ...settings, [key]: value };
    setSettings(newSettings);
    localStorage.setItem('e2ee_settings', JSON.stringify(newSettings));
  };

  const handleBackupCreation = async () => {
    if (!backupPassword || backupPassword.length < 8) {
      return;
    }

    setIsCreatingBackup(true);
    try {
      const backup = await createBackup(backupPassword);
      if (backup) {
        setBackupData(backup);
        // Auto-download backup
        const blob = new Blob([backup], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chat-keys-backup-${new Date().toISOString().split('T')[0]}.e2ee`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }
    } catch (err) {
      console.error('Backup creation failed:', err);
    } finally {
      setIsCreatingBackup(false);
    }
  };

  const handleKeyVerification = async () => {
    setIsVerifying(true);
    try {
      await verifyKeyIntegrity();
    } finally {
      setIsVerifying(false);
    }
  };

  const handleResetEncryption = async () => {
    if (confirm('Are you sure you want to reset all encryption keys? This will make old messages unreadable.')) {
      await clearEncryptionData();
      await initializeE2EE();
    }
  };

  const getSecurityScore = () => {
    let score = 0;
    if (status.enabled) score += 25;
    if (status.keyGenerated) score += 25;
    if (status.conversationKeysReady) score += 25;
    if (status.lastKeyRotation) score += 25;
    return score;
  };

  const securityScore = getSecurityScore();

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">End-to-End Encryption Settings</h1>
          <p className="text-gray-600 mt-1">Manage your encryption keys and security settings</p>
        </div>
        {onClose && (
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
        )}
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>Error</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Status Overview */}
        <Card className="lg:col-span-1">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Shield className="h-5 w-5" />
              Security Status
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium">Security Score</span>
                <span className="text-2xl font-bold">{securityScore}%</span>
              </div>
              <Progress value={securityScore} className="h-2" />
            </div>
            
            <E2EEStatusBadge status={status} detailed className="w-full justify-center" />
            
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span>Keys Generated</span>
                <CheckCircle className={`h-4 w-4 ${status.keyGenerated ? 'text-green-500' : 'text-gray-300'}`} />
              </div>
              <div className="flex items-center justify-between text-sm">
                <span>Conversations Ready</span>
                <CheckCircle className={`h-4 w-4 ${status.conversationKeysReady ? 'text-green-500' : 'text-gray-300'}`} />
              </div>
              <div className="flex items-center justify-between text-sm">
                <span>System Ready</span>
                <CheckCircle className={`h-4 w-4 ${isReady ? 'text-green-500' : 'text-gray-300'}`} />
              </div>
            </div>

            {status.lastKeyRotation && (
              <div className="text-xs text-gray-500 pt-2 border-t">
                Last key rotation: {new Date(status.lastKeyRotation).toLocaleDateString()}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Main Settings */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Encryption Management</CardTitle>
          </CardHeader>
          <CardContent>
            <Tabs defaultValue="general" className="w-full">
              <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="general">General</TabsTrigger>
                <TabsTrigger value="backup">Backup</TabsTrigger>
                <TabsTrigger value="advanced">Advanced</TabsTrigger>
              </TabsList>

              <TabsContent value="general" className="space-y-4">
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <Label htmlFor="encryption-indicators">Show Encryption Indicators</Label>
                      <p className="text-sm text-gray-500">Display encryption status on messages</p>
                    </div>
                    <Switch 
                      id="encryption-indicators"
                      checked={settings.showEncryptionIndicators}
                      onCheckedChange={(checked) => handleSettingChange('showEncryptionIndicators', checked)}
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <Label htmlFor="backup-reminders">Backup Reminders</Label>
                      <p className="text-sm text-gray-500">Remind me to backup my keys</p>
                    </div>
                    <Switch 
                      id="backup-reminders"
                      checked={settings.requireBackupReminder}
                      onCheckedChange={(checked) => handleSettingChange('requireBackupReminder', checked)}
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <Label htmlFor="auto-rotation">Automatic Key Rotation</Label>
                      <p className="text-sm text-gray-500">Automatically rotate keys periodically</p>
                    </div>
                    <Switch 
                      id="auto-rotation"
                      checked={settings.autoKeyRotation}
                      onCheckedChange={(checked) => handleSettingChange('autoKeyRotation', checked)}
                    />
                  </div>

                  {settings.autoKeyRotation && (
                    <div>
                      <Label htmlFor="rotation-days">Rotation Interval (days)</Label>
                      <Input
                        id="rotation-days"
                        type="number"
                        min="1"
                        max="365"
                        value={settings.keyRotationDays}
                        onChange={(e) => handleSettingChange('keyRotationDays', parseInt(e.target.value))}
                        className="mt-1"
                      />
                    </div>
                  )}
                </div>

                <div className="flex gap-2 pt-4 border-t">
                  <Button 
                    onClick={handleKeyVerification}
                    disabled={isVerifying || !isReady}
                    variant="outline"
                  >
                    {isVerifying ? <RefreshCw className="h-4 w-4 animate-spin mr-2" /> : <Key className="h-4 w-4 mr-2" />}
                    Verify Keys
                  </Button>
                  <Button 
                    onClick={handleResetEncryption}
                    variant="destructive"
                    disabled={!isReady}
                  >
                    Reset Encryption
                  </Button>
                </div>
              </TabsContent>

              <TabsContent value="backup" className="space-y-4">
                <Alert>
                  <Shield className="h-4 w-4" />
                  <AlertTitle>Important</AlertTitle>
                  <AlertDescription>
                    Regular backups are essential to prevent losing access to your encrypted messages.
                  </AlertDescription>
                </Alert>

                <div className="space-y-4">
                  <div>
                    <Label htmlFor="backup-password">Backup Password</Label>
                    <div className="relative mt-1">
                      <Input
                        id="backup-password"
                        type={showBackupPassword ? "text" : "password"}
                        value={backupPassword}
                        onChange={(e) => setBackupPassword(e.target.value)}
                        placeholder="Enter a strong password for your backup"
                        className="pr-10"
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3"
                        onClick={() => setShowBackupPassword(!showBackupPassword)}
                      >
                        {showBackupPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </Button>
                    </div>
                    <p className="text-sm text-gray-500 mt-1">
                      Must be at least 8 characters long
                    </p>
                  </div>

                  <Button 
                    onClick={handleBackupCreation}
                    disabled={!backupPassword || backupPassword.length < 8 || isCreatingBackup || !isReady}
                    className="w-full"
                  >
                    {isCreatingBackup ? (
                      <RefreshCw className="h-4 w-4 animate-spin mr-2" />
                    ) : (
                      <Download className="h-4 w-4 mr-2" />
                    )}
                    Create Backup
                  </Button>

                  {backupData && (
                    <Alert>
                      <CheckCircle className="h-4 w-4" />
                      <AlertTitle>Backup Created</AlertTitle>
                      <AlertDescription>
                        Your backup has been downloaded. Store it in a safe place.
                      </AlertDescription>
                    </Alert>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="advanced" className="space-y-4">
                <Alert>
                  <AlertTriangle className="h-4 w-4" />
                  <AlertTitle>Advanced Settings</AlertTitle>
                  <AlertDescription>
                    These settings are for advanced users. Changes may affect security.
                  </AlertDescription>
                </Alert>

                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <Label htmlFor="advanced-logging">Enable Advanced Logging</Label>
                      <p className="text-sm text-gray-500">Log encryption operations for debugging</p>
                    </div>
                    <Switch 
                      id="advanced-logging"
                      checked={settings.enableAdvancedLogging}
                      onCheckedChange={(checked) => handleSettingChange('enableAdvancedLogging', checked)}
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <h4 className="font-medium mb-2">Key Statistics</h4>
                      <div className="text-sm space-y-1">
                        <div className="flex justify-between">
                          <span>Total Keys:</span>
                          <Badge variant="outline">{keyStats.totalKeys}</Badge>
                        </div>
                        <div className="flex justify-between">
                          <span>Avg. Age:</span>
                          <Badge variant="outline">{keyStats.averageKeyAge} days</Badge>
                        </div>
                      </div>
                    </div>

                    <div>
                      <h4 className="font-medium mb-2">Encryption Info</h4>
                      <div className="text-sm space-y-1">
                        <div className="flex justify-between">
                          <span>Version:</span>
                          <Badge>{status.version}</Badge>
                        </div>
                        <div className="flex justify-between">
                          <span>Algorithm:</span>
                          <Badge>AES-256</Badge>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </TabsContent>
            </Tabs>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}