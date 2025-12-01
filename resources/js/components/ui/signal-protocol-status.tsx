/**
 * Signal Protocol Status Component
 * Shows the current status of Signal Protocol encryption including:
 * - Session information
 * - Identity verification status
 * - Key statistics
 * - Health metrics
 */

import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { 
  Shield, 
  Key, 
  Check, 
  AlertTriangle, 
  Clock, 
  Users, 
  MessageCircle,
  RefreshCw,
  Eye,
  Lock
} from 'lucide-react';
import type { SessionInfo } from '@/services/SignalSessionManager';

interface SignalProtocolStatusProps {
  sessionInfo?: SessionInfo | null;
  statistics?: {
    sessionStats: any;
    protocolStats: any;
    x3dhStats: any;
  } | null;
  healthScore?: {
    score: number;
    status: 'healthy' | 'warning' | 'critical';
    issues: string[];
  };
  onVerifyIdentity?: () => void;
  onRotateKeys?: () => void;
  onRefreshStats?: () => void;
  className?: string;
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'verified':
    case 'trusted':
      return 'bg-green-100 text-green-800 border-green-200';
    case 'unverified':
      return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    default:
      return 'bg-gray-100 text-gray-800 border-gray-200';
  }
};

const getHealthColor = (status: 'healthy' | 'warning' | 'critical') => {
  switch (status) {
    case 'healthy':
      return 'text-green-600';
    case 'warning':
      return 'text-yellow-600';
    case 'critical':
      return 'text-red-600';
    default:
      return 'text-gray-600';
  }
};

export function SignalProtocolStatus({
  sessionInfo,
  statistics,
  healthScore,
  onVerifyIdentity,
  onRotateKeys,
  onRefreshStats,
  className = ''
}: SignalProtocolStatusProps) {
  const formatDate = (date: Date | string) => {
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleString();
  };

  const formatTimeAgo = (date: Date | string) => {
    const d = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffInMinutes = Math.floor((now.getTime() - d.getTime()) / (1000 * 60));
    
    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
    if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
    return `${Math.floor(diffInMinutes / 1440)}d ago`;
  };

  return (
    <div className={`space-y-4 ${className}`}>
      {/* Health Overview */}
      {healthScore && (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Signal Protocol Health</CardTitle>
            <Button 
              variant="ghost" 
              size="sm" 
              onClick={onRefreshStats}
              className="h-8 w-8 p-0"
            >
              <RefreshCw className="h-4 w-4" />
            </Button>
          </CardHeader>
          <CardContent>
            <div className="flex items-center space-x-2">
              <Shield className={`h-5 w-5 ${getHealthColor(healthScore.status)}`} />
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Health Score</span>
                  <span className={`text-sm ${getHealthColor(healthScore.status)}`}>
                    {healthScore.score}/100
                  </span>
                </div>
                <Progress 
                  value={healthScore.score} 
                  className="mt-1 h-2"
                />
              </div>
            </div>
            
            {healthScore.issues.length > 0 && (
              <Alert className="mt-3">
                <AlertTriangle className="h-4 w-4" />
                <AlertDescription>
                  <ul className="list-disc list-inside text-xs space-y-1">
                    {healthScore.issues.map((issue, index) => (
                      <li key={index}>{issue}</li>
                    ))}
                  </ul>
                </AlertDescription>
              </Alert>
            )}
          </CardContent>
        </Card>
      )}

      {/* Session Information */}
      {sessionInfo ? (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <MessageCircle className="h-5 w-5" />
              <span>Active Session</span>
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Status</span>
              <Badge className={getStatusColor(sessionInfo.verificationStatus)}>
                {sessionInfo.verificationStatus === 'verified' ? (
                  <><Check className="h-3 w-3 mr-1" /> Verified</>
                ) : sessionInfo.verificationStatus === 'trusted' ? (
                  <><Shield className="h-3 w-3 mr-1" /> Trusted</>
                ) : (
                  <><AlertTriangle className="h-3 w-3 mr-1" /> Unverified</>
                )}
              </Badge>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Protocol Version</span>
              <span className="text-sm font-medium">{sessionInfo.protocolVersion}</span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Messages Exchanged</span>
              <span className="text-sm font-medium">
                {sessionInfo.messagesSent + sessionInfo.messagesReceived}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Key Rotations</span>
              <span className="text-sm font-medium">{sessionInfo.keyRotations}</span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Last Activity</span>
              <span className="text-sm text-gray-500" title={formatDate(sessionInfo.lastActivity)}>
                {formatTimeAgo(sessionInfo.lastActivity)}
              </span>
            </div>

            <div className="flex space-x-2 pt-2">
              {sessionInfo.verificationStatus === 'unverified' && (
                <Button 
                  onClick={onVerifyIdentity}
                  size="sm"
                  variant="outline"
                  className="flex-1"
                >
                  <Eye className="h-4 w-4 mr-1" />
                  Verify Identity
                </Button>
              )}
              <Button 
                onClick={onRotateKeys}
                size="sm"
                variant="outline"
                className="flex-1"
              >
                <RefreshCw className="h-4 w-4 mr-1" />
                Rotate Keys
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="py-6">
            <div className="text-center text-gray-500">
              <Lock className="h-8 w-8 mx-auto mb-2 opacity-50" />
              <p className="text-sm">No active Signal Protocol session</p>
              <p className="text-xs text-gray-400 mt-1">
                Send a message to establish an encrypted session
              </p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Statistics Overview */}
      {statistics && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Session Statistics */}
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium flex items-center">
                <Users className="h-4 w-4 mr-1" />
                Sessions
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Active</span>
                <span className="font-medium">{statistics.sessionStats.activeSessions}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Total</span>
                <span className="font-medium">{statistics.sessionStats.totalSessions}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Verified</span>
                <span className="font-medium">{statistics.sessionStats.verifiedSessions}</span>
              </div>
            </CardContent>
          </Card>

          {/* X3DH Statistics */}
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium flex items-center">
                <Key className="h-4 w-4 mr-1" />
                Keys
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Identity Key</span>
                <Badge variant={statistics.x3dhStats.identityKeyExists ? 'default' : 'destructive'}>
                  {statistics.x3dhStats.identityKeyExists ? 'Active' : 'Missing'}
                </Badge>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Signed Prekeys</span>
                <span className="font-medium">{statistics.x3dhStats.signedPreKeys}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">One-time Prekeys</span>
                <span className="font-medium">{statistics.x3dhStats.oneTimePreKeys}</span>
              </div>
            </CardContent>
          </Card>

          {/* Protocol Statistics */}
          <Card className="md:col-span-2">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium flex items-center">
                <MessageCircle className="h-4 w-4 mr-1" />
                Message Statistics
              </CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600">
                  {statistics.sessionStats.totalMessagesExchanged}
                </div>
                <div className="text-xs text-gray-500">Total Messages</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600">
                  {statistics.sessionStats.keyRotationsPerformed}
                </div>
                <div className="text-xs text-gray-500">Key Rotations</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-purple-600">
                  {statistics.sessionStats.verifiedSessions}
                </div>
                <div className="text-xs text-gray-500">Verified</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-orange-600">
                  {Math.round(statistics.sessionStats.averageSessionAge / (1000 * 60 * 60 * 24))}d
                </div>
                <div className="text-xs text-gray-500">Avg Age</div>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}

export default SignalProtocolStatus;