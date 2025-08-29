import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { 
  DevicePhoneMobileIcon, 
  ComputerDesktopIcon,
  GlobeAltIcon,
  DeviceTabletIcon,
  ShieldCheckIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  KeyIcon,
  ClockIcon
} from '@heroicons/react/24/outline';
import { multiDeviceE2EEService, DeviceVerificationChallenge } from '@/services/MultiDeviceE2EEService';

interface DeviceSetupProps {
  onSetupComplete: () => void;
  onSetupError?: (error: string) => void;
}

interface SetupStep {
  id: string;
  title: string;
  description: string;
  completed: boolean;
}

export default function DeviceSetup({ onSetupComplete, onSetupError }: DeviceSetupProps) {
  const [currentStep, setCurrentStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [deviceName, setDeviceName] = useState('');
  const [deviceType, setDeviceType] = useState<'mobile' | 'desktop' | 'web' | 'tablet'>('web');
  const [verificationChallenge, setVerificationChallenge] = useState<DeviceVerificationChallenge | null>(null);
  const [verificationCode, setVerificationCode] = useState('');
  const [registrationResult, setRegistrationResult] = useState<any>(null);

  const steps: SetupStep[] = [
    {
      id: 'device-info',
      title: 'Device Information',
      description: 'Tell us about this device',
      completed: false,
    },
    {
      id: 'registration',
      title: 'Device Registration',
      description: 'Register this device for encrypted messaging',
      completed: false,
    },
    {
      id: 'verification',
      title: 'Device Verification',
      description: 'Verify this device to enable encryption',
      completed: false,
    },
    {
      id: 'complete',
      title: 'Setup Complete',
      description: 'Your device is ready for encrypted messaging',
      completed: false,
    },
  ];

  const [setupSteps, setSetupSteps] = useState(steps);

  useEffect(() => {
    // Auto-detect device information
    const detectedName = getDeviceName();
    const detectedType = getDeviceType();
    
    if (detectedName) setDeviceName(detectedName);
    setDeviceType(detectedType);
  }, []);

  const getDeviceName = (): string => {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Android/.test(ua)) return 'Android Device';
    if (/Macintosh/.test(ua)) return 'Mac';
    if (/Windows/.test(ua)) return 'Windows PC';
    if (/Linux/.test(ua)) return 'Linux Device';
    return 'Web Browser';
  };

  const getDeviceType = (): 'mobile' | 'desktop' | 'web' | 'tablet' => {
    const ua = navigator.userAgent;
    if (/iPhone|Android.*Mobile/.test(ua)) return 'mobile';
    if (/iPad|Android(?!.*Mobile)/.test(ua)) return 'tablet';
    if (/Macintosh|Windows|Linux/.test(ua)) return 'desktop';
    return 'web';
  };

  const DeviceTypeIcon = ({ type, className = "h-5 w-5" }: { type: string; className?: string }) => {
    switch (type) {
      case 'mobile':
        return <DevicePhoneMobileIcon className={className} />;
      case 'desktop':
        return <ComputerDesktopIcon className={className} />;
      case 'tablet':
        return <DeviceTabletIcon className={className} />;
      default:
        return <GlobeAltIcon className={className} />;
    }
  };

  const updateStepStatus = (stepId: string, completed: boolean) => {
    setSetupSteps(prev => prev.map(step => 
      step.id === stepId ? { ...step, completed } : step
    ));
  };

  const handleNext = async () => {
    setError(null);
    
    switch (currentStep) {
      case 0:
        await handleDeviceInfoSubmit();
        break;
      case 1:
        await handleDeviceRegistration();
        break;
      case 2:
        await handleDeviceVerification();
        break;
      case 3:
        onSetupComplete();
        break;
    }
  };

  const handleDeviceInfoSubmit = async () => {
    if (!deviceName.trim()) {
      setError('Device name is required');
      return;
    }

    updateStepStatus('device-info', true);
    setCurrentStep(1);
  };

  const handleDeviceRegistration = async () => {
    try {
      setLoading(true);
      
      const result = await multiDeviceE2EEService.registerDevice();
      setRegistrationResult(result);
      
      if (result.verification) {
        setVerificationChallenge(result.verification);
      }
      
      updateStepStatus('registration', true);
      setCurrentStep(2);
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Registration failed';
      setError(errorMessage);
      onSetupError?.(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleDeviceVerification = async () => {
    if (!verificationChallenge) {
      setError('No verification challenge found');
      return;
    }

    try {
      setLoading(true);
      
      let verificationResponse;
      
      if (verificationChallenge.verificationType === 'security_key') {
        // Generate a signature using the device's private key
        verificationResponse = {
          type: 'security_key',
          signature: await generateSignature(verificationChallenge.nonce),
        };
      } else if (verificationChallenge.verificationType === 'verification_code') {
        if (!verificationCode.trim()) {
          setError('Verification code is required');
          return;
        }
        verificationResponse = {
          type: 'verification_code',
          code: verificationCode,
        };
      } else {
        setError('Unsupported verification method');
        return;
      }

      const verified = await multiDeviceE2EEService.verifyDevice(
        verificationChallenge.challengeId,
        verificationResponse
      );

      if (verified) {
        updateStepStatus('verification', true);
        updateStepStatus('complete', true);
        setCurrentStep(3);
      } else {
        setError('Device verification failed');
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Verification failed';
      setError(errorMessage);
      onSetupError?.(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const generateSignature = async (nonce: string): Promise<string> => {
    // This would use the device's private key to sign the nonce
    // For demo purposes, we'll return a mock signature
    return btoa(`signature_for_${nonce}_${Date.now()}`);
  };

  const handleBack = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
      setError(null);
    }
  };

  const canProceed = () => {
    switch (currentStep) {
      case 0:
        return deviceName.trim() !== '';
      case 1:
        return !loading;
      case 2:
        if (verificationChallenge?.verificationType === 'verification_code') {
          return verificationCode.trim() !== '' && !loading;
        }
        return !loading;
      case 3:
        return true;
      default:
        return false;
    }
  };

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Progress Steps */}
      <Card>
        <CardHeader>
          <CardTitle>Device Setup</CardTitle>
          <CardDescription>
            Set up this device for end-to-end encrypted messaging
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Progress value={(currentStep / (setupSteps.length - 1)) * 100} className="h-2" />
            
            <div className="flex justify-between">
              {setupSteps.map((step, index) => (
                <div
                  key={step.id}
                  className={`flex flex-col items-center space-y-2 ${
                    index <= currentStep ? 'text-blue-600' : 'text-gray-400'
                  }`}
                >
                  <div
                    className={`w-8 h-8 rounded-full border-2 flex items-center justify-center text-sm font-medium ${
                      step.completed
                        ? 'bg-green-100 border-green-500 text-green-600'
                        : index === currentStep
                        ? 'bg-blue-100 border-blue-500 text-blue-600'
                        : 'border-gray-300 text-gray-400'
                    }`}
                  >
                    {step.completed ? (
                      <CheckCircleIcon className="h-4 w-4" />
                    ) : (
                      index + 1
                    )}
                  </div>
                  <div className="text-center">
                    <p className="text-xs font-medium">{step.title}</p>
                    <p className="text-xs text-gray-500">{step.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Error Alert */}
      {error && (
        <Alert variant="destructive">
          <ExclamationTriangleIcon className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Step Content */}
      <Card>
        <CardContent className="pt-6">
          {/* Step 1: Device Information */}
          {currentStep === 0 && (
            <div className="space-y-4">
              <div className="text-center">
                <DeviceTypeIcon type={deviceType} className="h-12 w-12 mx-auto mb-4 text-gray-600" />
                <h3 className="text-lg font-semibold mb-2">Device Information</h3>
                <p className="text-gray-600 mb-6">
                  We've detected some information about your device. Please review and adjust if needed.
                </p>
              </div>

              <div className="grid gap-4">
                <div>
                  <Label htmlFor="device-name">Device Name</Label>
                  <Input
                    id="device-name"
                    value={deviceName}
                    onChange={(e) => setDeviceName(e.target.value)}
                    placeholder="Enter device name"
                    className="mt-1"
                  />
                </div>

                <div>
                  <Label htmlFor="device-type">Device Type</Label>
                  <Select value={deviceType} onValueChange={(value: any) => setDeviceType(value)}>
                    <SelectTrigger className="mt-1">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="mobile">Mobile</SelectItem>
                      <SelectItem value="desktop">Desktop</SelectItem>
                      <SelectItem value="tablet">Tablet</SelectItem>
                      <SelectItem value="web">Web Browser</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="bg-gray-50 p-4 rounded-lg">
                  <h4 className="font-medium mb-2">Detected Information</h4>
                  <div className="text-sm text-gray-600 space-y-1">
                    <p><strong>Platform:</strong> {navigator.platform}</p>
                    <p><strong>User Agent:</strong> {navigator.userAgent.substring(0, 80)}...</p>
                    <p><strong>Language:</strong> {navigator.language}</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Step 2: Device Registration */}
          {currentStep === 1 && (
            <div className="space-y-4">
              <div className="text-center">
                <KeyIcon className="h-12 w-12 mx-auto mb-4 text-blue-600" />
                <h3 className="text-lg font-semibold mb-2">Device Registration</h3>
                <p className="text-gray-600 mb-6">
                  We'll generate encryption keys for this device and register it with our secure servers.
                </p>
              </div>

              {loading ? (
                <div className="text-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                  <p className="text-gray-600 mt-4">Registering device...</p>
                </div>
              ) : registrationResult ? (
                <div className="bg-green-50 p-4 rounded-lg">
                  <div className="flex items-center mb-2">
                    <CheckCircleIcon className="h-5 w-5 text-green-600 mr-2" />
                    <h4 className="font-medium text-green-800">Registration Successful</h4>
                  </div>
                  <div className="text-sm text-green-700 space-y-1">
                    <p><strong>Device ID:</strong> {registrationResult.device.id}</p>
                    <p><strong>Security Level:</strong> 
                      <Badge className="ml-2" variant="outline">
                        {registrationResult.device.security_level.toUpperCase()}
                      </Badge>
                    </p>
                    <p><strong>Security Score:</strong> {registrationResult.device.security_score}/100</p>
                  </div>
                </div>
              ) : (
                <div className="text-center py-4">
                  <p className="text-gray-600">Ready to register this device</p>
                </div>
              )}
            </div>
          )}

          {/* Step 3: Device Verification */}
          {currentStep === 2 && (
            <div className="space-y-4">
              <div className="text-center">
                <ShieldCheckIcon className="h-12 w-12 mx-auto mb-4 text-green-600" />
                <h3 className="text-lg font-semibold mb-2">Device Verification</h3>
                <p className="text-gray-600 mb-6">
                  Complete the verification process to enable end-to-end encryption.
                </p>
              </div>

              {verificationChallenge && (
                <div className="space-y-4">
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <h4 className="font-medium mb-2">Verification Required</h4>
                    <div className="text-sm text-blue-700 space-y-1">
                      <p><strong>Method:</strong> {(verificationChallenge.verificationType || verificationChallenge.verification_type || 'unknown').replace('_', ' ')}</p>
                      <p><strong>Expires:</strong> 
                        <span className="flex items-center ml-1">
                          <ClockIcon className="h-4 w-4 mr-1" />
                          {new Date(verificationChallenge.expiresAt).toLocaleTimeString()}
                        </span>
                      </p>
                    </div>
                  </div>

                  {verificationChallenge.verificationType === 'verification_code' && (
                    <div>
                      <Label htmlFor="verification-code">Verification Code</Label>
                      <Input
                        id="verification-code"
                        value={verificationCode}
                        onChange={(e) => setVerificationCode(e.target.value)}
                        placeholder="Enter verification code"
                        className="mt-1"
                      />
                      <p className="text-sm text-gray-600 mt-1">
                        Check your email or SMS for the verification code
                      </p>
                    </div>
                  )}

                  {verificationChallenge.verificationType === 'security_key' && (
                    <Alert>
                      <KeyIcon className="h-4 w-4" />
                      <AlertDescription>
                        Your device will automatically verify using its security key when you click Continue.
                      </AlertDescription>
                    </Alert>
                  )}
                </div>
              )}

              {loading && (
                <div className="text-center py-4">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto"></div>
                  <p className="text-gray-600 mt-2">Verifying device...</p>
                </div>
              )}
            </div>
          )}

          {/* Step 4: Complete */}
          {currentStep === 3 && (
            <div className="space-y-4">
              <div className="text-center">
                <CheckCircleIcon className="h-16 w-16 mx-auto mb-4 text-green-600" />
                <h3 className="text-xl font-semibold mb-2 text-green-800">Setup Complete!</h3>
                <p className="text-gray-600 mb-6">
                  Your device is now registered and verified for end-to-end encrypted messaging.
                </p>
              </div>

              <div className="bg-green-50 p-6 rounded-lg">
                <h4 className="font-medium text-green-800 mb-3">What's Next?</h4>
                <ul className="space-y-2 text-sm text-green-700">
                  <li className="flex items-center">
                    <CheckCircleIcon className="h-4 w-4 mr-2" />
                    Your device can now send and receive encrypted messages
                  </li>
                  <li className="flex items-center">
                    <CheckCircleIcon className="h-4 w-4 mr-2" />
                    Key sharing with other devices is automatically handled
                  </li>
                  <li className="flex items-center">
                    <CheckCircleIcon className="h-4 w-4 mr-2" />
                    All conversations are protected with end-to-end encryption
                  </li>
                </ul>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Action Buttons */}
      <div className="flex justify-between">
        <Button
          variant="outline"
          onClick={handleBack}
          disabled={currentStep === 0 || loading}
        >
          Back
        </Button>
        
        <Button
          onClick={handleNext}
          disabled={!canProceed() || loading}
        >
          {loading ? (
            <>
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              Processing...
            </>
          ) : currentStep === 3 ? (
            'Get Started'
          ) : (
            'Continue'
          )}
        </Button>
      </div>
    </div>
  );
}