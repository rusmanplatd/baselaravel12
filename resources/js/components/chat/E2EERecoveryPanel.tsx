import React, { useState } from 'react';
import { 
  AlertTriangle, 
  CheckCircle, 
  Clock, 
  Play, 
  Pause, 
  RotateCcw, 
  Trash2,
  Shield,
  Activity,
  Settings,
  Eye,
  EyeOff
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { useE2EEErrorRecovery } from '@/hooks/useE2EEErrorRecovery';
import { format } from 'date-fns';
import type { E2EEError, RecoveryStrategy } from '@/services/E2EEErrorRecovery';

interface E2EERecoveryPanelProps {
  onClose?: () => void;
  showDetailsDefault?: boolean;
}

export function E2EERecoveryPanel({ onClose, showDetailsDefault = false }: E2EERecoveryPanelProps) {
  const {
    errorHistory,
    clearHistory,
    isRecovering,
    lastError,
    errorStatistics,
    autoRecoveryEnabled,
    setAutoRecoveryEnabled,
    getRecoveryStrategies,
    executeRecovery
  } = useE2EEErrorRecovery();

  const [showDetails, setShowDetails] = useState(showDetailsDefault);
  const [selectedError, setSelectedError] = useState<E2EEError | null>(null);
  const [executingStrategy, setExecutingStrategy] = useState<string | null>(null);
  const [recoveryProgress, setRecoveryProgress] = useState<{ [key: string]: number }>({});

  const handleExecuteRecovery = async (strategy: RecoveryStrategy) => {
    if (executingStrategy) return;

    setExecutingStrategy(strategy.name);
    
    // Simulate progress for user feedback
    const progressInterval = setInterval(() => {
      setRecoveryProgress(prev => ({
        ...prev,
        [strategy.name]: Math.min((prev[strategy.name] || 0) + (100 / strategy.estimatedTime), 95)
      }));
    }, 1000);

    try {
      const success = await executeRecovery(strategy);
      
      setRecoveryProgress(prev => ({
        ...prev,
        [strategy.name]: success ? 100 : 0
      }));

      if (success) {
        setTimeout(() => {
          setRecoveryProgress(prev => {
            const newProgress = { ...prev };
            delete newProgress[strategy.name];
            return newProgress;
          });
        }, 2000);
      }
    } finally {
      clearInterval(progressInterval);
      setExecutingStrategy(null);
    }
  };

  const getErrorTypeColor = (type: E2EEError['type']) => {
    switch (type) {
      case 'ENCRYPTION_FAILED':
      case 'DECRYPTION_FAILED':
        return 'destructive';
      case 'KEY_NOT_FOUND':
      case 'KEY_CORRUPTED':
        return 'secondary';
      case 'NETWORK_ERROR':
        return 'outline';
      default:
        return 'default';
    }
  };

  const getErrorIcon = (type: E2EEError['type']) => {
    switch (type) {
      case 'ENCRYPTION_FAILED':
      case 'DECRYPTION_FAILED':
        return <Shield className="h-4 w-4" />;
      case 'KEY_NOT_FOUND':
      case 'KEY_CORRUPTED':
        return <AlertTriangle className="h-4 w-4" />;
      default:
        return <AlertTriangle className="h-4 w-4" />;
    }
  };

  const renderErrorsList = (errors: E2EEError[]) => (
    <div className="space-y-2">
      {errors.length === 0 ? (
        <div className="text-center py-8 text-gray-500">
          <CheckCircle className="h-12 w-12 mx-auto mb-2 text-green-500" />
          <p>No encryption errors recorded</p>
        </div>
      ) : (
        errors.map((error, index) => (
          <Card 
            key={index} 
            className={`cursor-pointer transition-colors ${
              selectedError === error ? 'ring-2 ring-blue-500' : ''
            }`}
            onClick={() => setSelectedError(selectedError === error ? null : error)}
          >
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  {getErrorIcon(error.type)}
                  <div>
                    <p className="font-medium">{error.type.replace(/_/g, ' ')}</p>
                    <p className="text-sm text-gray-500">
                      {format(new Date(error.timestamp), 'MMM d, HH:mm:ss')}
                    </p>
                  </div>
                </div>
                <div className="flex items-center space-x-2">
                  <Badge variant={getErrorTypeColor(error.type)}>
                    {error.autoRecoveryAttempts > 0 ? 'Auto-recovered' : 'Pending'}
                  </Badge>
                  {error.conversationId && (
                    <Badge variant="outline" className="text-xs">
                      Conv: {error.conversationId.substring(0, 8)}...
                    </Badge>
                  )}
                </div>
              </div>
              
              {selectedError === error && (
                <div className="mt-4 pt-4 border-t">
                  <p className="text-sm text-gray-600 mb-3">{error.message}</p>
                  
                  <div className="space-y-2">
                    {getRecoveryStrategies(error).map((strategy, strategyIndex) => (
                      <div key={strategyIndex} className="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div className="flex-1">
                          <div className="flex items-center space-x-2">
                            <span className="font-medium text-sm">{strategy.name}</span>
                            {strategy.automatic && <Badge variant="outline" className="text-xs">Auto</Badge>}
                            {strategy.destructive && <Badge variant="destructive" className="text-xs">Destructive</Badge>}
                          </div>
                          <p className="text-xs text-gray-500 mt-1">{strategy.description}</p>
                          <p className="text-xs text-gray-400">Est. {strategy.estimatedTime}s</p>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                          {recoveryProgress[strategy.name] !== undefined && (
                            <div className="w-24">
                              <Progress value={recoveryProgress[strategy.name]} className="h-2" />
                            </div>
                          )}
                          <Button
                            size="sm"
                            variant={strategy.destructive ? "destructive" : "default"}
                            onClick={() => handleExecuteRecovery(strategy)}
                            disabled={executingStrategy === strategy.name || isRecovering}
                          >
                            {executingStrategy === strategy.name ? (
                              <RotateCcw className="h-3 w-3 animate-spin" />
                            ) : (
                              <Play className="h-3 w-3" />
                            )}
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        ))
      )}
    </div>
  );

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold flex items-center space-x-2">
            <Activity className="h-8 w-8" />
            <span>E2EE Recovery Center</span>
          </h1>
          <p className="text-gray-600 mt-1">Monitor and recover from encryption errors</p>
        </div>
        {onClose && (
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
        )}
      </div>

      {/* Statistics Overview */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              <div>
                <p className="text-2xl font-bold">{errorStatistics.total}</p>
                <p className="text-sm text-gray-600">Total Errors</p>
              </div>
            </div>
          </CardContent>
        </Card>
        
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <CheckCircle className="h-4 w-4 text-green-500" />
              <div>
                <p className="text-2xl font-bold">{errorStatistics.recovered}</p>
                <p className="text-sm text-gray-600">Auto-Recovered</p>
              </div>
            </div>
          </CardContent>
        </Card>
        
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <Clock className="h-4 w-4 text-blue-500" />
              <div>
                <p className="text-2xl font-bold">{errorStatistics.recent}</p>
                <p className="text-sm text-gray-600">Last 24h</p>
              </div>
            </div>
          </CardContent>
        </Card>
        
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              {isRecovering ? (
                <RotateCcw className="h-4 w-4 text-orange-500 animate-spin" />
              ) : (
                <Pause className="h-4 w-4 text-gray-500" />
              )}
              <div>
                <p className="text-2xl font-bold">{isRecovering ? 'Active' : 'Idle'}</p>
                <p className="text-sm text-gray-600">Recovery Status</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Latest Error Alert */}
      {lastError && (
        <Alert>
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>Latest Error: {lastError.type.replace(/_/g, ' ')}</AlertTitle>
          <AlertDescription>
            {lastError.message}
            <div className="flex items-center space-x-2 mt-2">
              <Button 
                size="sm" 
                onClick={() => setSelectedError(lastError)}
                variant="outline"
              >
                View Recovery Options
              </Button>
            </div>
          </AlertDescription>
        </Alert>
      )}

      {/* Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center space-x-2">
            <Settings className="h-5 w-5" />
            <span>Recovery Settings</span>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <Label>Automatic Recovery</Label>
              <p className="text-sm text-gray-500">Enable automatic error recovery attempts</p>
            </div>
            <Switch
              checked={autoRecoveryEnabled}
              onCheckedChange={setAutoRecoveryEnabled}
            />
          </div>

          <div className="flex items-center justify-between">
            <div>
              <Label>Show Technical Details</Label>
              <p className="text-sm text-gray-500">Display technical error information</p>
            </div>
            <Switch
              checked={showDetails}
              onCheckedChange={setShowDetails}
            />
          </div>

          <div className="flex items-center space-x-2">
            <Button onClick={clearHistory} variant="outline" size="sm">
              <Trash2 className="h-4 w-4 mr-2" />
              Clear History
            </Button>
            <Button 
              onClick={() => setShowDetails(!showDetails)} 
              variant="ghost" 
              size="sm"
            >
              {showDetails ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Error History Tabs */}
      <Card>
        <CardHeader>
          <CardTitle>Error History</CardTitle>
          <CardDescription>
            Recent encryption errors and recovery actions
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Tabs defaultValue="all">
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="all">All Errors ({errorHistory.length})</TabsTrigger>
              <TabsTrigger value="recent">Recent ({errorStatistics.recent})</TabsTrigger>
              <TabsTrigger value="failed">Failed Recovery</TabsTrigger>
            </TabsList>

            <TabsContent value="all" className="mt-4">
              {renderErrorsList(errorHistory)}
            </TabsContent>

            <TabsContent value="recent" className="mt-4">
              {renderErrorsList(errorHistory.filter(e => 
                Date.now() - new Date(e.timestamp).getTime() < 24 * 60 * 60 * 1000
              ))}
            </TabsContent>

            <TabsContent value="failed" className="mt-4">
              {renderErrorsList(errorHistory.filter(e => 
                e.autoRecoveryAttempts > 0 && !e.recoverable
              ))}
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  );
}