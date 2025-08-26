import React from 'react';
import { ShieldExclamationIcon } from '@heroicons/react/24/outline';
import { Button } from '@/components/ui/button';

interface AccessRevokedOverlayProps {
  deviceName?: string;
  onReauthorize?: () => void;
}

export default function AccessRevokedOverlay({ 
  deviceName = 'this device',
  onReauthorize 
}: AccessRevokedOverlayProps) {
  return (
    <div 
      className="absolute inset-0 bg-red-50 bg-opacity-95 backdrop-blur-sm z-50 flex items-center justify-center"
      data-testid="access-revoked"
    >
      <div className="bg-white rounded-lg shadow-lg p-8 max-w-md mx-4 text-center border border-red-200">
        <ShieldExclamationIcon className="h-16 w-16 text-red-500 mx-auto mb-4" />
        
        <h2 className="text-2xl font-bold text-red-900 mb-2">
          Device Access Revoked
        </h2>
        
        <p className="text-red-700 mb-6">
          Access to encrypted conversations has been revoked for {deviceName}. 
          This device can no longer decrypt or send messages.
        </p>
        
        <div className="space-y-3">
          {onReauthorize && (
            <Button 
              onClick={onReauthorize}
              className="w-full bg-red-600 hover:bg-red-700"
            >
              Request Reauthorization
            </Button>
          )}
          
          <Button 
            variant="outline"
            className="w-full"
            onClick={() => window.location.reload()}
          >
            Refresh Page
          </Button>
        </div>
        
        <p className="text-xs text-red-600 mt-4">
          Contact the conversation admin if you believe this is an error.
        </p>
      </div>
    </div>
  );
}