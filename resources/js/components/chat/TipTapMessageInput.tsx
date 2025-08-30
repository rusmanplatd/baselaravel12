import React, { useState, useRef, useEffect } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import CodeBlock from '@tiptap/extension-code-block';
import Code from '@tiptap/extension-code';
import Highlight from '@tiptap/extension-highlight';
import { Button } from '@/components/ui/button';
import { PaperAirplaneIcon, MicrophoneIcon, CalendarIcon } from '@heroicons/react/24/solid';
import { ArrowUturnLeftIcon } from '@heroicons/react/24/outline';
import { Message, VoiceRecording, Participant } from '@/types/chat';
import VoiceRecorder from './VoiceRecorder';
import { parseMentions } from '@/utils/mentions';

interface TipTapMessageInputProps {
  onSendMessage: (content: string, options?: {
    type?: 'text' | 'voice';
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    scheduledAt?: Date;
    voiceData?: VoiceRecording;
    replyToId?: string;
    mentions?: unknown[];
  }) => Promise<void>;
  replyingTo?: Message | null;
  onCancelReply?: () => void;
  onTyping?: (isTyping: boolean) => void;
  encryptionReady?: boolean;
  disabled?: boolean;
  participants?: Participant[];
  currentUserId: string;
}

export default function TipTapMessageInput({
  onSendMessage,
  replyingTo,
  onCancelReply,
  onTyping,
  disabled = false,
}: TipTapMessageInputProps) {

  const [sending, setSending] = useState(false);
  const [showVoiceRecorder, setShowVoiceRecorder] = useState(false);
  const [showScheduler, setShowScheduler] = useState(false);
  const [priority, setPriority] = useState<'low' | 'normal' | 'high' | 'urgent'>('normal');
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');

  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        bulletList: {
          keepMarks: true,
          keepAttributes: false,
        },
        orderedList: {
          keepMarks: true,
          keepAttributes: false,
        },
      }),
      Placeholder.configure({
        placeholder: 'Type a message...',
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-blue-500 underline',
        },
      }),
      Underline,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      CodeBlock,
      Code,
      Highlight,
    ],
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none',
      },
    },
    onUpdate: ({ editor }) => {
      const content = editor.getHTML();
      if (onTyping) {
        onTyping(content.length > 0);
      }

      // Handle typing timeout
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }

      if (content.length > 0) {
        typingTimeoutRef.current = setTimeout(() => {
          if (onTyping) {
            onTyping(false);
          }
        }, 1000);
      }
    },
  });

  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!editor || sending || disabled) return;

    const content = editor.getHTML();
    if (!content.trim()) return;

    setSending(true);

    try {
      // Extract mentions from content
      const mentions = parseMentions(content);

      await onSendMessage(content, {
        mentions,
        priority,
        scheduledAt: scheduledDate && scheduledTime
          ? new Date(`${scheduledDate}T${scheduledTime}`)
          : undefined,
      });

      // Clear editor
      editor.commands.clearContent();

      // Reset states
      setPriority('normal');
      setScheduledDate('');
      setScheduledTime('');
      setShowScheduler(false);

    } catch (error) {
      console.error('Failed to send message:', error);
    } finally {
      setSending(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e as React.FormEvent);
    }
  };

  const handleVoiceRecording = async (voiceData: VoiceRecording) => {
    setSending(true);
    try {
      await onSendMessage('', {
        type: 'voice',
        voiceData,
        priority,
      });
    } catch (error) {
      console.error('Failed to send voice message:', error);
    } finally {
      setSending(false);
      setShowVoiceRecorder(false);
    }
  };

  if (!editor) {
    return null;
  }

  return (
    <div className="border-t border-gray-200 bg-white p-4">
      {/* Reply indicator */}
      {replyingTo && (
        <div className="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <ArrowUturnLeftIcon className="h-4 w-4 text-blue-600" />
              <span className="text-sm text-blue-800">Replying to {replyingTo.sender?.name}</span>
            </div>
            <button
              onClick={onCancelReply}
              className="text-blue-600 hover:text-blue-800"
            >
              ×
            </button>
          </div>
          <div className="mt-1 text-sm text-gray-600 truncate">
            {replyingTo.content}
          </div>
        </div>
      )}

      {/* Voice Recorder */}
      {showVoiceRecorder && (
        <div className="mb-4">
          <VoiceRecorder
            onRecordingComplete={handleVoiceRecording}
            onCancel={() => setShowVoiceRecorder(false)}
            isOpen={showVoiceRecorder}
          />
        </div>
      )}

      {/* Message Input Form */}
      <form onSubmit={handleSubmit} className="flex items-end space-x-3">
        <div className="flex-1 bg-gray-50 rounded-lg border border-gray-200 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
          <div className="p-3">
            <EditorContent
              editor={editor}
              onKeyDown={handleKeyPress}
              className="min-h-[20px] max-h-32 overflow-y-auto"
            />
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-between px-3 pb-2">
            <div className="flex space-x-1">
              {/* Voice Recording Button */}
              <button
                type="button"
                onClick={() => setShowVoiceRecorder(!showVoiceRecorder)}
                className={`flex items-center justify-center w-8 h-8 rounded-full transition-colors ${
                  showVoiceRecorder
                    ? 'bg-red-100 text-red-600'
                    : 'bg-gray-100 hover:bg-gray-200 text-gray-600'
                }`}
                title="Voice message"
              >
                <MicrophoneIcon className="h-4 w-4" />
              </button>

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
            </div>

            {/* Send Button */}
            <Button
              type="submit"
              size="sm"
              disabled={sending || disabled || !editor.getHTML().trim()}
              className="bg-blue-500 hover:bg-blue-600 text-white"
            >
              <PaperAirplaneIcon className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </form>

      {/* Scheduler Panel */}
      {showScheduler && (
        <div className="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Date
              </label>
              <input
                type="date"
                value={scheduledDate}
                onChange={(e) => setScheduledDate(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Time
              </label>
              <input
                type="time"
                value={scheduledTime}
                onChange={(e) => setScheduledTime(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
