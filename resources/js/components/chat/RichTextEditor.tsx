import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useEditor, EditorContent, JSONContent } from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extension-placeholder';
import { Mention } from '@tiptap/extension-mention';
import { Bold } from '@tiptap/extension-bold';
import { Italic } from '@tiptap/extension-italic';
import { Strike } from '@tiptap/extension-strike';
import { Underline } from '@tiptap/extension-underline';
import { Code } from '@tiptap/extension-code';
import { Link } from '@tiptap/extension-link';
import { Blockquote } from '@tiptap/extension-blockquote';
import { CodeBlock } from '@tiptap/extension-code-block';
import { BulletList } from '@tiptap/extension-bullet-list';
import { OrderedList } from '@tiptap/extension-ordered-list';
import { ListItem } from '@tiptap/extension-list-item';
import { HardBreak } from '@tiptap/extension-hard-break';

import { Button } from '@/components/ui/button';
import { 
  Bold as BoldIcon, 
  Italic as ItalicIcon, 
  Strikethrough, 
  Underline as UnderlineIcon,
  Code2,
  Quote,
  List,
  ListOrdered,
  Link2,
  Type,
  Hash,
  AtSign,
  Smile
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface User {
  id: string;
  name: string;
  avatar?: string;
  email?: string;
  online?: boolean;
}

interface Channel {
  id: string;
  name: string;
  type: 'public' | 'private';
  memberCount?: number;
}

interface RichTextEditorProps {
  content?: string | JSONContent;
  placeholder?: string;
  onUpdate?: (content: JSONContent, text: string) => void;
  onSubmit?: (content: JSONContent, text: string) => void;
  onEmojiClick?: () => void;
  disabled?: boolean;
  mentionableUsers?: User[];
  mentionableChannels?: Channel[];
  className?: string;
  editable?: boolean;
  minHeight?: number;
  maxHeight?: number;
  showToolbar?: boolean;
  autoFocus?: boolean;
}

interface MentionSuggestion {
  type: 'user' | 'channel';
  id: string;
  name: string;
  avatar?: string;
  email?: string;
  online?: boolean;
}

const RichTextEditor: React.FC<RichTextEditorProps> = ({
  content = '',
  placeholder = 'Type a message...',
  onUpdate,
  onSubmit,
  onEmojiClick,
  disabled = false,
  mentionableUsers = [],
  mentionableChannels = [],
  className,
  editable = true,
  minHeight = 40,
  maxHeight = 200,
  showToolbar = true,
  autoFocus = false
}) => {
  const [suggestions, setSuggestions] = useState<MentionSuggestion[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [selectedSuggestion, setSelectedSuggestion] = useState(0);
  const suggestionRef = useRef<HTMLDivElement>(null);

  // Create mention suggestions list
  const createSuggestions = useCallback((query: string): MentionSuggestion[] => {
    const userSuggestions: MentionSuggestion[] = mentionableUsers
      .filter(user => user.name.toLowerCase().includes(query.toLowerCase()))
      .map(user => ({
        type: 'user' as const,
        id: user.id,
        name: user.name,
        avatar: user.avatar,
        email: user.email,
        online: user.online
      }));

    const channelSuggestions: MentionSuggestion[] = mentionableChannels
      .filter(channel => channel.name.toLowerCase().includes(query.toLowerCase()))
      .map(channel => ({
        type: 'channel' as const,
        id: channel.id,
        name: channel.name
      }));

    return [...userSuggestions, ...channelSuggestions];
  }, [mentionableUsers, mentionableChannels]);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        // Disable some extensions we'll configure manually
        bold: false,
        italic: false,
        strike: false,
        code: false,
        bulletList: false,
        orderedList: false,
        listItem: false,
        blockquote: false,
        codeBlock: false,
        hardBreak: false,
      }),
      Bold,
      Italic,
      Strike,
      Underline,
      Code.configure({
        HTMLAttributes: {
          class: 'bg-muted px-1 py-0.5 rounded text-sm font-mono'
        }
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-blue-500 hover:text-blue-600 underline'
        }
      }),
      Blockquote.configure({
        HTMLAttributes: {
          class: 'border-l-4 border-muted-foreground pl-4 italic'
        }
      }),
      CodeBlock.configure({
        HTMLAttributes: {
          class: 'bg-muted p-3 rounded font-mono text-sm'
        }
      }),
      BulletList.configure({
        HTMLAttributes: {
          class: 'list-disc list-inside'
        }
      }),
      OrderedList.configure({
        HTMLAttributes: {
          class: 'list-decimal list-inside'
        }
      }),
      ListItem,
      HardBreak.configure({
        keepMarks: false,
      }),
      Placeholder.configure({
        placeholder
      }),
      Mention.configure({
        HTMLAttributes: {
          class: 'mention bg-blue-100 text-blue-800 px-1 py-0.5 rounded font-medium'
        },
        suggestion: {
          items: ({ query }) => {
            const results = createSuggestions(query);
            setSuggestions(results);
            return results;
          },
          render: () => {
            let component: any;
            let popup: any;

            return {
              onStart: (props: any) => {
                component = {
                  selectedIndex: 0,
                  selectItem: (index: number) => {
                    const item = props.items[index];
                    if (item) {
                      props.command({
                        id: item.id,
                        label: item.type === 'user' ? `@${item.name}` : `#${item.name}`
                      });
                    }
                  }
                };

                setShowSuggestions(true);
                setSelectedSuggestion(0);
                setSuggestions(props.items);
              },

              onUpdate: (props: any) => {
                setSuggestions(props.items);
                component.selectedIndex = Math.min(component.selectedIndex, props.items.length - 1);
              },

              onKeyDown: (props: any) => {
                if (props.event.key === 'ArrowUp') {
                  component.selectedIndex = Math.max(0, component.selectedIndex - 1);
                  setSelectedSuggestion(component.selectedIndex);
                  return true;
                }

                if (props.event.key === 'ArrowDown') {
                  component.selectedIndex = Math.min(props.items.length - 1, component.selectedIndex + 1);
                  setSelectedSuggestion(component.selectedIndex);
                  return true;
                }

                if (props.event.key === 'Enter') {
                  component.selectItem(component.selectedIndex);
                  return true;
                }

                return false;
              },

              onExit: () => {
                setShowSuggestions(false);
                setSuggestions([]);
                setSelectedSuggestion(0);
              }
            };
          }
        }
      })
    ],
    content,
    editable,
    autofocus: autoFocus,
    onUpdate: ({ editor }) => {
      const json = editor.getJSON();
      const text = editor.getText();
      onUpdate?.(json, text);
    },
    editorProps: {
      attributes: {
        class: cn(
          'prose prose-sm max-w-none focus:outline-none',
          'min-h-[40px] p-3',
          disabled && 'opacity-50 cursor-not-allowed',
          className
        )
      },
      handleKeyDown: (view, event) => {
        // Handle Enter key for submission
        if (event.key === 'Enter' && !event.shiftKey && !showSuggestions) {
          event.preventDefault();
          const json = editor?.getJSON();
          const text = editor?.getText();
          if (json && text && onSubmit) {
            onSubmit(json, text);
            editor?.commands.clearContent();
            return true;
          }
        }
        return false;
      }
    }
  });

  // Focus editor when enabled
  useEffect(() => {
    if (editor && autoFocus && editable) {
      editor.commands.focus();
    }
  }, [editor, autoFocus, editable]);

  const toggleBold = useCallback(() => {
    editor?.chain().focus().toggleBold().run();
  }, [editor]);

  const toggleItalic = useCallback(() => {
    editor?.chain().focus().toggleItalic().run();
  }, [editor]);

  const toggleStrike = useCallback(() => {
    editor?.chain().focus().toggleStrike().run();
  }, [editor]);

  const toggleUnderline = useCallback(() => {
    editor?.chain().focus().toggleUnderline().run();
  }, [editor]);

  const toggleCode = useCallback(() => {
    editor?.chain().focus().toggleCode().run();
  }, [editor]);

  const toggleBlockquote = useCallback(() => {
    editor?.chain().focus().toggleBlockquote().run();
  }, [editor]);

  const toggleBulletList = useCallback(() => {
    editor?.chain().focus().toggleBulletList().run();
  }, [editor]);

  const toggleOrderedList = useCallback(() => {
    editor?.chain().focus().toggleOrderedList().run();
  }, [editor]);

  const setLink = useCallback(() => {
    const previousUrl = editor?.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    // cancelled
    if (url === null) {
      return;
    }

    // empty
    if (url === '') {
      editor?.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }

    // update link
    editor?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }, [editor]);

  const insertMention = useCallback((type: 'user' | 'channel') => {
    const char = type === 'user' ? '@' : '#';
    editor?.chain().focus().insertContent(char).run();
  }, [editor]);

  if (!editor) {
    return null;
  }

  return (
    <div className="relative">
      {/* Toolbar */}
      {showToolbar && editable && (
        <div className="flex items-center gap-1 p-2 border-b border-border">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleBold}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('bold') && 'bg-accent'
            )}
          >
            <BoldIcon className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleItalic}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('italic') && 'bg-accent'
            )}
          >
            <ItalicIcon className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleStrike}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('strike') && 'bg-accent'
            )}
          >
            <Strikethrough className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleUnderline}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('underline') && 'bg-accent'
            )}
          >
            <UnderlineIcon className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleCode}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('code') && 'bg-accent'
            )}
          >
            <Code2 className="h-4 w-4" />
          </Button>
          
          <div className="w-px h-6 bg-border mx-1" />
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleBlockquote}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('blockquote') && 'bg-accent'
            )}
          >
            <Quote className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleBulletList}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('bulletList') && 'bg-accent'
            )}
          >
            <List className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={toggleOrderedList}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('orderedList') && 'bg-accent'
            )}
          >
            <ListOrdered className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={setLink}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('link') && 'bg-accent'
            )}
          >
            <Link2 className="h-4 w-4" />
          </Button>
          
          <div className="w-px h-6 bg-border mx-1" />
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => insertMention('user')}
          >
            <AtSign className="h-4 w-4" />
          </Button>
          
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => insertMention('channel')}
          >
            <Hash className="h-4 w-4" />
          </Button>

          {onEmojiClick && (
            <>
              <div className="w-px h-6 bg-border mx-1" />
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={onEmojiClick}
              >
                <Smile className="h-4 w-4" />
              </Button>
            </>
          )}
        </div>
      )}

      {/* Editor */}
      <div 
        className="relative"
        style={{ 
          minHeight: `${minHeight}px`,
          maxHeight: `${maxHeight}px`,
          overflowY: 'auto'
        }}
      >
        <EditorContent 
          editor={editor}
          className={cn(
            'prose prose-sm max-w-none',
            disabled && 'pointer-events-none'
          )}
        />

        {/* Mention Suggestions */}
        {showSuggestions && suggestions.length > 0 && (
          <div 
            ref={suggestionRef}
            className="absolute z-50 bg-background border border-border rounded-lg shadow-lg max-h-48 overflow-y-auto"
            style={{ top: '100%', left: '0', minWidth: '200px' }}
          >
            {suggestions.map((suggestion, index) => (
              <div
                key={`${suggestion.type}-${suggestion.id}`}
                className={cn(
                  'flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-accent',
                  index === selectedSuggestion && 'bg-accent'
                )}
                onClick={() => {
                  editor.chain().focus().insertContent(
                    suggestion.type === 'user' ? `@${suggestion.name}` : `#${suggestion.name}`
                  ).run();
                  setShowSuggestions(false);
                }}
              >
                {suggestion.type === 'user' ? (
                  <>
                    <div className="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-xs font-medium">
                      {suggestion.avatar ? (
                        <img 
                          src={suggestion.avatar} 
                          alt={suggestion.name}
                          className="w-6 h-6 rounded-full"
                        />
                      ) : (
                        suggestion.name.charAt(0).toUpperCase()
                      )}
                    </div>
                    <div className="flex flex-col">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-sm">{suggestion.name}</span>
                        {suggestion.online && (
                          <div className="w-2 h-2 bg-green-500 rounded-full" />
                        )}
                      </div>
                      {suggestion.email && (
                        <span className="text-xs text-muted-foreground">{suggestion.email}</span>
                      )}
                    </div>
                  </>
                ) : (
                  <>
                    <div className="w-6 h-6 rounded bg-accent flex items-center justify-center">
                      <Hash className="w-3 h-3" />
                    </div>
                    <div className="flex flex-col">
                      <span className="font-medium text-sm">#{suggestion.name}</span>
                    </div>
                  </>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default RichTextEditor;