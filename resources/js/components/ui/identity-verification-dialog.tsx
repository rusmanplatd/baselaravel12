/**
 * Identity Verification Dialog Component
 * Allows users to verify each other's identities using fingerprint comparison
 * This is similar to Signal's safety number verification
 */

import React, { useState, useCallback, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
  Shield,
  Eye,
  Copy,
  Check,
  AlertTriangle,
  QrCode,
  Fingerprint,
  Users,
  Lock
} from 'lucide-react';
import { toast } from 'sonner';
import type { SessionInfo } from '@/services/SignalSessionManager';

interface IdentityVerificationDialogProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  sessionInfo: SessionInfo;
  remoteUserName: string;
  localFingerprint: string;
  remoteFingerprint: string;
  onVerifyIdentity: (fingerprint: string, method: string) => Promise<boolean>;
  onGenerateQRCode?: () => Promise<{ qrCode: string; url: string }>;
}

const VerificationMethod = {
  FINGERPRINT: 'fingerprint',
  QR_CODE: 'qr_code',
  SAFETY_NUMBERS: 'safety_numbers'
} as const;

type VerificationMethodType = typeof VerificationMethod[keyof typeof VerificationMethod];

export function IdentityVerificationDialog({
  isOpen,
  onOpenChange,
  sessionInfo,
  remoteUserName,
  localFingerprint,
  remoteFingerprint,
  onVerifyIdentity,
  onGenerateQRCode,
}: IdentityVerificationDialogProps) {
  const [activeMethod, setActiveMethod] = useState<VerificationMethodType>(VerificationMethod.FINGERPRINT);
  const [verificationStep, setVerificationStep] = useState<'compare' | 'confirm' | 'result'>('compare');
  const [userConfirmedMatch, setUserConfirmedMatch] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [verificationResult, setVerificationResult] = useState<{
    success: boolean;
    message: string;
  } | null>(null);
  const [qrCodeData, setQrCodeData] = useState<{ qrCode: string; url: string } | null>(null);
  const [providedFingerprint, setProvidedFingerprint] = useState('');

  // Format fingerprint for display (groups of 5 characters)
  const formatFingerprint = (fingerprint: string) => {
    return fingerprint.match(/.{1,5}/g)?.join(' ') || fingerprint;
  };

  // Copy fingerprint to clipboard
  const copyFingerprint = useCallback(async (fingerprint: string, label: string) => {
    try {
      await navigator.clipboard.writeText(fingerprint);
      toast.success(`${label} fingerprint copied to clipboard`);
    } catch (error) {
      toast.error('Failed to copy fingerprint');
    }
  }, []);

  // Generate QR code for verification
  const generateQRCode = useCallback(async () => {
    if (!onGenerateQRCode) return;
    
    try {
      const qrData = await onGenerateQRCode();
      setQrCodeData(qrData);
    } catch (error) {
      toast.error('Failed to generate QR code');
    }
  }, [onGenerateQRCode]);

  // Perform verification
  const performVerification = useCallback(async () => {
    setIsVerifying(true);
    
    try {
      let fingerprintToVerify = remoteFingerprint;
      
      if (activeMethod === VerificationMethod.SAFETY_NUMBERS && providedFingerprint) {
        fingerprintToVerify = providedFingerprint.replace(/\s/g, '');
      }

      const success = await onVerifyIdentity(fingerprintToVerify, activeMethod);
      
      setVerificationResult({
        success,
        message: success 
          ? `${remoteUserName}'s identity has been verified successfully!`
          : 'Identity verification failed. The fingerprints do not match.'
      });
      
      setVerificationStep('result');
    } catch (error) {
      setVerificationResult({
        success: false,
        message: 'Verification failed due to an error. Please try again.'
      });
      setVerificationStep('result');
    } finally {
      setIsVerifying(false);
    }
  }, [activeMethod, providedFingerprint, remoteFingerprint, onVerifyIdentity, remoteUserName]);

  // Reset state when dialog opens
  useEffect(() => {
    if (isOpen) {
      setVerificationStep('compare');
      setUserConfirmedMatch(false);
      setIsVerifying(false);
      setVerificationResult(null);
      setQrCodeData(null);
      setProvidedFingerprint('');
    }
  }, [isOpen]);

  // Load QR code when QR method is selected
  useEffect(() => {
    if (activeMethod === VerificationMethod.QR_CODE && !qrCodeData) {
      generateQRCode();
    }
  }, [activeMethod, qrCodeData, generateQRCode]);

  const renderFingerprintComparison = () => (
    <div className="space-y-4">
      <Alert>
        <Fingerprint className="h-4 w-4" />
        <AlertDescription>
          Compare these fingerprints with {remoteUserName} through a secure channel 
          (in person, phone call, etc.) to verify their identity.
        </AlertDescription>
      </Alert>

      {/* Local Fingerprint */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">Your Fingerprint</Label>
        <div className="p-3 bg-blue-50 rounded-lg border border-blue-200">
          <div className="flex items-center justify-between">
            <code className="text-sm font-mono text-blue-800 break-all">
              {formatFingerprint(localFingerprint)}
            </code>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => copyFingerprint(localFingerprint, 'Your')}
              className="ml-2 h-8 w-8 p-0"
            >
              <Copy className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      {/* Remote Fingerprint */}
      <div className="space-y-2">
        <Label className="text-sm font-medium">{remoteUserName}'s Fingerprint</Label>
        <div className="p-3 bg-green-50 rounded-lg border border-green-200">
          <div className="flex items-center justify-between">
            <code className="text-sm font-mono text-green-800 break-all">
              {formatFingerprint(remoteFingerprint)}
            </code>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => copyFingerprint(remoteFingerprint, `${remoteUserName}'s`)}
              className="ml-2 h-8 w-8 p-0"
            >
              <Copy className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <Alert>
        <Shield className="h-4 w-4" />
        <AlertDescription>
          <strong>Security Note:</strong> Only mark as verified if the fingerprints 
          match exactly and you've confirmed this through a secure channel.
        </AlertDescription>
      </Alert>
    </div>
  );

  const renderQRCodeVerification = () => (
    <div className="space-y-4">
      <Alert>
        <QrCode className="h-4 w-4" />
        <AlertDescription>
          Scan this QR code with {remoteUserName} to verify each other's identities.
        </AlertDescription>
      </Alert>

      {qrCodeData ? (
        <div className="text-center space-y-4">
          <div className="inline-block p-4 bg-white border-2 border-gray-300 rounded-lg">
            <img 
              src={qrCodeData.qrCode} 
              alt="Identity Verification QR Code"
              className="w-48 h-48"
            />
          </div>
          <p className="text-sm text-gray-600">
            Share this QR code with {remoteUserName} for verification
          </p>
          <Button
            variant="outline"
            onClick={() => copyFingerprint(qrCodeData.url, 'Verification URL')}
            className="w-full"
          >
            <Copy className="h-4 w-4 mr-2" />
            Copy Verification Link
          </Button>
        </div>
      ) : (
        <div className="text-center py-8">
          <QrCode className="h-12 w-12 mx-auto text-gray-400 mb-4" />
          <p className="text-gray-500">Generating QR code...</p>
        </div>
      )}
    </div>
  );

  const renderSafetyNumberInput = () => (
    <div className="space-y-4">
      <Alert>
        <Users className="h-4 w-4" />
        <AlertDescription>
          Enter the safety numbers provided by {remoteUserName} to verify their identity.
        </AlertDescription>
      </Alert>

      <div className="space-y-2">
        <Label htmlFor="safety-numbers">Safety Numbers from {remoteUserName}</Label>
        <Textarea
          id="safety-numbers"
          placeholder="Enter the safety numbers here..."
          value={providedFingerprint}
          onChange={(e) => setProvidedFingerprint(e.target.value)}
          className="min-h-[80px] font-mono text-sm"
        />
      </div>

      <div className="space-y-2">
        <Label className="text-sm font-medium">Your Safety Numbers</Label>
        <div className="p-3 bg-blue-50 rounded-lg border border-blue-200">
          <div className="flex items-center justify-between">
            <code className="text-sm font-mono text-blue-800 break-all">
              {formatFingerprint(localFingerprint)}
            </code>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => copyFingerprint(localFingerprint, 'Your safety numbers')}
              className="ml-2 h-8 w-8 p-0"
            >
              <Copy className="h-4 w-4" />
            </Button>
          </div>
        </div>
        <p className="text-xs text-gray-500">Share these numbers with {remoteUserName}</p>
      </div>
    </div>
  );

  const renderVerificationResult = () => (
    <div className="text-center space-y-4">
      <div className={`mx-auto w-16 h-16 rounded-full flex items-center justify-center ${
        verificationResult?.success 
          ? 'bg-green-100 text-green-600' 
          : 'bg-red-100 text-red-600'
      }`}>
        {verificationResult?.success ? (
          <Check className="h-8 w-8" />
        ) : (
          <AlertTriangle className="h-8 w-8" />
        )}
      </div>
      
      <div className="space-y-2">
        <h3 className="text-lg font-semibold">
          {verificationResult?.success ? 'Verification Successful' : 'Verification Failed'}
        </h3>
        <p className="text-gray-600 text-sm">
          {verificationResult?.message}
        </p>
      </div>

      {verificationResult?.success && (
        <Badge className="bg-green-100 text-green-800 border-green-200">
          <Shield className="h-3 w-3 mr-1" />
          Identity Verified
        </Badge>
      )}
    </div>
  );

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <Eye className="h-5 w-5" />
            <span>Verify Identity</span>
          </DialogTitle>
          <DialogDescription>
            Verify {remoteUserName}'s identity to ensure secure communication
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Verification Method Tabs */}
          {verificationStep === 'compare' && (
            <div className="flex space-x-1 p-1 bg-gray-100 rounded-lg">
              <button
                onClick={() => setActiveMethod(VerificationMethod.FINGERPRINT)}
                className={`flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                  activeMethod === VerificationMethod.FINGERPRINT
                    ? 'bg-white text-gray-900 shadow-sm'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                <Fingerprint className="h-4 w-4 mx-auto mb-1" />
                Fingerprint
              </button>
              {onGenerateQRCode && (
                <button
                  onClick={() => setActiveMethod(VerificationMethod.QR_CODE)}
                  className={`flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                    activeMethod === VerificationMethod.QR_CODE
                      ? 'bg-white text-gray-900 shadow-sm'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  <QrCode className="h-4 w-4 mx-auto mb-1" />
                  QR Code
                </button>
              )}
              <button
                onClick={() => setActiveMethod(VerificationMethod.SAFETY_NUMBERS)}
                className={`flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                  activeMethod === VerificationMethod.SAFETY_NUMBERS
                    ? 'bg-white text-gray-900 shadow-sm'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                <Users className="h-4 w-4 mx-auto mb-1" />
                Numbers
              </button>
            </div>
          )}

          {/* Verification Content */}
          {verificationStep === 'compare' && (
            <div>
              {activeMethod === VerificationMethod.FINGERPRINT && renderFingerprintComparison()}
              {activeMethod === VerificationMethod.QR_CODE && renderQRCodeVerification()}
              {activeMethod === VerificationMethod.SAFETY_NUMBERS && renderSafetyNumberInput()}
            </div>
          )}

          {verificationStep === 'result' && renderVerificationResult()}
        </div>

        <DialogFooter className="flex flex-col sm:flex-row gap-2">
          {verificationStep === 'compare' && (
            <>
              <Button variant="outline" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button
                onClick={performVerification}
                disabled={
                  isVerifying || 
                  (activeMethod === VerificationMethod.SAFETY_NUMBERS && !providedFingerprint.trim())
                }
                className="bg-green-600 hover:bg-green-700"
              >
                {isVerifying ? (
                  <>
                    <Lock className="h-4 w-4 mr-2 animate-spin" />
                    Verifying...
                  </>
                ) : (
                  <>
                    <Shield className="h-4 w-4 mr-2" />
                    Verify Identity
                  </>
                )}
              </Button>
            </>
          )}

          {verificationStep === 'result' && (
            <Button onClick={() => onOpenChange(false)} className="w-full">
              Done
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default IdentityVerificationDialog;