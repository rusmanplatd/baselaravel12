import React, { useState, useEffect, useRef } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
  QrCodeIcon,
  DevicePhoneMobileIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClipboardDocumentIcon,
  ArrowPathIcon
} from '@heroicons/react/24/outline';
import { multiDeviceE2EEService, type DeviceVerificationChallenge } from '@/services/MultiDeviceE2EEService';

interface QRDeviceVerificationProps {
  deviceId: string;
  onVerificationComplete?: (success: boolean) => void;
  onClose?: () => void;
}

// Simple QR code component (you might want to use a proper QR library)
const QRCodeDisplay = ({ data, size = 200 }: { data: string; size?: number }) => {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    if (canvasRef.current && data) {
      // This is a placeholder - in a real app you'd use a QR code library
      // like 'qrcode' npm package
      const canvas = canvasRef.current;
      const ctx = canvas.getContext('2d');
      
      if (ctx) {
        // Clear canvas
        ctx.clearRect(0, 0, size, size);
        
        // Simple placeholder pattern
        ctx.fillStyle = '#000';
        for (let i = 0; i < size; i += 10) {
          for (let j = 0; j < size; j += 10) {
            if (Math.random() > 0.5) {
              ctx.fillRect(i, j, 8, 8);
            }
          }
        }
        
        // Add corners (QR finder patterns)
        const cornerSize = 40;
        [[0, 0], [size - cornerSize, 0], [0, size - cornerSize]].forEach(([x, y]) => {
          ctx.fillStyle = '#000';
          ctx.fillRect(x, y, cornerSize, cornerSize);
          ctx.fillStyle = '#fff';
          ctx.fillRect(x + 6, y + 6, cornerSize - 12, cornerSize - 12);
          ctx.fillStyle = '#000';
          ctx.fillRect(x + 12, y + 12, cornerSize - 24, cornerSize - 24);
        });
      }
    }
  }, [data, size]);

  return (
    <canvas
      ref={canvasRef}
      width={size}
      height={size}
      className="border border-gray-200 rounded-lg"
      style={{ imageRendering: 'pixelated' }}
    />
  );
};

