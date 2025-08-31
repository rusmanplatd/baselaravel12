import React, { useState, useRef, useEffect, useCallback } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Mention from '@tiptap/extension-mention';
import { Button } from '@/components/ui/button';
import { PaperAirplaneIcon, MicrophoneIcon, CalendarIcon } from '@heroicons/react/24/solid';
import { Message, VoiceRecording, Participant, User } from '@/types/chat';
import VoiceRecorder from './VoiceRecorder';
import MentionAutocomplete from './MentionAutocomplete';
import { 
  convertToMessageMentions, 
  getCurrentMentionQuery 
} from '@/utils/mentions';

interface TiptapMessageEditorProps {
  onSendMessage: (content: string, options?: {
    type?: 'text' | 'voice';
    priority?: 'low' | 'normal' | 'high' | 'urgent';
    scheduledAt?: Date;
    voiceData?: VoiceRecording;
    replyToId?: string;
    mentions?: any[];
  }) => Promise<void>;
  replyingTo?: Message | null;
  onCancelReply?: () => void;
  onTyping?: (isTyping: boolean) => void;
  encryptionReady?: boolean;
  disabled?: boolean;
  participants?: Participant[];
  currentUserId: string;
}

export default function TiptapMessageEditor({ 
  onSendMessage, 
  replyingTo, 
  onCancelReply, 
  onTyping,
  encryptionReady = true,
  disabled = false,
  participants = [],
  currentUserId
}: TiptapMessageEditorProps) {
  const [sending, setSending] = useState(false);
  const [showVoiceRecorder, setShowVoiceRecorder] = useState(false);
  const [showScheduler, setShowScheduler] = useState(false);
  const [priority, setPriority] = useState<'low' | 'normal' | 'high' | 'urgent'>('normal');
  const [scheduledDate, setScheduledDate] = useState('');
  const [scheduledTime, setScheduledTime] = useState('');
  
  // Mention states
  const [showMentionAutocomplete, setShowMentionAutocomplete] = useState(false);
  const [mentionQuery, setMentionQuery] = useState('');
  const [autocompletePosition, setAutocompletePosition] = useState({ top: 0, left: 0 });
  
  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: false,
        codeBlock: false,
        horizontalRule: false,
        blockquote: false,
      }),
      Placeholder.configure({
        placeholder: 'Type a message...',
      }),
      Mention.configure({
        HTMLAttributes: {
          class: 'mention',
        },
        suggestion: {
          items: ({ query }: { query: string }) => {
            return participants
              .filter(p => p.user && p.user_id !== currentUserId)
              .map(p => p.user!)
              .filter(user => 
                user.name.toLowerCase().includes(query.toLowerCase())
              )
              .slice(0, 5);
          },
          render: () => {
            let component: any;
            let popup: any;

            return {
              onStart: (props: any) => {
                setMentionQuery(props.query);
                setShowMentionAutocomplete(true);
                
                // Calculate position for autocomplete
                const editorElement = document.querySelector('.ProseMirror');
                if (editorElement) {
                  const rect = editorElement.getBoundingClientRect();
                  setAutocompletePosition({
                    top: rect.top - 40,
                    left: rect.left,
                  });
                }
              },
              onUpdate(props: any) {
                setMentionQuery(props.query);
              },
              onKeyDown(props: any) {
                if (props.event.key === 'Escape') {
                  setShowMentionAutocomplete(false);
                  return true;
                }
                return false;
              },
              onExit() {
                setShowMentionAutocomplete(false);
              },
            };
          },
        },
      }),
    ],
    content: '',
    onUpdate: ({ editor }) => {
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
    },
    editorProps: {
      attributes: {
        class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-2xl mx-auto focus:outline-none min-h-[20px] max-h-32 overflow-y-auto',
        'data-testid': 'message-input',
      },
    },
  });

  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);

  const handleSubmit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    
    if (!editor || sending || disabled) return;
    
    const content = editor.getHTML();
    const plainText = editor.getText().trim();
    
    if (!plainText) return;
    
    setSending(true);
    
    // Hide mention autocomplete
    setShowMentionAutocomplete(false);
    
    // Stop typing indicator
    onTyping?.(false);
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }
    
    try {
      const scheduledAt = scheduledDate && scheduledTime 
        ? new Date(`${scheduledDate}T${scheduledTime}`)
        : undefined;
      
      // Extract mentions from Tiptap content
      const mentions = extractMentionsFromTiptap(content, participants);
      
      await onSendMessage(content, {
        type: 'text',
        priority,
        scheduledAt,
        replyToId: replyingTo?.id,
        mentions,
      });

      // Clear editor
      editor.commands.clearContent();
      
      // Reset states
      setPriority('normal');
      setScheduledDate('');
      setScheduledTime('');
      setShowScheduler(false);
      onCancelReply?.();
    } catch {
      // Keep content on error
    } finally {
      setSending(false);
    }
  };

  const extractMentionsFromTiptap = (htmlContent: string, participants: Participant[]) => {
    const mentionRegex = /@([^@\s]+)/g;
    const mentions: any[] = [];
    let match;
    
    while ((match = mentionRegex.exec(htmlContent)) !== null) {
      const username = match[1];
      const participant = participants.find(p => p.user?.name === username);
      if (participant?.user) {
        mentions.push({
          type: 'user',
          id: participant.user.id,
          name: participant.user.name,
          position: match.index,
          length: match[0].length,
        });
      }
    }
    
    return mentions;
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

  const handleMentionSelect = (user: User) => {
    if (!editor) return;
    
    // Insert mention into Tiptap
    editor.chain()
      .focus()
      .insertContent(`@${user.name} `)
      .run();
    
    setShowMentionAutocomplete(false);
  };

  const handleKeyPress = useCallback((event: React.KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      handleSubmit();
    }
  }, [handleSubmit]);

  // Add keyboard event listener to editor
  useEffect(() => {
    if (editor) {
      const handleKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          handleSubmit();
        }
      };

      editor.view.dom.addEventListener('keydown', handleKeyDown);
      return () => {
        editor.view.dom.removeEventListener('keydown', handleKeyDown);
      };
    }
  }, [editor, handleSubmit]);

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
          {/* Voice Button */}
          <button
            type="button"
            onClick={() => setShowVoiceRecorder(true)}
            className="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
            title="Record voice message"
          >
            <MicrophoneIcon className="h-4 w-4 text-gray-600" />
          </button>

          <div className="flex-1 relative border border-gray-300 rounded-lg p-2 min-h-[40px] max-h-32 overflow-y-auto">
            <EditorContent 
              editor={editor} 
              className="prose prose-sm max-w-none"
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
              disabled={!editor?.getText().trim() || sending || disabled}
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
      
      {/* Mention Autocomplete */}
      <MentionAutocomplete
        query={mentionQuery}
        participants={participants}
        currentUserId={currentUserId}
        onSelect={handleMentionSelect}
        onClose={() => setShowMentionAutocomplete(false)}
        position={autocompletePosition}
        isVisible={showMentionAutocomplete}
      />
    </div>
  );
}