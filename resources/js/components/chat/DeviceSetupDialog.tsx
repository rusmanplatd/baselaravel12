import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { CheckIcon, DevicePhoneMobileIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';

interface DeviceSetupDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete: () => void;
}

type SetupStep = 'device-detection' | 'encryption-setup' | 'complete';

export default function DeviceSetupDialog({ 
  isOpen, 
  onClose, 
  onComplete 
}: DeviceSetupDialogProps) {
  const [currentStep, setCurrentStep] = useState<SetupStep>('device-detection');
  const [progress, setProgress] = useState(0);

  const handleNext = () => {
    switch (currentStep) {
      case 'device-detection':
        setCurrentStep('encryption-setup');
        setProgress(50);
        break;
      case 'encryption-setup':
        setCurrentStep('complete');
        setProgress(100);
        break;
      case 'complete':
        onComplete();
        onClose();
        break;
    }
  };

  const renderStep = () => {
    switch (currentStep) {
      case 'device-detection':
        return (
          <div data-testid="device-detection-step" className="text-center py-6">
            <DevicePhoneMobileIcon className="h-16 w-16 text-blue-500 mx-auto mb-4" />
            <h3 className="text-lg font-semibold mb-2">Device Detection</h3>
            <p className="text-gray-600 mb-6">
              We're detecting your device capabilities and setting up secure communication.
            </p>
            <div className="bg-gray-50 p-4 rounded-lg mb-6">
              <div className="flex items-center justify-between text-sm">
                <span>Device Type:</span>
                <span className="font-medium">Desktop</span>
              </div>
              <div className="flex items-center justify-between text-sm mt-2">
                <span>Security Level:</span>
                <span className="font-medium text-green-600">High</span>
              </div>
            </div>
          </div>
        );

      case 'encryption-setup':
        return (
          <div data-testid="encryption-setup-step" className="text-center py-6">
            <ShieldCheckIcon className="h-16 w-16 text-green-500 mx-auto mb-4" />
            <h3 className="text-lg font-semibold mb-2">Encryption Setup</h3>
            <p className="text-gray-600 mb-6">
              Generating your unique encryption keys and configuring end-to-end encryption.
            </p>
            <div className="bg-blue-50 p-4 rounded-lg mb-6">
              <div className="text-sm text-blue-700">
                🔐 Your messages will be encrypted with AES-256 encryption<br />
                🔑 Only you and your recipients can read your messages<br />
                🚫 Not even we can access your conversations
              </div>
            </div>
          </div>
        );

      case 'complete':
        return (
          <div data-testid="setup-complete" className="text-center py-6">
            <div className="h-16 w-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <CheckIcon className="h-8 w-8 text-green-600" />
            </div>
            <h3 className="text-lg font-semibold mb-2">Setup Complete!</h3>
            <p className="text-gray-600 mb-6">
              Your device is now secured with end-to-end encryption. You can start chatting securely.
            </p>
            <div className="bg-green-50 p-4 rounded-lg">
              <div className="text-sm text-green-700">
                ✅ Device encryption enabled<br />
                ✅ Secure key generation complete<br />
                ✅ Ready for encrypted messaging
              </div>
            </div>
          </div>
        );
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent 
        className="sm:max-w-md"
        data-testid="device-setup-dialog"
      >
        <DialogHeader>
          <DialogTitle>Device Setup</DialogTitle>
        </DialogHeader>
        
        <div className="space-y-4">
          <Progress value={progress} className="w-full" />
          
          {renderStep()}
          
          <div className="flex justify-end space-x-2 pt-4">
            <Button
              onClick={handleNext}
              className="px-6"
            >
              {currentStep === 'complete' ? 'Start Chatting' : 'Continue'}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}