import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { DocumentTextIcon, ClockIcon, TrashIcon, PencilIcon } from '@heroicons/react/24/outline';
import { formatDistanceToNow } from 'date-fns';
import { useE2EE } from '@/hooks/useE2EE';

interface Draft {
  id: string;
  conversationId: string;
  content: string;
  encryptedContent?: string;
  replyToId?: string;
  createdAt: Date;
  updatedAt: Date;
  metadata?: {
    type?: 'text' | 'voice' | 'file';
    fileInfo?: {
      name: string;
      size: number;
      type: string;
    };
    voiceInfo?: {
      duration: number;
      transcript?: string;
    };
  };
}

interface EncryptedDraftsProps {
  conversationId: string;
  currentDraft?: string;
  onLoadDraft: (content: string, metadata?: any) => void;
  onSaveDraft: (content: string, metadata?: any) => void;
  onDeleteDraft: (draftId: string) => void;
  userId: string;
}

const STORAGE_KEY_PREFIX = 'encrypted_drafts_';
const MAX_DRAFTS_PER_CONVERSATION = 10;
const DRAFT_SAVE_DEBOUNCE = 2000; // 2 seconds

export default function EncryptedDrafts({
  conversationId,
  currentDraft = '',
  onLoadDraft,
  onSaveDraft,
  onDeleteDraft,
  userId
}: EncryptedDraftsProps) {
  const [drafts, setDrafts] = useState<Draft[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [saveTimeout, setSaveTimeout] = useState<NodeJS.Timeout | null>(null);
  
  const { encryptMessage, decryptMessage, isReady } = useE2EE(userId);

  // Load drafts from encrypted storage
  const loadDrafts = useCallback(async () => {
    if (!isReady) return;
    
    setIsLoading(true);
    try {
      const storageKey = `${STORAGE_KEY_PREFIX}${conversationId}`;
      const encryptedDraftsData = localStorage.getItem(storageKey);
      
      if (!encryptedDraftsData) {
        setDrafts([]);
        return;
      }

      const parsedData = JSON.parse(encryptedDraftsData);
      const decryptedDrafts: Draft[] = [];

      for (const encryptedDraft of parsedData.drafts || []) {
        try {
          const decryptedContent = await decryptMessage(
            encryptedDraft.encryptedContent,
            conversationId
          );

          if (decryptedContent) {
            decryptedDrafts.push({
              ...encryptedDraft,
              content: decryptedContent,
              createdAt: new Date(encryptedDraft.createdAt),
              updatedAt: new Date(encryptedDraft.updatedAt),
            });
          }
        } catch (error) {
          console.warn('Failed to decrypt draft:', encryptedDraft.id, error);
        }
      }

      setDrafts(decryptedDrafts.sort((a, b) => b.updatedAt.getTime() - a.updatedAt.getTime()));
    } catch (error) {
      console.error('Failed to load drafts:', error);
    } finally {
      setIsLoading(false);
    }
  }, [conversationId, decryptMessage, isReady]);

  // Save draft to encrypted storage
  const saveDraft = useCallback(async (content: string, metadata?: any) => {
    if (!isReady || !content.trim()) return;

    try {
      const encryptedContent = await encryptMessage(content, conversationId);
      if (!encryptedContent) {
        console.error('Failed to encrypt draft');
        return;
      }

      const draft: Draft = {
        id: crypto.randomUUID(),
        conversationId,
        content,
        encryptedContent: JSON.stringify(encryptedContent),
        createdAt: new Date(),
        updatedAt: new Date(),
        metadata,
      };

      // Update drafts list
      setDrafts(prevDrafts => {
        const updatedDrafts = [draft, ...prevDrafts.filter(d => d.content !== content)]
          .slice(0, MAX_DRAFTS_PER_CONVERSATION);

        // Save to storage
        const storageKey = `${STORAGE_KEY_PREFIX}${conversationId}`;
        const encryptedDrafts = updatedDrafts.map(d => ({
          ...d,
          content: undefined, // Don't store plain content
        }));

        localStorage.setItem(storageKey, JSON.stringify({
          drafts: encryptedDrafts,
          version: 1,
          updatedAt: new Date().toISOString(),
        }));

        return updatedDrafts;
      });

      onSaveDraft(content, metadata);
    } catch (error) {
      console.error('Failed to save draft:', error);
    }
  }, [conversationId, encryptMessage, isReady, onSaveDraft]);

  // Auto-save draft with debouncing
  useEffect(() => {
    if (!currentDraft.trim()) return;

    if (saveTimeout) {
      clearTimeout(saveTimeout);
    }

    const timeout = setTimeout(() => {
      saveDraft(currentDraft);
    }, DRAFT_SAVE_DEBOUNCE);

    setSaveTimeout(timeout);

    return () => {
      if (timeout) {
        clearTimeout(timeout);
      }
    };
  }, [currentDraft, saveDraft]);

  // Load drafts when component mounts or conversation changes
  useEffect(() => {
    loadDrafts();
  }, [loadDrafts]);

  const handleDeleteDraft = async (draftId: string) => {
    try {
      const updatedDrafts = drafts.filter(d => d.id !== draftId);
      setDrafts(updatedDrafts);

      // Update storage
      const storageKey = `${STORAGE_KEY_PREFIX}${conversationId}`;
      const encryptedDrafts = updatedDrafts.map(d => ({
        ...d,
        content: undefined,
      }));

      localStorage.setItem(storageKey, JSON.stringify({
        drafts: encryptedDrafts,
        version: 1,
        updatedAt: new Date().toISOString(),
      }));

      onDeleteDraft(draftId);
    } catch (error) {
      console.error('Failed to delete draft:', error);
    }
  };

  const handleLoadDraft = (draft: Draft) => {
    onLoadDraft(draft.content, draft.metadata);
    setIsOpen(false);
  };

  const getDraftPreview = (content: string, maxLength: number = 100): string => {
    if (content.length <= maxLength) return content;
    return content.substring(0, maxLength) + '...';
  };

  const getDraftTypeIcon = (metadata?: Draft['metadata']) => {
    switch (metadata?.type) {
      case 'voice':
        return '🎵';
      case 'file':
        return '📎';
      default:
        return '💬';
    }
  };

  return (
    <>
      {drafts.length > 0 && (
        <Badge variant="outline" className="flex items-center gap-1">
          <DocumentTextIcon className="h-3 w-3" />
          {drafts.length} draft{drafts.length !== 1 ? 's' : ''}
        </Badge>
      )}

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogTrigger asChild>
          <Button
            variant="ghost"
            size="sm"
            className="flex items-center gap-2"
            disabled={drafts.length === 0}
          >
            <DocumentTextIcon className="h-4 w-4" />
            Drafts ({drafts.length})
          </Button>
        </DialogTrigger>
        <DialogContent className="max-w-lg max-h-[70vh]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <DocumentTextIcon className="h-5 w-5" />
              Encrypted Message Drafts
            </DialogTitle>
            <DialogDescription>
              Your drafts are encrypted and stored securely. Select one to continue editing.
            </DialogDescription>
          </DialogHeader>

          <ScrollArea className="flex-1">
            {isLoading ? (
              <div className="text-center py-8 text-muted-foreground">
                Loading encrypted drafts...
              </div>
            ) : drafts.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                No drafts saved for this conversation
              </div>
            ) : (
              <div className="space-y-3">
                {drafts.map((draft) => (
                  <div
                    key={draft.id}
                    className="border rounded-lg p-3 space-y-2 hover:bg-muted/50 transition-colors"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex items-center gap-2 flex-1 min-w-0">
                        <span className="text-sm">
                          {getDraftTypeIcon(draft.metadata)}
                        </span>
                        <div className="flex-1 min-w-0">
                          <div className="text-sm truncate">
                            {getDraftPreview(draft.content, 80)}
                          </div>
                          <div className="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
                            <ClockIcon className="h-3 w-3" />
                            {formatDistanceToNow(draft.updatedAt, { addSuffix: true })}
                            {draft.metadata?.type && (
                              <Badge variant="outline" className="text-xs px-1">
                                {draft.metadata.type}
                              </Badge>
                            )}
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-1 ml-2">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => handleLoadDraft(draft)}
                          className="h-8 w-8 p-0"
                        >
                          <PencilIcon className="h-3 w-3" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => handleDeleteDraft(draft.id)}
                          className="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                        >
                          <TrashIcon className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>

                    {draft.metadata?.fileInfo && (
                      <div className="text-xs text-muted-foreground bg-muted p-2 rounded">
                        📎 {draft.metadata.fileInfo.name} ({Math.round(draft.metadata.fileInfo.size / 1024)} KB)
                      </div>
                    )}

                    {draft.metadata?.voiceInfo && (
                      <div className="text-xs text-muted-foreground bg-muted p-2 rounded">
                        🎵 Voice message ({draft.metadata.voiceInfo.duration}s)
                        {draft.metadata.voiceInfo.transcript && (
                          <div className="mt-1 italic">"{draft.metadata.voiceInfo.transcript}"</div>
                        )}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </ScrollArea>

          <div className="flex justify-between items-center pt-4 border-t text-xs text-muted-foreground">
            <div>
              Drafts are automatically encrypted and saved as you type
            </div>
            <div>
              {drafts.length}/{MAX_DRAFTS_PER_CONVERSATION} drafts
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}