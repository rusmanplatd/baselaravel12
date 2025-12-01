/**
 * Signal Message Composer Component
 * Enhanced message composer with Signal Protocol features
 */

import React, { useState, useCallback, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
  Send,
  Shield,
  Clock,
  AlertTriangle,
  Lock,
  Zap,
  Settings,
  Eye,
  Timer,
  Paperclip,
  Smile,
  Mic
} from 'lucide-react';
import { toast } from 'sonner';
import type { MessageDeliveryOptions } from '@/services/SignalSessionManager';

interface SignalMessageComposerProps {
  conversationId: string;
  recipientUserId?: string;
  isSignalEnabled: boolean;
  sessionVerified: boolean;
  onSendMessage: (content: string, options: MessageDeliveryOptions & { useSignal: boolean }) => Promise<void>;
  onStartTyping?: () => void;
  onStopTyping?: () => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
}

export function SignalMessageComposer({
  conversationId,
  recipientUserId,
  isSignalEnabled,
  sessionVerified,
  onSendMessage,
  onStartTyping,
  onStopTyping,
  placeholder = "Type a message...52",
  disabled = false,
  className = ''
}: SignalMessageComposerProps) {
  const [message, setMessage] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [messageOptions, setMessageOptions] = useState<MessageDeliveryOptions & { useSignal: boolean }>({
    priority: 'normal',
    requiresReceipt: false,
    forwardSecrecy: true,
    useSignal: isSignalEnabled,
    expirationTime: undefined,
  });

  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Auto-resize textarea
  const adjustTextareaHeight = useCallback(() => {
    const textarea = textareaRef.current;
    if (textarea) {
      textarea.style.height = 'auto';
      textarea.style.height = `${Math.min(textarea.scrollHeight, 120)}px`;
    }
  }, []);

  // Handle message input
  const handleMessageChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setMessage(e.target.value);
    adjustTextareaHeight();

    // Handle typing indicators
    if (onStartTyping) {
      onStartTyping();
    }

    // Clear previous timeout and set new one
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }

    typingTimeoutRef.current = setTimeout(() => {
      if (onStopTyping) {
        onStopTyping();
      }
    }, 1000);
  }, [adjustTextareaHeight, onStartTyping, onStopTyping]);

  // Send message
  const handleSendMessage = useCallback(async () => {
    if (!message.trim() || isSending || disabled) return;

    setIsSending(true);
    try {
      await onSendMessage(message.trim(), messageOptions);
      setMessage('');
      adjustTextareaHeight();

      // Stop typing indicator
      if (onStopTyping) {
        onStopTyping();
      }
    } catch (error) {
      toast.error('Failed to send message');
    } finally {
      setIsSending(false);
    }
  }, [message, messageOptions, isSending, disabled, onSendMessage, adjustTextareaHeight, onStopTyping]);

  // Handle Enter key
  const handleKeyDown = useCallback((e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage();
    }
  }, [handleSendMessage]);

  // Update Signal usage based on availability
  useEffect(() => {
    setMessageOptions(prev => ({
      ...prev,
      useSignal: isSignalEnabled,
      forwardSecrecy: isSignalEnabled ? prev.forwardSecrecy : false
    }));
  }, [isSignalEnabled]);

  // Cleanup typing timeout
  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);

  const getEncryptionBadge = () => {
    if (messageOptions.useSignal && isSignalEnabled) {
      return (
        <Badge className="bg-green-100 text-green-800 border-green-200">
          <Shield className="h-3 w-3 mr-1" />
          Signal E2EE
          {sessionVerified && <Eye className="h-3 w-3 ml-1" />}
        </Badge>
      );
    } else if (!isSignalEnabled) {
      return (
        <Badge className="bg-blue-100 text-blue-800 border-blue-200">
          <Lock className="h-3 w-3 mr-1" />
          Standard E2EE
        </Badge>
      );
    }
    return null;
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent': return 'text-red-600';
      case 'high': return 'text-orange-600';
      case 'normal': return 'text-blue-600';
      case 'low': return 'text-gray-600';
      default: return 'text-blue-600';
    }
  };

  return (
    <div className={`bg-white border-t border-gray-200 ${className}`}>
      {/* Encryption Status Bar */}
      <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
        <div className="flex items-center justify-between text-xs">
          <div className="flex items-center space-x-2">
            {getEncryptionBadge()}
            {!sessionVerified && isSignalEnabled && (
              <Badge variant="outline" className="text-yellow-600 border-yellow-300">
                <AlertTriangle className="h-3 w-3 mr-1" />
                Unverified
              </Badge>
            )}
            {messageOptions.forwardSecrecy && (
              <Badge variant="outline" className="text-purple-600 border-purple-300">
                <Zap className="h-3 w-3 mr-1" />
                Forward Secrecy
              </Badge>
            )}
          </div>
          <div className="flex items-center space-x-1 text-gray-500">
            {messageOptions.priority !== 'normal' && (
              <span className={`font-medium ${getPriorityColor(messageOptions.priority)}`}>
                {messageOptions.priority.toUpperCase()}
              </span>
            )}
            {messageOptions.expirationTime && (
              <div className="flex items-center">
                <Timer className="h-3 w-3 mr-1" />
                <span>{messageOptions.expirationTime}s</span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Message Input */}
      <div className="p-4">
        <div className="flex items-end space-x-3">
          {/* Attachment Button */}
          <Button
            variant="ghost"
            size="sm"
            className="shrink-0 h-10 w-10 p-0"
            disabled={disabled}
          >
            <Paperclip className="h-4 w-4" />
          </Button>

          {/* Message Input Area */}
          <div className="flex-1 min-w-0">
            <Textarea
              ref={textareaRef}
              value={message}
              onChange={handleMessageChange}
              onKeyDown={handleKeyDown}
              placeholder={placeholder}
              disabled={disabled || isSending}
              className="resize-none min-h-[40px] max-h-[120px] rounded-2xl border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              rows={1}
            />
          </div>

          {/* Emoji Button */}
          <Button
            variant="ghost"
            size="sm"
            className="shrink-0 h-10 w-10 p-0"
            disabled={disabled}
          >
            <Smile className="h-4 w-4" />
          </Button>

          {/* Voice Message Button */}
          <Button
            variant="ghost"
            size="sm"
            className="shrink-0 h-10 w-10 p-0"
            disabled={disabled}
          >
            <Mic className="h-4 w-4" />
          </Button>

          {/* Settings Popover */}
          <Popover open={showSettings} onOpenChange={setShowSettings}>
            <PopoverTrigger asChild>
              <Button
                variant="ghost"
                size="sm"
                className="shrink-0 h-10 w-10 p-0"
                disabled={disabled}
              >
                <Settings className="h-4 w-4" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80" align="end">
              <div className="space-y-4">
                <div className="space-y-2">
                  <h4 className="font-medium">Message Options</h4>
                  <p className="text-sm text-gray-600">Configure delivery and security options</p>
                </div>

                <Separator />

                {/* Encryption Options */}
                <div className="space-y-3">
                  <h5 className="text-sm font-medium">Encryption</h5>

                  {isSignalEnabled && (
                    <div className="flex items-center justify-between">
                      <div>
                        <Label className="text-sm">Use Signal Protocol</Label>
                        <p className="text-xs text-gray-500">Enhanced E2EE with forward secrecy</p>
                      </div>
                      <Switch
                        checked={messageOptions.useSignal}
                        onCheckedChange={(checked) =>
                          setMessageOptions(prev => ({ ...prev, useSignal: checked }))
                        }
                      />
                    </div>
                  )}

                  <div className="flex items-center justify-between">
                    <div>
                      <Label className="text-sm">Forward Secrecy</Label>
                      <p className="text-xs text-gray-500">Perfect forward secrecy for this message</p>
                    </div>
                    <Switch
                      checked={messageOptions.forwardSecrecy}
                      onCheckedChange={(checked) =>
                        setMessageOptions(prev => ({ ...prev, forwardSecrecy: checked }))
                      }
                      disabled={!messageOptions.useSignal}
                    />
                  </div>
                </div>

                <Separator />

                {/* Delivery Options */}
                <div className="space-y-3">
                  <h5 className="text-sm font-medium">Delivery</h5>

                  <div>
                    <Label className="text-sm">Priority</Label>
                    <select
                      value={messageOptions.priority}
                      onChange={(e) =>
                        setMessageOptions(prev => ({
                          ...prev,
                          priority: e.target.value as MessageDeliveryOptions['priority']
                        }))
                      }
                      className="w-full mt-1 p-2 border border-gray-300 rounded-md text-sm"
                    >
                      <option value="low">Low</option>
                      <option value="normal">Normal</option>
                      <option value="high">High</option>
                      <option value="urgent">Urgent</option>
                    </select>
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <Label className="text-sm">Read Receipts</Label>
                      <p className="text-xs text-gray-500">Request delivery confirmation</p>
                    </div>
                    <Switch
                      checked={messageOptions.requiresReceipt}
                      onCheckedChange={(checked) =>
                        setMessageOptions(prev => ({ ...prev, requiresReceipt: checked }))
                      }
                    />
                  </div>

                  <div>
                    <Label className="text-sm">Auto-delete (seconds)</Label>
                    <input
                      type="number"
                      min="0"
                      max="604800"
                      step="1"
                      value={messageOptions.expirationTime || ''}
                      onChange={(e) =>
                        setMessageOptions(prev => ({
                          ...prev,
                          expirationTime: e.target.value ? parseInt(e.target.value) : undefined
                        }))
                      }
                      placeholder="Never"
                      className="w-full mt-1 p-2 border border-gray-300 rounded-md text-sm"
                    />
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>

          {/* Send Button */}
          <Button
            onClick={handleSendMessage}
            disabled={!message.trim() || isSending || disabled}
            className="shrink-0 h-10 w-10 p-0 rounded-full"
          >
            {isSending ? (
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white" />
            ) : (
              <Send className="h-4 w-4" />
            )}
          </Button>
        </div>

        {/* Message Info */}
        {message.trim() && (
          <div className="mt-2 flex items-center justify-between text-xs text-gray-500">
            <div>
              {messageOptions.useSignal && isSignalEnabled ? (
                <span className="text-green-600">Signal Protocol encryption enabled</span>
              ) : (
                <span className="text-blue-600">Standard encryption enabled</span>
              )}
            </div>
            <div>
              {message.length} characters
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default SignalMessageComposer;
