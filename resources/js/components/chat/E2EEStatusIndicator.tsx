import React from 'react';
import { Shield, ShieldCheck, ShieldAlert, ShieldX, Key, AlertTriangle, CheckCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { E2EEStatus } from '@/types/chat';

interface E2EEStatusIndicatorProps {
  status: E2EEStatus;
  size?: 'sm' | 'md' | 'lg';
  showText?: boolean;
  className?: string;
}

export function E2EEStatusIndicator({ 
  status, 
  size = 'md', 
  showText = false, 
  className 
}: E2EEStatusIndicatorProps) {
  const getStatusConfig = () => {
    if (!status.enabled) {
      return {
        icon: ShieldX,
        color: 'text-gray-400',
        bgColor: 'bg-gray-100',
        text: 'E2EE Disabled',
        description: 'End-to-end encryption is not enabled for this conversation'
      };
    }

    if (!status.keyGenerated) {
      return {
        icon: ShieldAlert,
        color: 'text-yellow-600',
        bgColor: 'bg-yellow-50',
        text: 'Keys Generating',
        description: 'Generating encryption keys...'
      };
    }

    if (!status.conversationKeysReady) {
      return {
        icon: Key,
        color: 'text-blue-600',
        bgColor: 'bg-blue-50',
        text: 'Setting up E2EE',
        description: 'Setting up conversation encryption keys...'
      };
    }

    return {
      icon: ShieldCheck,
      color: 'text-green-600',
      bgColor: 'bg-green-50',
      text: 'E2EE Active',
      description: 'Messages are end-to-end encrypted'
    };
  };

  const config = getStatusConfig();
  const Icon = config.icon;

  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-6 w-6'
  };

  const textSizeClasses = {
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-base'
  };

  return (
    <div 
      className={cn(
        'flex items-center gap-2',
        className
      )}
      title={config.description}
    >
      <div className={cn(
        'flex items-center justify-center rounded-full p-1',
        config.bgColor
      )}>
        <Icon className={cn(
          sizeClasses[size],
          config.color
        )} />
      </div>
      
      {showText && (
        <span className={cn(
          'font-medium',
          config.color,
          textSizeClasses[size]
        )}>
          {config.text}
        </span>
      )}
    </div>
  );
}

interface E2EEStatusBadgeProps {
  status: E2EEStatus;
  detailed?: boolean;
  className?: string;
}

export function E2EEStatusBadge({ status, detailed = false, className }: E2EEStatusBadgeProps) {
  const getStatusConfig = () => {
    if (!status.enabled) {
      return {
        icon: ShieldX,
        color: 'text-gray-600',
        bgColor: 'bg-gray-100',
        borderColor: 'border-gray-200',
        text: 'Not Encrypted',
        level: 'none' as const
      };
    }

    if (!status.keyGenerated || !status.conversationKeysReady) {
      return {
        icon: AlertTriangle,
        color: 'text-yellow-700',
        bgColor: 'bg-yellow-100',
        borderColor: 'border-yellow-200',
        text: 'Setting up...',
        level: 'pending' as const
      };
    }

    return {
      icon: CheckCircle,
      color: 'text-green-700',
      bgColor: 'bg-green-100',
      borderColor: 'border-green-200',
      text: 'End-to-End Encrypted',
      level: 'secure' as const
    };
  };

  const config = getStatusConfig();
  const Icon = config.icon;

  return (
    <div className={cn(
      'inline-flex items-center gap-2 px-3 py-1 rounded-full border text-sm font-medium',
      config.bgColor,
      config.borderColor,
      config.color,
      className
    )}>
      <Icon className="h-4 w-4" />
      <span>{config.text}</span>
      
      {detailed && status.version && (
        <span className="text-xs opacity-75">
          v{status.version}
        </span>
      )}
    </div>
  );
}

interface E2EEStatusTooltipProps {
  status: E2EEStatus;
  children: React.ReactNode;
}

export function E2EEStatusTooltip({ status, children }: E2EEStatusTooltipProps) {
  const getDetailedStatus = () => {
    const details = [];
    
    if (status.enabled) {
      details.push(`✓ E2EE Version: ${status.version}`);
      
      if (status.keyGenerated) {
        details.push('✓ Encryption keys generated');
      } else {
        details.push('⏳ Generating encryption keys...');
      }
      
      if (status.conversationKeysReady) {
        details.push('✓ Conversation keys ready');
      } else {
        details.push('⏳ Setting up conversation keys...');
      }
      
      if (status.lastKeyRotation) {
        const rotationDate = new Date(status.lastKeyRotation).toLocaleDateString();
        details.push(`🔄 Last key rotation: ${rotationDate}`);
      }
    } else {
      details.push('❌ End-to-end encryption is disabled');
    }
    
    return details;
  };

  return (
    <div 
      className="group relative"
      title={getDetailedStatus().join('\n')}
    >
      {children}
      <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
        <div className="space-y-1">
          {getDetailedStatus().map((detail, index) => (
            <div key={index}>{detail}</div>
          ))}
        </div>
        <div className="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
      </div>
    </div>
  );
}