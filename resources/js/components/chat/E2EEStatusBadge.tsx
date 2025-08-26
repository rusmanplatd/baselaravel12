import React from 'react';
import { ShieldCheckIcon, ShieldExclamationIcon, XMarkIcon } from '@heroicons/react/24/solid';

interface E2EEStatusBadgeProps {
  status: 'enabled' | 'disabled' | 'error';
  className?: string;
  onClick?: () => void;
}

export default function E2EEStatusBadge({ 
  status, 
  className = '', 
  onClick 
}: E2EEStatusBadgeProps) {
  const getStatusConfig = () => {
    switch (status) {
      case 'enabled':
        return {
          bgColor: 'bg-green-100 hover:bg-green-200',
          textColor: 'text-green-800',
          icon: <ShieldCheckIcon className="h-4 w-4" />,
          text: 'End-to-End Encrypted',
        };
      case 'disabled':
        return {
          bgColor: 'bg-yellow-100 hover:bg-yellow-200',
          textColor: 'text-yellow-800',
          icon: <ShieldExclamationIcon className="h-4 w-4" />,
          text: 'Encryption Disabled',
        };
      case 'error':
        return {
          bgColor: 'bg-red-100 hover:bg-red-200',
          textColor: 'text-red-800',
          icon: <XMarkIcon className="h-4 w-4" />,
          text: 'Encryption Error',
        };
    }
  };

  const config = getStatusConfig();

  return (
    <div 
      className={`
        inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
        ${config.bgColor} ${config.textColor}
        ${onClick ? 'cursor-pointer transition-colors' : ''}
        ${className}
      `}
      data-testid="e2ee-status-badge"
      onClick={onClick}
    >
      {config.icon}
      <span className="ml-1">{config.text}</span>
    </div>
  );
}