import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { 
  ArrowPathIcon,
  CloudArrowUpIcon,
  CloudArrowDownIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  SignalIcon
} from '@heroicons/react/24/outline';
import { multiDeviceE2EEService, type SyncReport, type MessageSyncData } from '@/services/MultiDeviceE2EEService';
import { formatDistanceToNow } from 'date-fns';

interface DeviceSyncProps {
  conversationId?: string;
  onSyncComplete?: (report: SyncReport) => void;
}

export default function DeviceSync({ conversationId, onSyncComplete }: DeviceSyncProps) {
  const [syncReport, setSyncReport] = useState<SyncReport | null>(null);
  const [isSyncing, setIsSyncing] = useState(false);
  const [isRecovering, setIsRecovering] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [recoveredMessages, setRecoveredMessages] = useState<{
    recovered: any[];
    failed: string[];
  } | null>(null);

  // Load current sync status on mount
  useEffect(() => {
    const currentStatus = multiDeviceE2EEService.getSyncStatus(conversationId);
    setSyncReport(currentStatus);
  }, [conversationId]);

  const handleSync = async () => {
    try {
      setIsSyncing(true);
      setError(null);
      
      const report = await multiDeviceE2EEService.syncMessagesAcrossDevices(conversationId);
      setSyncReport(report);
      onSyncComplete?.(report);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Sync failed');
    } finally {
      setIsSyncing(false);
    }
  };

  const handleRecoverMessages = async () => {
    if (!conversationId) {
      setError('Conversation ID is required for message recovery');
      return;
    }

    try {
      setIsRecovering(true);
      setError(null);
      
      // Recover messages from the last 24 hours
      const fromTimestamp = Date.now() - (24 * 60 * 60 * 1000);
      const result = await multiDeviceE2EEService.recoverMissngMessages(conversationId, fromTimestamp);
      
      setRecoveredMessages(result);
      
      // If we recovered messages, trigger a sync
      if (result.recovered.length > 0) {
        await handleSync();
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Message recovery failed');
    } finally {
      setIsRecovering(false);
    }
  };

  const handleRetrySyncQueue = async () => {
    try {
      setIsSyncing(true);
      setError(null);
      
      await multiDeviceE2EEService.retrySyncQueue();
      
      // Update sync status
      const currentStatus = multiDeviceE2EEService.getSyncStatus(conversationId);
      setSyncReport(currentStatus);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Retry failed');
    } finally {
      setIsSyncing(false);
    }
  };

  const getSyncStatusBadge = (report: SyncReport) => {
    if (report.pendingMessages === 0 && report.failedMessages === 0) {
      return <Badge className="bg-green-100 text-green-800">In Sync</Badge>;
    } else if (report.failedMessages > 0) {
      return <Badge className="bg-red-100 text-red-800">Sync Issues</Badge>;
    } else if (report.pendingMessages > 0) {
      return <Badge className="bg-yellow-100 text-yellow-800">Syncing</Badge>;
    }
    return <Badge className="bg-gray-100 text-gray-800">Unknown</Badge>;
  };

  const getSyncProgress = (report: SyncReport) => {
    const total = report.totalMessages;
    if (total === 0) return 100;
    return Math.round((report.syncedMessages / total) * 100);
  };

  return (
    <div className="space-y-4">
      {error && (
        <Alert variant="destructive">
          <ExclamationTriangleIcon className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Sync Status Card */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-lg">Device Synchronization</CardTitle>
              <CardDescription>
                Keep messages synced across all your devices
              </CardDescription>
            </div>
            {syncReport && getSyncStatusBadge(syncReport)}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {syncReport ? (
            <>
              {/* Sync Progress */}
              {syncReport.totalMessages > 0 && (
                <div>
                  <div className="flex justify-between text-sm mb-2">
                    <span>Sync Progress</span>
                    <span>{getSyncProgress(syncReport)}%</span>
                  </div>
                  <Progress value={getSyncProgress(syncReport)} className="h-2" />
                </div>
              )}

              {/* Sync Statistics */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div className="p-3 bg-gray-50 rounded-lg">
                  <div className="text-2xl font-bold text-gray-900">{syncReport.syncedMessages}</div>
                  <div className="text-sm text-gray-600">Synced</div>
                </div>
                <div className="p-3 bg-yellow-50 rounded-lg">
                  <div className="text-2xl font-bold text-yellow-800">{syncReport.pendingMessages}</div>
                  <div className="text-sm text-yellow-600">Pending</div>
                </div>
                <div className="p-3 bg-red-50 rounded-lg">
                  <div className="text-2xl font-bold text-red-800">{syncReport.failedMessages}</div>
                  <div className="text-sm text-red-600">Failed</div>
                </div>
                <div className="p-3 bg-blue-50 rounded-lg">
                  <div className="text-2xl font-bold text-blue-800">{syncReport.totalMessages}</div>
                  <div className="text-sm text-blue-600">Total</div>
                </div>
              </div>

              {/* Last Sync Time */}
              <div className="flex items-center text-sm text-gray-600">
                <ClockIcon className="h-4 w-4 mr-2" />
                Last sync: {formatDistanceToNow(syncReport.lastSyncAt)} ago
              </div>

              {/* Sync Errors */}
              {syncReport.syncErrors.length > 0 && (
                <div>
                  <h4 className="font-medium text-sm text-red-800 mb-2">Sync Errors:</h4>
                  <div className="space-y-1">
                    {syncReport.syncErrors.slice(0, 3).map((error, index) => (
                      <div key={index} className="text-xs bg-red-50 p-2 rounded border-l-2 border-red-200">
                        <div className="font-medium">{error.conversationId}</div>
                        <div className="text-red-600">{error.error}</div>
                      </div>
                    ))}
                    {syncReport.syncErrors.length > 3 && (
                      <div className="text-xs text-gray-500">
                        +{syncReport.syncErrors.length - 3} more errors
                      </div>
                    )}
                  </div>
                </div>
              )}
            </>
          ) : (
            <div className="text-center py-4">
              <div className="text-gray-500">No sync data available</div>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex flex-wrap gap-2 pt-4 border-t">
            <Button
              onClick={handleSync}
              disabled={isSyncing}
              variant="outline"
              size="sm"
            >
              {isSyncing ? (
                <ArrowPathIcon className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <CloudArrowUpIcon className="h-4 w-4 mr-2" />
              )}
              {isSyncing ? 'Syncing...' : 'Sync Now'}
            </Button>

            {conversationId && (
              <Button
                onClick={handleRecoverMessages}
                disabled={isRecovering}
                variant="outline"
                size="sm"
              >
                {isRecovering ? (
                  <ArrowPathIcon className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <CloudArrowDownIcon className="h-4 w-4 mr-2" />
                )}
                {isRecovering ? 'Recovering...' : 'Recover Messages'}
              </Button>
            )}

            {syncReport && syncReport.failedMessages > 0 && (
              <Button
                onClick={handleRetrySyncQueue}
                disabled={isSyncing}
                variant="outline"
                size="sm"
              >
                <SignalIcon className="h-4 w-4 mr-2" />
                Retry Failed
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Recovered Messages Info */}
      {recoveredMessages && recoveredMessages.recovered.length > 0 && (
        <Alert>
          <CheckCircleIcon className="h-4 w-4" />
          <AlertDescription>
            Successfully recovered {recoveredMessages.recovered.length} messages from other devices.
            {recoveredMessages.failed.length > 0 && (
              <span className="block mt-1 text-red-600">
                Failed to decrypt {recoveredMessages.failed.length} messages.
              </span>
            )}
          </AlertDescription>
        </Alert>
      )}
    </div>
  );
}