export default function QRDeviceVerification({ 
  deviceId, 
  onVerificationComplete, 
  onClose 
}: QRDeviceVerificationProps) {
  const [step, setStep] = useState<'generate' | 'display' | 'waiting' | 'manual' | 'complete'>('generate');
  const [qrData, setQrData] = useState<{ qrCode: string; verificationUrl: string } | null>(null);
  const [verificationChallenge, setVerificationChallenge] = useState<DeviceVerificationChallenge | null>(null);
  const [manualCode, setManualCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [timeLeft, setTimeLeft] = useState(300); // 5 minutes

  // Generate QR code when component mounts
  useEffect(() => {
    generateQRCode();
  }, [deviceId]);

  // Countdown timer
  useEffect(() => {
    if (step === 'display' || step === 'waiting') {
      const timer = setInterval(() => {
        setTimeLeft(prev => {
          if (prev <= 1) {
            setError('Verification expired. Please generate a new QR code.');
            setStep('generate');
            return 300;
          }
          return prev - 1;
        });
      }, 1000);

      return () => clearInterval(timer);
    }
  }, [step]);

  const generateQRCode = async () => {
    try {
      setLoading(true);
      setError(null);
      
      // Generate QR code for device verification
      const qrResult = await multiDeviceE2EEService.generateVerificationQRCode();
      setQrData(qrResult);
      
      // Also initiate verification challenge
      const challenge = await multiDeviceE2EEService.initiateDeviceVerification({
        method: 'qr_code',
        timeout: 300
      });
      setVerificationChallenge(challenge);
      
      setStep('display');
      setTimeLeft(300);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to generate QR code');
    } finally {
      setLoading(false);
    }
  };

  const handleManualVerification = async () => {
    if (!manualCode.trim() || !verificationChallenge) {
      setError('Please enter the verification code');
      return;
    }

    try {
      setLoading(true);
      setError(null);
      
      const success = await multiDeviceE2EEService.completeDeviceVerification(
        verificationChallenge.challengeId,
        {
          type: 'verification_code',
          code: manualCode.trim()
        },
        { trustDevice: true }
      );

      if (success) {
        setStep('complete');
        onVerificationComplete?.(true);
      } else {
        setError('Verification failed. Please check your code and try again.');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verification failed');
    } finally {
      setLoading(false);
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text).then(() => {
      // You might want to show a toast notification here
      console.log('Copied to clipboard');
    });
  };

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  return (
    <div className="max-w-md mx-auto">
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <DevicePhoneMobileIcon className="h-6 w-6" />
              <div>
                <CardTitle>Device Verification</CardTitle>
                <CardDescription>Verify this device using QR code or manual entry</CardDescription>
              </div>
            </div>
            {onClose && (
              <Button variant="outline" size="sm" onClick={onClose}>
                Close
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {error && (
            <Alert variant="destructive">
              <ExclamationTriangleIcon className="h-4 w-4" />
              <AlertTitle>Error</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          {/* Step: Generate QR Code */}
          {step === 'generate' && (
            <div className="text-center space-y-4">
              <div className="p-8">
                <QrCodeIcon className="h-16 w-16 mx-auto text-gray-400 mb-4" />
                <p className="text-gray-600">Click to generate a QR code for device verification</p>
              </div>
              <Button onClick={generateQRCode} disabled={loading} className="w-full">
                {loading ? (
                  <ArrowPathIcon className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <QrCodeIcon className="h-4 w-4 mr-2" />
                )}
                {loading ? 'Generating...' : 'Generate QR Code'}
              </Button>
            </div>
          )}

          {/* Step: Display QR Code */}
          {step === 'display' && qrData && (
            <div className="space-y-4">
              <div className="text-center">
                <QRCodeDisplay data={qrData.qrCode} size={200} />
                <div className="mt-2 text-sm text-gray-600">
                  Expires in: <Badge variant="outline">{formatTime(timeLeft)}</Badge>
                </div>
              </div>

              <div className="space-y-3">
                <div className="text-sm">
                  <p className="font-medium mb-2">To verify this device:</p>
                  <ol className="list-decimal list-inside space-y-1 text-gray-600">
                    <li>Open the chat app on another trusted device</li>
                    <li>Go to Device Management</li>
                    <li>Scan this QR code</li>
                    <li>Follow the verification prompt</li>
                  </ol>
                </div>

                <div className="flex space-x-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => copyToClipboard(qrData.verificationUrl)}
                    className="flex-1"
                  >
                    <ClipboardDocumentIcon className="h-4 w-4 mr-2" />
                    Copy Link
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setStep('manual')}
                    className="flex-1"
                  >
                    Manual Entry
                  </Button>
                </div>

                <Button onClick={generateQRCode} variant="outline" className="w-full">
                  <ArrowPathIcon className="h-4 w-4 mr-2" />
                  Regenerate QR Code
                </Button>
              </div>
            </div>
          )}

          {/* Step: Manual Verification */}
          {step === 'manual' && (
            <div className="space-y-4">
              <div>
                <Label htmlFor="manual-code">Verification Code</Label>
                <Input
                  id="manual-code"
                  value={manualCode}
                  onChange={(e) => setManualCode(e.target.value)}
                  placeholder="Enter verification code from trusted device"
                  className="mt-1"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Get this code from Device Management on a trusted device
                </p>
              </div>

              <div className="flex space-x-2">
                <Button
                  onClick={() => setStep('display')}
                  variant="outline"
                  className="flex-1"
                >
                  Back to QR
                </Button>
                <Button
                  onClick={handleManualVerification}
                  disabled={loading || !manualCode.trim()}
                  className="flex-1"
                >
                  {loading ? (
                    <ArrowPathIcon className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <CheckCircleIcon className="h-4 w-4 mr-2" />
                  )}
                  {loading ? 'Verifying...' : 'Verify'}
                </Button>
              </div>
            </div>
          )}

          {/* Step: Complete */}
          {step === 'complete' && (
            <div className="text-center space-y-4">
              <div className="p-8">
                <CheckCircleIcon className="h-16 w-16 mx-auto text-green-500 mb-4" />
                <h3 className="text-lg font-medium text-green-800">Device Verified!</h3>
                <p className="text-gray-600 mt-2">
                  This device is now trusted and can access encrypted conversations.
                </p>
              </div>
              {onClose && (
                <Button onClick={onClose} className="w-full">
                  Close
                </Button>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}