import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { apiService, ApiError } from '@/services/ApiService';
import { 
  Download, 
  Upload, 
  Shield, 
  Clock, 
  AlertTriangle, 
  CheckCircle, 
  Key,
  Archive,
  FileDown,
  FileUp,
  History,
  Info
} from 'lucide-react';

interface BackupData {
  backup_id: string;
  backup_timestamp: string;
  conversations_count: number;
  encrypted: boolean;
  backup_data?: any;
}

interface RecoveryStatus {
  user_id: string;
  user_email: string;
  has_public_key: boolean;
  total_conversations: number;
  total_encryption_keys: number;
  active_keys: number;
  devices_count: number;
  trusted_devices_count: number;
  last_key_created: string;
  backup_recommendations: Array<{
    type: 'info' | 'warning' | 'suggestion';
    message: string;
  }>;
}

interface KeyBackupManagerProps {
  className?: string;
}

export function KeyBackupManager({ className }: KeyBackupManagerProps) {
  const [recoveryStatus, setRecoveryStatus] = useState<RecoveryStatus | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [masterPassword, setMasterPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [privateKey, setPrivateKey] = useState('');
  const [backupData, setBackupData] = useState<string>('');
  const [lastBackup, setLastBackup] = useState<BackupData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState('status');

  useEffect(() => {
    loadRecoveryStatus();
  }, []);

  const loadRecoveryStatus = async () => {
    try {
      setIsLoading(true);
      const data = await apiService.get('/api/v1/key-recovery/status') as {
        success: boolean;
        status: any;
        message?: string;
      };
      if (data.success) {
        setRecoveryStatus(data.status);
      } else {
        setError(data.message || 'Failed to load recovery status');
      }
    } catch (err) {
      setError('Network error while loading recovery status');
    } finally {
      setIsLoading(false);
    }
  };

  const createBackup = async (incremental = false, sinceTimestamp?: string) => {
    if (masterPassword !== confirmPassword) {
      setError('Master passwords do not match');
      return;
    }

    try {
      setIsLoading(true);
      setError(null);
      
      const endpoint = incremental ? 'backup/incremental' : 'backup';
      const payload: any = {
        master_password: masterPassword,
        store_backup: true,
        include_data: true,
      };
      
      if (incremental && sinceTimestamp) {
        payload.since_timestamp = sinceTimestamp;
      }
      
      const data = await apiService.post(`/api/v1/key-recovery/${endpoint}`, payload) as {
        success: boolean;
        backup_id: string;
        backup_timestamp: string;
        conversations_count: number;
        encrypted: boolean;
        backup_data: any;
        message?: string;
      };
      if (data.success) {
        setLastBackup({
          backup_id: data.backup_id,
          backup_timestamp: data.backup_timestamp,
          conversations_count: data.conversations_count,
          encrypted: data.encrypted,
          backup_data: data.backup_data,
        });
        setSuccess(`${incremental ? 'Incremental' : 'Full'} backup created successfully with ${data.conversations_count} conversations`);
        setMasterPassword('');
        setConfirmPassword('');
      } else {
        setError(data.message || 'Failed to create backup');
      }
    } catch (err) {
      setError('Network error while creating backup');
    } finally {
      setIsLoading(false);
    }
  };

  const restoreFromBackup = async () => {
    if (!backupData.trim()) {
      setError('Please provide backup data');
      return;
    }
    
    if (!privateKey.trim()) {
      setError('Please provide your private key');
      return;
    }

    try {
      setIsLoading(true);
      setError(null);
      
      const data = await apiService.post('/api/v1/key-recovery/backup/restore', {
        backup_data: JSON.parse(backupData),
        private_key: privateKey,
        master_password: masterPassword || undefined,
      }) as {
        success: boolean;
        restored_count: number;
        total_conversations: number;
        errors_count: number;
        message?: string;
      };
      if (data.success) {
        setSuccess(`Backup restored successfully! ${data.restored_count}/${data.total_conversations} conversations recovered`);
        if (data.errors_count > 0) {
          setError(`${data.errors_count} conversations had errors during restoration`);
        }
        setBackupData('');
        setPrivateKey('');
        setMasterPassword('');
        await loadRecoveryStatus();
      } else {
        setError(data.message || 'Failed to restore backup');
      }
    } catch (err) {
      setError('Network error or invalid backup data format');
    } finally {
      setIsLoading(false);
    }
  };

  const validateBackup = async () => {
    if (!backupData.trim()) {
      setError('Please provide backup data to validate');
      return;
    }

    try {
      setIsLoading(true);
      setError(null);
      
      const data = await apiService.post('/api/v1/key-recovery/backup/validate', {
        backup_data: JSON.parse(backupData),
      }) as {
        success: boolean;
        valid: boolean;
        backup_id: string;
        backup_version: string;
        message?: string;
      };
      if (data.success) {
        if (data.valid) {
          setSuccess(`Backup is valid! ID: ${data.backup_id}, Version: ${data.backup_version}`);
        } else {
          setError('Backup integrity check failed - backup may be corrupted');
        }
      } else {
        setError(data.message || 'Failed to validate backup');
      }
    } catch (err) {
      setError('Network error or invalid backup data format');
    } finally {
      setIsLoading(false);
    }
  };

  const downloadBackup = (backup: BackupData) => {
    const dataStr = JSON.stringify(backup.backup_data, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `backup-${backup.backup_id}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const getBadgeVariant = (type: string) => {
    switch (type) {
      case 'warning': return 'destructive';
      case 'suggestion': return 'default';
      default: return 'secondary';
    }
  };

  const getIconForType = (type: string) => {
    switch (type) {
      case 'warning': return <AlertTriangle className="h-4 w-4" />;
      case 'suggestion': return <Info className="h-4 w-4" />;
      default: return <CheckCircle className="h-4 w-4" />;
    }
  };

  return (
    <div className={className}>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Key className="h-5 w-5" />
            Key Recovery & Backup Manager
          </CardTitle>
          <CardDescription>
            Securely backup and restore your end-to-end encryption keys
          </CardDescription>
        </CardHeader>
        <CardContent>
          {error && (
            <Alert variant="destructive" className="mb-4">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Error</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          
          {success && (
            <Alert className="mb-4">
              <CheckCircle className="h-4 w-4" />
              <AlertTitle>Success</AlertTitle>
              <AlertDescription>{success}</AlertDescription>
            </Alert>
          )}

          <Tabs value={activeTab} onValueChange={setActiveTab}>
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="status">Status</TabsTrigger>
              <TabsTrigger value="backup">Backup</TabsTrigger>
              <TabsTrigger value="restore">Restore</TabsTrigger>
              <TabsTrigger value="history">History</TabsTrigger>
            </TabsList>

            <TabsContent value="status" className="space-y-4">
              {isLoading ? (
                <div className="flex items-center justify-center p-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                </div>
              ) : recoveryStatus ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                      <CardContent className="p-4">
                        <div className="text-2xl font-bold">{recoveryStatus.total_conversations}</div>
                        <p className="text-xs text-muted-foreground">Conversations</p>
                      </CardContent>
                    </Card>
                    <Card>
                      <CardContent className="p-4">
                        <div className="text-2xl font-bold">{recoveryStatus.active_keys}</div>
                        <p className="text-xs text-muted-foreground">Active Keys</p>
                      </CardContent>
                    </Card>
                    <Card>
                      <CardContent className="p-4">
                        <div className="text-2xl font-bold">{recoveryStatus.devices_count}</div>
                        <p className="text-xs text-muted-foreground">Devices</p>
                      </CardContent>
                    </Card>
                    <Card>
                      <CardContent className="p-4">
                        <div className="text-2xl font-bold">{recoveryStatus.trusted_devices_count}</div>
                        <p className="text-xs text-muted-foreground">Trusted Devices</p>
                      </CardContent>
                    </Card>
                  </div>

                  <div className="space-y-2">
                    <Label>Security Status</Label>
                    <div className="flex items-center gap-2">
                      {recoveryStatus.has_public_key ? (
                        <Badge variant="default" className="flex items-center gap-1">
                          <Shield className="h-3 w-3" />
                          Keys Configured
                        </Badge>
                      ) : (
                        <Badge variant="destructive" className="flex items-center gap-1">
                          <AlertTriangle className="h-3 w-3" />
                          No Keys Found
                        </Badge>
                      )}
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label>Recommendations</Label>
                    <div className="space-y-2">
                      {recoveryStatus.backup_recommendations.map((rec, index) => (
                        <div key={index} className="flex items-start gap-2 p-3 border rounded">
                          {getIconForType(rec.type)}
                          <div className="flex-1">
                            <div className="flex items-center gap-2 mb-1">
                              <Badge variant={getBadgeVariant(rec.type)} className="text-xs">
                                {rec.type.toUpperCase()}
                              </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">{rec.message}</p>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="text-center p-8 text-muted-foreground">
                  Failed to load recovery status
                </div>
              )}
            </TabsContent>

            <TabsContent value="backup" className="space-y-4">
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="master-password">Master Password (Optional)</Label>
                  <Input
                    id="master-password"
                    type="password"
                    placeholder="Enter master password for encrypted backup"
                    value={masterPassword}
                    onChange={(e) => setMasterPassword(e.target.value)}
                  />
                </div>

                {masterPassword && (
                  <div className="space-y-2">
                    <Label htmlFor="confirm-password">Confirm Master Password</Label>
                    <Input
                      id="confirm-password"
                      type="password"
                      placeholder="Confirm master password"
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                    />
                  </div>
                )}

                <div className="flex gap-2">
                  <Button 
                    onClick={() => createBackup(false)} 
                    disabled={isLoading}
                    className="flex items-center gap-2"
                  >
                    <Archive className="h-4 w-4" />
                    Create Full Backup
                  </Button>
                  <Button 
                    variant="outline"
                    onClick={() => createBackup(true, new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString())} 
                    disabled={isLoading}
                    className="flex items-center gap-2"
                  >
                    <Clock className="h-4 w-4" />
                    Incremental (7 days)
                  </Button>
                </div>

                {lastBackup && (
                  <Card>
                    <CardHeader>
                      <CardTitle className="text-sm">Latest Backup</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                      <div className="flex justify-between text-sm">
                        <span>Backup ID:</span>
                        <span className="font-mono">{lastBackup.backup_id}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span>Created:</span>
                        <span>{new Date(lastBackup.backup_timestamp).toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span>Conversations:</span>
                        <span>{lastBackup.conversations_count}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span>Encrypted:</span>
                        <span>{lastBackup.encrypted ? 'Yes' : 'No'}</span>
                      </div>
                      <Button 
                        size="sm" 
                        variant="outline" 
                        onClick={() => downloadBackup(lastBackup)}
                        className="w-full flex items-center gap-2"
                      >
                        <FileDown className="h-4 w-4" />
                        Download Backup
                      </Button>
                    </CardContent>
                  </Card>
                )}
              </div>
            </TabsContent>

            <TabsContent value="restore" className="space-y-4">
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="backup-data">Backup Data</Label>
                  <Textarea
                    id="backup-data"
                    placeholder="Paste your backup JSON data here..."
                    value={backupData}
                    onChange={(e) => setBackupData(e.target.value)}
                    rows={8}
                    className="font-mono text-xs"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="private-key">Your Private Key</Label>
                  <Textarea
                    id="private-key"
                    placeholder="-----BEGIN PRIVATE KEY-----"
                    value={privateKey}
                    onChange={(e) => setPrivateKey(e.target.value)}
                    rows={10}
                    className="font-mono text-xs"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="restore-password">Master Password (if encrypted)</Label>
                  <Input
                    id="restore-password"
                    type="password"
                    placeholder="Enter master password if backup is encrypted"
                    value={masterPassword}
                    onChange={(e) => setMasterPassword(e.target.value)}
                  />
                </div>

                <div className="flex gap-2">
                  <Button 
                    onClick={validateBackup} 
                    disabled={isLoading || !backupData.trim()}
                    variant="outline"
                    className="flex items-center gap-2"
                  >
                    <CheckCircle className="h-4 w-4" />
                    Validate Backup
                  </Button>
                  <Button 
                    onClick={restoreFromBackup} 
                    disabled={isLoading || !backupData.trim() || !privateKey.trim()}
                    className="flex items-center gap-2"
                  >
                    <FileUp className="h-4 w-4" />
                    Restore from Backup
                  </Button>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="history" className="space-y-4">
              <div className="text-center p-8 text-muted-foreground">
                <History className="h-12 w-12 mx-auto mb-4" />
                <p>Backup history feature coming soon...</p>
                <p className="text-sm">Track your backup history and restore points.</p>
              </div>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  );
}