import React from 'react';
import { ShieldExclamationIcon } from '@heroicons/react/24/outline';
import { Button } from '@/components/ui/button';

interface DeviceSetupOverlayProps {
  onStartSetup: () => void;
}

export default function DeviceSetupOverlay({ onStartSetup }: DeviceSetupOverlayProps) {
  return (
    <div 
      className="absolute inset-0 bg-gray-50 bg-opacity-95 backdrop-blur-sm z-50 flex items-center justify-center"
      data-testid="device-setup-required"
    >
      <div className="bg-white rounded-lg shadow-lg p-8 max-w-md mx-4 text-center">
        <ShieldExclamationIcon className="h-16 w-16 text-orange-500 mx-auto mb-4" />
        
        <h2 className="text-2xl font-bold text-gray-900 mb-2">
          Secure Your Messages
        </h2>
        
        <p className="text-gray-600 mb-6">
          End-to-end encryption requires device setup. Your conversations will be secured with military-grade encryption.
        </p>
        
        <Button 
          onClick={onStartSetup}
          className="w-full bg-blue-600 hover:bg-blue-700"
        >
          Setup Device Encryption
        </Button>
        
        <p className="text-xs text-gray-500 mt-4">
          This process takes less than 2 minutes and only needs to be done once per device.
        </p>
      </div>
    </div>
  );
}