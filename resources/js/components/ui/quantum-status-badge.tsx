import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Shield, ShieldAlert, ShieldCheck, ShieldX, Zap } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';

interface QuantumStatusBadgeProps {
  algorithm?: string;
  quantumResistant?: boolean;
  encryptionVersion?: number;
  size?: 'sm' | 'md' | 'lg';
  showText?: boolean;
  className?: string;
}

const algorithmInfo = {
  'ML-KEM-512': { name: 'ML-KEM-512', level: 'Basic', color: 'bg-blue-500' },
  'ML-KEM-768': { name: 'ML-KEM-768', level: 'Standard', color: 'bg-green-500' },
  'ML-KEM-1024': { name: 'ML-KEM-1024', level: 'High', color: 'bg-purple-500' },
  'HYBRID-RSA4096-MLKEM768': { name: 'Hybrid', level: 'Transition', color: 'bg-amber-500' },
  'RSA-4096-OAEP': { name: 'RSA-4096', level: 'Legacy', color: 'bg-gray-500' },
};

export function QuantumStatusBadge({
  algorithm = 'RSA-4096-OAEP',
  quantumResistant = false,
  encryptionVersion = 2,
  size = 'md',
  showText = true,
  className
}: QuantumStatusBadgeProps) {
  const info = algorithmInfo[algorithm as keyof typeof algorithmInfo];
  
  const getIcon = () => {
    if (quantumResistant) {
      if (algorithm.startsWith('ML-KEM')) {
        return <Zap className="h-3 w-3" />;
      }
      return <ShieldCheck className="h-3 w-3" />;
    }
    
    if (encryptionVersion < 3) {
      return <ShieldAlert className="h-3 w-3" />;
    }
    
    return <Shield className="h-3 w-3" />;
  };

  const getVariant = (): "default" | "secondary" | "destructive" | "outline" => {
    if (quantumResistant) {
      return algorithm.startsWith('ML-KEM') ? 'default' : 'secondary';
    }
    return 'outline';
  };

  const getBadgeText = () => {
    if (!showText) return null;
    
    if (quantumResistant) {
      return algorithm.startsWith('ML-KEM') ? 'Quantum-Safe' : 'Hybrid';
    }
    
    return 'Classical';
  };

  const getTooltipContent = () => (
    <div className="space-y-1">
      <div className="font-medium">{info?.name || algorithm}</div>
      <div className="text-xs text-muted-foreground">
        Security Level: {info?.level || 'Unknown'}
      </div>
      <div className="text-xs text-muted-foreground">
        Version: {encryptionVersion}
      </div>
      <div className="text-xs">
        {quantumResistant 
          ? '🛡️ Protected against quantum attacks'
          : '⚠️ Vulnerable to future quantum computers'
        }
      </div>
    </div>
  );

  const sizeClasses = {
    sm: 'h-5 text-xs',
    md: 'h-6 text-sm',
    lg: 'h-7 text-base'
  };

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <Badge
            variant={getVariant()}
            className={cn(
              'inline-flex items-center gap-1 font-medium',
              sizeClasses[size],
              quantumResistant && algorithm.startsWith('ML-KEM') && 'bg-gradient-to-r from-blue-600 to-purple-600 text-white border-0',
              quantumResistant && algorithm.includes('HYBRID') && 'bg-gradient-to-r from-amber-500 to-orange-500 text-white border-0',
              className
            )}
          >
            {getIcon()}
            {getBadgeText()}
          </Badge>
        </TooltipTrigger>
        <TooltipContent side="bottom">
          {getTooltipContent()}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}