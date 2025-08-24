import React, { Component, ErrorInfo, ReactNode } from 'react';
import { AlertTriangle, RefreshCw, Shield, AlertCircle, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
  showDetails?: boolean;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
  errorType: 'encryption' | 'decryption' | 'keyGeneration' | 'keyExchange' | 'storage' | 'network' | 'unknown';
  retryCount: number;
}

export class E2EEErrorBoundary extends Component<Props, State> {
  private maxRetries = 3;

  constructor(props: Props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
      errorType: 'unknown',
      retryCount: 0
    };
  }

  static getDerivedStateFromError(error: Error): Partial<State> {
    return {
      hasError: true,
      error
    };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    this.setState({
      errorInfo,
      errorType: this.categorizeError(error)
    });

    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }

    // Log to console for debugging
    console.error('E2EE Error Boundary caught an error:', error, errorInfo);
  }

  private categorizeError(error: Error): State['errorType'] {
    const message = error.message.toLowerCase();
    
    if (message.includes('decrypt') || message.includes('decryption')) {
      return 'decryption';
    }
    if (message.includes('encrypt') || message.includes('encryption')) {
      return 'encryption';
    }
    if (message.includes('key') && message.includes('generat')) {
      return 'keyGeneration';
    }
    if (message.includes('key') && (message.includes('exchange') || message.includes('distribution'))) {
      return 'keyExchange';
    }
    if (message.includes('storage') || message.includes('indexeddb') || message.includes('database')) {
      return 'storage';
    }
    if (message.includes('fetch') || message.includes('network') || message.includes('connection')) {
      return 'network';
    }
    
    return 'unknown';
  }

  private getErrorIcon() {
    switch (this.state.errorType) {
      case 'encryption':
      case 'decryption':
        return <Shield className="h-5 w-5 text-red-500" />;
      case 'keyGeneration':
      case 'keyExchange':
        return <AlertCircle className="h-5 w-5 text-orange-500" />;
      case 'storage':
        return <AlertTriangle className="h-5 w-5 text-yellow-500" />;
      case 'network':
        return <ExternalLink className="h-5 w-5 text-blue-500" />;
      default:
        return <AlertTriangle className="h-5 w-5 text-red-500" />;
    }
  }

  private getErrorTitle() {
    switch (this.state.errorType) {
      case 'encryption':
        return 'Encryption Failed';
      case 'decryption':
        return 'Unable to Decrypt Message';
      case 'keyGeneration':
        return 'Key Generation Error';
      case 'keyExchange':
        return 'Key Exchange Failed';
      case 'storage':
        return 'Storage Access Error';
      case 'network':
        return 'Network Connection Error';
      default:
        return 'Encryption System Error';
    }
  }

  private getErrorDescription() {
    switch (this.state.errorType) {
      case 'encryption':
        return 'Your message could not be encrypted. This may be due to missing encryption keys or a temporary system issue.';
      case 'decryption':
        return 'This message could not be decrypted. You may not have the necessary decryption keys or the message may be corrupted.';
      case 'keyGeneration':
        return 'Failed to generate encryption keys. This is required for secure messaging. Please try again or contact support.';
      case 'keyExchange':
        return 'Could not establish secure communication with other participants. Some users may not receive encrypted messages.';
      case 'storage':
        return 'Cannot access secure storage. Your encryption keys may not be available, affecting message security.';
      case 'network':
        return 'Network error while communicating with the encryption service. Please check your connection and try again.';
      default:
        return 'An unexpected error occurred in the encryption system. Your messages may not be properly secured.';
    }
  }

  private getRecoveryActions() {
    const actions = [];
    
    switch (this.state.errorType) {
      case 'encryption':
        actions.push(
          { label: 'Retry Encryption', action: () => this.handleRetry(), primary: true },
          { label: 'Reset Keys', action: () => this.handleKeyReset(), destructive: true }
        );
        break;
      case 'decryption':
        actions.push(
          { label: 'Retry Decryption', action: () => this.handleRetry(), primary: true },
          { label: 'Request New Keys', action: () => this.handleKeyRequest() }
        );
        break;
      case 'keyGeneration':
        actions.push(
          { label: 'Generate New Keys', action: () => this.handleKeyGeneration(), primary: true },
          { label: 'Clear Storage', action: () => this.handleStorageClear(), destructive: true }
        );
        break;
      case 'keyExchange':
        actions.push(
          { label: 'Retry Exchange', action: () => this.handleRetry(), primary: true },
          { label: 'Manual Setup', action: () => this.handleManualSetup() }
        );
        break;
      case 'storage':
        actions.push(
          { label: 'Retry Access', action: () => this.handleRetry(), primary: true },
          { label: 'Reset Storage', action: () => this.handleStorageReset(), destructive: true }
        );
        break;
      case 'network':
        actions.push(
          { label: 'Retry Connection', action: () => this.handleRetry(), primary: true },
          { label: 'Work Offline', action: () => this.handleOfflineMode() }
        );
        break;
      default:
        actions.push(
          { label: 'Try Again', action: () => this.handleRetry(), primary: true },
          { label: 'Reset System', action: () => this.handleSystemReset(), destructive: true }
        );
    }
    
    return actions;
  }

  private handleRetry = () => {
    if (this.state.retryCount < this.maxRetries) {
      this.setState(prevState => ({
        hasError: false,
        error: null,
        errorInfo: null,
        retryCount: prevState.retryCount + 1
      }));
    }
  };

  private handleKeyReset = () => {
    // Trigger key reset through global event
    window.dispatchEvent(new CustomEvent('e2ee:reset-keys'));
    this.handleRetry();
  };

  private handleKeyRequest = () => {
    // Trigger key request through global event
    window.dispatchEvent(new CustomEvent('e2ee:request-keys'));
    this.handleRetry();
  };

  private handleKeyGeneration = () => {
    // Trigger key generation through global event
    window.dispatchEvent(new CustomEvent('e2ee:generate-keys'));
    this.handleRetry();
  };

  private handleManualSetup = () => {
    // Trigger manual setup through global event
    window.dispatchEvent(new CustomEvent('e2ee:manual-setup'));
  };

  private handleStorageClear = () => {
    // Clear local storage and trigger reset
    localStorage.removeItem('e2ee_settings');
    window.dispatchEvent(new CustomEvent('e2ee:storage-cleared'));
    this.handleRetry();
  };

  private handleStorageReset = () => {
    // Reset storage through global event
    window.dispatchEvent(new CustomEvent('e2ee:reset-storage'));
    this.handleRetry();
  };

  private handleOfflineMode = () => {
    // Enable offline mode
    window.dispatchEvent(new CustomEvent('e2ee:offline-mode'));
    this.handleRetry();
  };

  private handleSystemReset = () => {
    // Complete system reset
    window.dispatchEvent(new CustomEvent('e2ee:system-reset'));
    this.handleRetry();
  };

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      const canRetry = this.state.retryCount < this.maxRetries;
      const recoveryActions = this.getRecoveryActions();

      return (
        <Card className="max-w-2xl mx-auto m-6 border-red-200">
          <CardHeader>
            <div className="flex items-center space-x-2">
              {this.getErrorIcon()}
              <CardTitle className="text-red-700">{this.getErrorTitle()}</CardTitle>
              <Badge variant="destructive">E2EE Error</Badge>
            </div>
            <CardDescription>
              {this.getErrorDescription()}
            </CardDescription>
          </CardHeader>
          
          <CardContent className="space-y-4">
            {this.state.error && (
              <Alert>
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle>Error Details</AlertTitle>
                <AlertDescription className="font-mono text-sm">
                  {this.state.error.message}
                </AlertDescription>
              </Alert>
            )}

            <div className="flex flex-wrap gap-2">
              {recoveryActions.map((action, index) => (
                <Button
                  key={index}
                  onClick={action.action}
                  variant={action.primary ? 'default' : action.destructive ? 'destructive' : 'outline'}
                  size="sm"
                  disabled={!canRetry && action.primary}
                >
                  {action.primary && <RefreshCw className="h-4 w-4 mr-1" />}
                  {action.label}
                </Button>
              ))}
            </div>

            {!canRetry && (
              <Alert variant="destructive">
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle>Maximum Retries Reached</AlertTitle>
                <AlertDescription>
                  Please try a different recovery option or contact support if the problem persists.
                </AlertDescription>
              </Alert>
            )}

            {this.props.showDetails && this.state.errorInfo && (
              <details className="mt-4">
                <summary className="cursor-pointer text-sm font-medium text-gray-600">
                  Technical Details
                </summary>
                <pre className="mt-2 p-3 bg-gray-50 rounded text-xs overflow-auto max-h-48">
                  {this.state.errorInfo.componentStack}
                </pre>
              </details>
            )}

            <div className="text-xs text-gray-500 pt-2 border-t">
              Error ID: {Date.now().toString(36)} • 
              Retry Count: {this.state.retryCount}/{this.maxRetries} • 
              Type: {this.state.errorType}
            </div>
          </CardContent>
        </Card>
      );
    }

    return this.props.children;
  }
}

// Higher-order component for easy wrapping
export function withE2EEErrorBoundary<P extends object>(
  Component: React.ComponentType<P>,
  errorBoundaryProps?: Omit<Props, 'children'>
) {
  return function WrappedComponent(props: P) {
    return (
      <E2EEErrorBoundary {...errorBoundaryProps}>
        <Component {...props} />
      </E2EEErrorBoundary>
    );
  };
}