import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { PaperAirplaneIcon, MicrophoneIcon, PlusIcon, CalendarIcon } from '@heroicons/react/24/solid';
import { Message, VoiceRecording } from '@/types/chat';
import VoiceRecorder from './VoiceRecorder';

interface MessageInputProps {
  onSendMessage: (content: string, options?: {
    type?: 'text' | 'voice';
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    scheduledAt?: Date;
    voiceData?: VoiceRecording;
    replyToId?: string;
  }) => Promise<void>;
  replyingTo?: Message | null;
  onCancelReply?: () => void;
  onTyping?: (isTyping: boolean) => void;
  encryptionReady?: boolean;
  disabled?: boolean;
}

export default function MessageInput({ 
  onSendMessage, 
  replyingTo, 
  onCancelReply, 
  onTyping,
  encryptionReady = true,
  disabled = false
}: MessageInputProps) {
  const [message, setMessage] = useState('');
  const [sending, setSending] = useState(false);
  const [showVoiceRecorder, setShowVoiceRecorder] = useState(false);
  const [showScheduler, setShowScheduler] = useState(false);
  const [priority, setPriority] = useState<'low' | 'normal' | 'high' | 'urgent'>('normal');
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');
  
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!message.trim() || sending || disabled) return;
    
    const messageToSend = message.trim();
    setMessage('');
    setSending(true);
    
    // Stop typing indicator
    onTyping?.(false);
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }
    
    try {
      const scheduledAt = scheduledDate && scheduledTime 
        ? new Date(`${scheduledDate}T${scheduledTime}`)
        : undefined;
      
      await onSendMessage(messageToSend, {
        type: 'text',
        priority,
        scheduledAt,
        replyToId: replyingTo?.id,
      });

      // Reset states
      setPriority('normal');
      setScheduledDate('');
      setScheduledTime('');
      setShowScheduler(false);
      onCancelReply?.();
    } catch {
      setMessage(messageToSend);
    } finally {
      setSending(false);
    }
  };

  const handleMessageChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setMessage(e.target.value);
    
    // Handle typing indicator
    if (onTyping) {
      onTyping(true);
      
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
      
      typingTimeoutRef.current = setTimeout(() => {
        onTyping(false);
      }, 3000);
    }
    
    // Auto-resize textarea
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.height = `${textareaRef.current.scrollHeight}px`;
    }
  };

  const handleVoiceRecording = async (recording: VoiceRecording) => {
    setSending(true);
    try {
      await onSendMessage('Voice message', {
        type: 'voice',
        voiceData: recording,
        priority,
        replyToId: replyingTo?.id,
      });
      
      setShowVoiceRecorder(false);
      setPriority('normal');
      onCancelReply?.();
    } catch (error) {
      console.error('Failed to send voice message:', error);
    } finally {
      setSending(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  return (
    <div className="border-t border-gray-200 bg-white">
      {/* Reply Preview */}
      {replyingTo && (
        <div className="px-4 py-2 bg-gray-50 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex-1 min-w-0">
              <div className="text-xs text-gray-500 mb-1">
                Replying to {replyingTo.sender?.name}
              </div>
              <div className="text-sm text-gray-700 truncate">
                {replyingTo.content || '[Voice message]'}
              </div>
            </div>
            <button
              onClick={onCancelReply}
              className="text-gray-400 hover:text-gray-600 ml-2"
            >
              ✕
            </button>
          </div>
        </div>
      )}

      {/* Voice Recorder Modal */}
      {showVoiceRecorder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <VoiceRecorder
            isOpen={showVoiceRecorder}
            onRecordingComplete={handleVoiceRecording}
            onCancel={() => setShowVoiceRecorder(false)}
          />
        </div>
      )}

      {/* Scheduler Modal */}
      {showScheduler && (
        <div className="px-4 py-3 bg-blue-50 border-b border-blue-200">
          <div className="flex items-center space-x-3">
            <div className="text-sm text-blue-700 font-medium">Schedule Message:</div>
            <input
              type="date"
              value={scheduledDate}
              onChange={(e) => setScheduledDate(e.target.value)}
              className="text-xs border border-blue-300 rounded px-2 py-1"
              min={new Date().toISOString().split('T')[0]}
            />
            <input
              type="time"
              value={scheduledTime}
              onChange={(e) => setScheduledTime(e.target.value)}
              className="text-xs border border-blue-300 rounded px-2 py-1"
            />
            <button
              onClick={() => setShowScheduler(false)}
              className="text-blue-600 hover:text-blue-800 text-xs"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      <div className="p-4">
        {/* Priority Selector */}
        {priority !== 'normal' && (
          <div className="mb-2 flex items-center space-x-2">
            <span className="text-xs text-gray-500">Priority:</span>
            <select
              value={priority}
              onChange={(e) => setPriority(e.target.value as 'low' | 'normal' | 'high' | 'urgent')}
              className="text-xs border border-gray-300 rounded px-2 py-1"
            >
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
        )}

        <form onSubmit={handleSubmit} className="flex space-x-3">
          {/* Attachment/Voice Button */}
          <div className="flex space-x-1">
            <button
              type="button"
              onClick={() => setShowVoiceRecorder(true)}
              className="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
              title="Record voice message"
            >
              <MicrophoneIcon className="h-4 w-4 text-gray-600" />
            </button>
            
            <div className="relative">
              <button
                type="button"
                className="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                title="More options"
              >
                <PlusIcon className="h-4 w-4 text-gray-600" />
              </button>
            </div>
          </div>

          <div className="flex-1">
            <Textarea
              ref={textareaRef}
              value={message}
              onChange={handleMessageChange}
              onKeyPress={handleKeyPress}
              placeholder="Type a message..."
              className="resize-none border-0 shadow-none focus:ring-0 p-0 text-sm min-h-[20px] max-h-32"
              rows={1}
              disabled={sending || disabled}
              data-testid="message-input"
            />
          </div>

          {/* Action Buttons */}
          <div className="flex space-x-1 self-end">
            {/* Scheduler Button */}
            <button
              type="button"
              onClick={() => setShowScheduler(!showScheduler)}
              className={`flex items-center justify-center w-8 h-8 rounded-full transition-colors ${
                showScheduler || scheduledDate 
                  ? 'bg-blue-100 text-blue-600' 
                  : 'bg-gray-100 hover:bg-gray-200 text-gray-600'
              }`}
              title="Schedule message"
            >
              <CalendarIcon className="h-4 w-4" />
            </button>

            {/* Priority Button */}
            <button
              type="button"
              onClick={() => {
                const priorities: ('low' | 'normal' | 'high' | 'urgent')[] = ['normal', 'low', 'high', 'urgent'];
                const currentIndex = priorities.indexOf(priority);
                setPriority(priorities[(currentIndex + 1) % priorities.length]);
              }}
              className={`flex items-center justify-center w-8 h-8 rounded-full transition-colors ${
                priority !== 'normal'
                  ? 'bg-orange-100 text-orange-600'
                  : 'bg-gray-100 hover:bg-gray-200 text-gray-600'
              }`}
              title={`Priority: ${priority}`}
            >
              !
            </button>

            {/* Send Button */}
            <Button 
              type="submit" 
              disabled={!message.trim() || sending || disabled}
              size="sm"
              className="w-8 h-8 p-0 rounded-full"
              data-testid="send-message"
            >
              {sending ? (
                <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
              ) : (
                <PaperAirplaneIcon className="h-4 w-4" />
              )}
            </Button>
          </div>
        </form>
        
        <div className="mt-2 text-xs text-center">
          {disabled ? (
            <span className="text-red-500">Device setup required for encrypted messaging</span>
          ) : encryptionReady ? (
            <span className="text-gray-500">Messages are end-to-end encrypted. Press Enter to send, Shift+Enter for new line.</span>
          ) : (
            <span className="text-yellow-600">Setting up encryption...</span>
          )}
        </div>
      </div>
    </div>
  );
}