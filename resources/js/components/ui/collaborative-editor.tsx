import { EditorContent, useEditor } from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extension-placeholder';
import { Link } from '@tiptap/extension-link';
import { Image } from '@tiptap/extension-image';
import { TaskList } from '@tiptap/extension-task-list';
import { TaskItem } from '@tiptap/extension-task-item';
import { Highlight } from '@tiptap/extension-highlight';
import { Underline } from '@tiptap/extension-underline';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TextAlign } from '@tiptap/extension-text-align';
import { Color } from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import { FontFamily } from '@tiptap/extension-font-family';
import { Focus } from '@tiptap/extension-focus';
import { Dropcursor } from '@tiptap/extension-dropcursor';
import { Gapcursor } from '@tiptap/extension-gapcursor';
import React, { useEffect, useRef, useState, useCallback } from 'react';
import * as Y from 'yjs';
import { WebsocketProvider } from 'y-websocket';
import { ySyncPlugin, yCursorPlugin, yUndoPlugin, undo, redo } from 'y-prosemirror';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Toggle } from '@/components/ui/toggle';
import { 
  Bold, 
  Italic, 
  Underline as UnderlineIcon,
  Strikethrough,
  Code,
  Heading1,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Undo,
  Redo,
  Link as LinkIcon,
  Image as ImageIcon,
  Highlighter,
  Users,
  Save,
  History,
  Settings,
  AlignLeft,
  AlignCenter,
  AlignRight,
  AlignJustify,
  Table as TableIcon,
  Palette,
  Type,
  Ruler,
  FileText,
  Layout,
  BookOpen,
  Printer,
  ZoomIn,
  ZoomOut,
  Eye,
  EyeOff
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import apiService from '@/services/ApiService';
import { CollaboratorsList } from './collaborators-list';
import { DocumentVersionHistory } from './document-version-history';
import { ShareDocumentDialog } from './share-document-dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface User {
  id: string;
  name: string;
  email: string;
}

interface Document {
  id: string;
  title: string;
  content: string;
  yjs_state?: number[];
  is_collaborative: boolean;
  can_edit: boolean;
  can_comment: boolean;
  active_collaborators_count: number;
  metadata?: {
    page_settings?: {
      size: string;
      orientation: string;
      margins: {
        top: number;
        bottom: number;
        left: number;
        right: number;
      };
    };
    header?: string;
    footer?: string;
    page_numbers: boolean;
  };
}

interface CollaborativeEditorProps {
  document: Document;
  user: User;
  className?: string;
  onSave?: (content: string, metadata?: any) => void;
  onTitleChange?: (title: string) => void;
}

export function CollaborativeEditor({ 
  document, 
  user, 
  className,
  onSave,
  onTitleChange
}: CollaborativeEditorProps) {
  const [title, setTitle] = useState(document.title);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isConnected, setIsConnected] = useState(false);
  const [collaborators, setCollaborators] = useState([]);
  const [showCollaborators, setShowCollaborators] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [showShare, setShowShare] = useState(false);
  const [showTOC, setShowTOC] = useState(false);
  const [tocItems, setTocItems] = useState<Array<{id: string, level: number, text: string}>>([]);
  const [showRuler, setShowRuler] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [zoom, setZoom] = useState(100);
  const [viewMode, setViewMode] = useState<'edit' | 'preview' | 'print'>('edit');
  const [pageSettings, setPageSettings] = useState({
    size: 'A4',
    orientation: 'portrait',
    margins: { top: 2.54, bottom: 2.54, left: 2.54, right: 2.54 }
  });
  const [headerText, setHeaderText] = useState('');
  const [footerText, setFooterText] = useState('');
  const [showPageNumbers, setShowPageNumbers] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  
  const ydoc = useRef<Y.Doc>();
  const provider = useRef<WebsocketProvider>();
  const saveTimeoutRef = useRef<NodeJS.Timeout>();
  const editorRef = useRef<HTMLDivElement>(null);

  // Font families
  const fontFamilies = [
    { value: 'Arial', label: 'Arial' },
    { value: 'Calibri', label: 'Calibri' },
    { value: 'Times New Roman', label: 'Times New Roman' },
    { value: 'Georgia', label: 'Georgia' },
    { value: 'Helvetica', label: 'Helvetica' },
    { value: 'Courier New', label: 'Courier New' },
    { value: 'Verdana', label: 'Verdana' },
  ];

  // Font sizes
  const fontSizes = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72];

  // Initialize Yjs document and WebSocket provider
  useEffect(() => {
    if (!document.is_collaborative) {
      return;
    }

    ydoc.current = new Y.Doc();
    
    // Get auth token for WebSocket connection
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    
    if (!token) {
      toast.error('Authentication token not found. Please log in again.');
      return;
    }

    const wsUrl = `ws://localhost:1234?documentId=${document.id}&token=${token}`;
    
    setIsConnecting(true);
    
    provider.current = new WebsocketProvider(
      wsUrl,
      `document:${document.id}`,
      ydoc.current,
      {
        connect: false,
      }
    );

    provider.current.on('status', (event: { status: string }) => {
      console.log('WebSocket status:', event.status);
      if (event.status === 'connected') {
        setIsConnected(true);
        setIsConnecting(false);
        toast.success('Connected to collaboration server');
      } else if (event.status === 'disconnected') {
        setIsConnected(false);
        toast.error('Disconnected from collaboration server');
      }
    });

    provider.current.on('connection-error', (error: Error) => {
      console.error('WebSocket connection error:', error);
      setIsConnecting(false);
      setIsConnected(false);
      toast.error('Failed to connect to collaboration server');
    });

    // Custom message handling for presence and user updates
    provider.current.ws?.addEventListener('message', (event) => {
      try {
        const data = JSON.parse(event.data);
        
        if (data.type === 'active-users') {
          setCollaborators(data.users);
        } else if (data.type === 'user-joined') {
          setCollaborators(prev => [...prev, { userId: data.userId, userName: data.userName }]);
          toast.info(`${data.userName} joined the document`);
        } else if (data.type === 'user-left') {
          setCollaborators(prev => prev.filter(c => c.userId !== data.userId));
          toast.info(`${data.userName} left the document`);
        }
      } catch (error) {
        // Ignore non-JSON messages
      }
    });

    // Connect to WebSocket
    provider.current.connect();

    return () => {
      if (provider.current) {
        provider.current.disconnect();
        provider.current.destroy();
      }
      if (ydoc.current) {
        ydoc.current.destroy();
      }
    };
  }, [document.id, document.is_collaborative]);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        history: false, // Disable built-in history to use Yjs history
      }),
      Placeholder.configure({
        placeholder: 'Start writing your document...',
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-blue-600 underline cursor-pointer',
        },
      }),
      Image.configure({
        HTMLAttributes: {
          class: 'rounded-lg max-w-full h-auto',
        },
      }),
      TaskList,
      TaskItem.configure({
        nested: true,
      }),
      Highlight.configure({
        multicolor: true,
      }),
      Underline,
      Table.configure({
        resizable: true,
        HTMLAttributes: {
          class: 'border-collapse border border-gray-300 my-4',
        },
      }),
      TableRow.configure({
        HTMLAttributes: {
          class: 'border border-gray-300',
        },
      }),
      TableCell.configure({
        HTMLAttributes: {
          class: 'border border-gray-300 p-2 min-w-24',
        },
      }),
      TableHeader.configure({
        HTMLAttributes: {
          class: 'border border-gray-300 p-2 bg-gray-50 font-semibold',
        },
      }),
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      Color.configure({ types: [TextStyle.name, 'listItem'] }),
      TextStyle,
      FontFamily.configure({
        types: ['textStyle'],
      }),
      Focus.configure({
        className: 'has-focus',
        mode: 'all',
      }),
      Dropcursor,
      Gapcursor,
      // Yjs collaboration extensions
      ...(document.is_collaborative && ydoc.current ? [
        ySyncPlugin(ydoc.current.getText('content')),
        yCursorPlugin(provider.current?.awareness, {
          cursorBuilder: (user: any) => {
            const cursor = document.createElement('span');
            cursor.classList.add('collaboration-cursor');
            cursor.setAttribute('style', `border-color: ${user.color}`);
            return cursor;
          },
          selectionBuilder: (user: any) => {
            const selection = document.createElement('div');
            selection.classList.add('collaboration-selection');
            selection.setAttribute('style', `background-color: ${user.color}20`);
            return selection;
          },
        }),
        yUndoPlugin(),
      ] : []),
    ],
    content: document.content,
    editable: document.can_edit && viewMode === 'edit',
    onUpdate: ({ editor }) => {
      // Auto-save after 2 seconds of inactivity
      if (saveTimeoutRef.current) {
        clearTimeout(saveTimeoutRef.current);
      }
      
      saveTimeoutRef.current = setTimeout(() => {
        handleAutoSave(editor.getHTML());
      }, 2000);

      // Update page count estimation
      updatePageCount();
      
      // Update table of contents
      updateTableOfContents();
    },
    onCreate: ({ editor }) => {
      // Load initial document state if available
      if (document.yjs_state && document.yjs_state.length > 0) {
        const state = new Uint8Array(document.yjs_state);
        Y.applyUpdate(ydoc.current!, state);
      }
      updatePageCount();
      updateTableOfContents();
    },
  }, [document.is_collaborative, document.can_edit, viewMode]);

  // Initialize document metadata
  useEffect(() => {
    if (document.metadata?.page_settings) {
      setPageSettings(document.metadata.page_settings);
    }
    if (document.metadata?.header) {
      setHeaderText(document.metadata.header);
    }
    if (document.metadata?.footer) {
      setFooterText(document.metadata.footer);
    }
    if (document.metadata?.page_numbers !== undefined) {
      setShowPageNumbers(document.metadata.page_numbers);
    }
  }, [document.metadata]);

  const updatePageCount = useCallback(() => {
    if (!editorRef.current) return;
    
    // Estimate page count based on content height
    const contentHeight = editorRef.current.scrollHeight;
    const pageHeight = 842; // A4 page height in pixels at 96 DPI
    const estimated = Math.max(1, Math.ceil(contentHeight / pageHeight));
    setTotalPages(estimated);
  }, []);

  const updateTableOfContents = useCallback(() => {
    if (!editor) return;
    
    const headings: Array<{id: string, level: number, text: string}> = [];
    const doc = editor.state.doc;
    
    doc.descendants((node, pos) => {
      if (node.type.name === 'heading' && node.textContent) {
        const level = node.attrs.level;
        const text = node.textContent;
        const id = `heading-${pos}`;
        headings.push({ id, level, text });
      }
    });
    
    setTocItems(headings);
  }, [editor]);

  const handleAutoSave = useCallback(async (content: string) => {
    if (!document.can_edit || isSaving) return;
    
    try {
      setIsSaving(true);
      const metadata = {
        page_settings: pageSettings,
        header: headerText,
        footer: footerText,
        page_numbers: showPageNumbers,
      };

      await apiService.put(`/api/v1/documents/${document.id}`, {
        content,
        title,
        metadata,
      });
      setLastSaved(new Date());
      onSave?.(content, metadata);
    } catch (error) {
      console.error('Auto-save failed:', error);
      toast.error('Failed to auto-save document');
    } finally {
      setIsSaving(false);
    }
  }, [document.id, document.can_edit, title, isSaving, onSave, pageSettings, headerText, footerText, showPageNumbers]);

  const handleManualSave = useCallback(async () => {
    if (!editor || !document.can_edit) return;
    
    const content = editor.getHTML();
    await handleAutoSave(content);
    toast.success('Document saved');
  }, [editor, document.can_edit, handleAutoSave]);

  const handleTitleChange = useCallback((newTitle: string) => {
    setTitle(newTitle);
    onTitleChange?.(newTitle);
  }, [onTitleChange]);

  const setLink = useCallback(() => {
    const previousUrl = editor?.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    if (url === null) {
      return;
    }

    if (url === '') {
      editor?.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }

    editor?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }, [editor]);

  const addImage = useCallback(() => {
    const url = window.prompt('Image URL');

    if (url) {
      editor?.chain().focus().setImage({ src: url }).run();
    }
  }, [editor]);

  const insertTable = useCallback(() => {
    editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
  }, [editor]);

  const setTextColor = useCallback((color: string) => {
    editor?.chain().focus().setColor(color).run();
  }, [editor]);

  const setFontFamily = useCallback((fontFamily: string) => {
    editor?.chain().focus().setFontFamily(fontFamily).run();
  }, [editor]);

  const setFontSize = useCallback((size: number) => {
    editor?.chain().focus().setFontSize(`${size}px`).run();
  }, [editor]);

  const toggleViewMode = useCallback((mode: 'edit' | 'preview' | 'print') => {
    setViewMode(mode);
    if (editor) {
      editor.setEditable(mode === 'edit' && document.can_edit);
    }
  }, [editor, document.can_edit]);

  const zoomIn = useCallback(() => {
    setZoom(prev => Math.min(200, prev + 10));
  }, []);

  const zoomOut = useCallback(() => {
    setZoom(prev => Math.max(50, prev - 10));
  }, []);

  if (!editor) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
          <p>Loading enhanced editor...</p>
        </div>
      </div>
    );
  }

  return (
    <TooltipProvider>
      <div className={cn("enhanced-collaborative-editor border rounded-lg bg-gray-50", className)}>
        {/* Main Header */}
        <div className="border-b bg-white p-4">
          <div className="flex items-center justify-between">
            <div className="flex-1">
              <input
                type="text"
                value={title}
                onChange={(e) => handleTitleChange(e.target.value)}
                className="text-2xl font-bold bg-transparent border-none outline-none w-full"
                placeholder="Untitled Document"
                disabled={!document.can_edit}
              />
            </div>
            <div className="flex items-center space-x-2">
              {document.is_collaborative && (
                <>
                  <div className="flex items-center space-x-2 text-sm text-muted-foreground">
                    <div className={cn("w-2 h-2 rounded-full", {
                      "bg-green-500": isConnected,
                      "bg-yellow-500": isConnecting,
                      "bg-red-500": !isConnected && !isConnecting,
                    })} />
                    {isConnected ? "Connected" : isConnecting ? "Connecting..." : "Disconnected"}
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowCollaborators(true)}
                  >
                    <Users className="w-4 h-4 mr-2" />
                    {collaborators.length + 1}
                  </Button>
                </>
              )}
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowHistory(true)}
              >
                <History className="w-4 h-4 mr-2" />
                History
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowShare(true)}
              >
                <Settings className="w-4 h-4 mr-2" />
                Share
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleManualSave}
                disabled={isSaving || !document.can_edit}
              >
                <Save className="w-4 h-4 mr-2" />
                {isSaving ? "Saving..." : "Save"}
              </Button>
            </div>
          </div>
          {lastSaved && (
            <p className="text-xs text-muted-foreground mt-2">
              Last saved: {lastSaved.toLocaleTimeString()}
            </p>
          )}
        </div>

        {/* View Mode & Zoom Controls */}
        <div className="border-b bg-white px-4 py-2">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="flex items-center space-x-1">
                <Button
                  variant={viewMode === 'edit' ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => toggleViewMode('edit')}
                  disabled={!document.can_edit}
                >
                  <FileText className="w-4 h-4 mr-1" />
                  Edit
                </Button>
                <Button
                  variant={viewMode === 'preview' ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => toggleViewMode('preview')}
                >
                  <Eye className="w-4 h-4 mr-1" />
                  Preview
                </Button>
                <Button
                  variant={viewMode === 'print' ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => toggleViewMode('print')}
                >
                  <Printer className="w-4 h-4 mr-1" />
                  Print Layout
                </Button>
              </div>
              
              <Separator orientation="vertical" className="h-6" />
              
              <Toggle
                pressed={showTOC}
                onPressedChange={setShowTOC}
                size="sm"
              >
                <BookOpen className="w-4 h-4 mr-1" />
                TOC
              </Toggle>
              
              <Toggle
                pressed={showRuler}
                onPressedChange={setShowRuler}
                size="sm"
              >
                <Ruler className="w-4 h-4 mr-1" />
                Ruler
              </Toggle>
            </div>

            <div className="flex items-center space-x-2">
              <Button
                variant="ghost"
                size="sm"
                onClick={zoomOut}
                disabled={zoom <= 50}
              >
                <ZoomOut className="w-4 h-4" />
              </Button>
              <span className="text-sm min-w-12 text-center">{zoom}%</span>
              <Button
                variant="ghost"
                size="sm"
                onClick={zoomIn}
                disabled={zoom >= 200}
              >
                <ZoomIn className="w-4 h-4" />
              </Button>
              
              <Separator orientation="vertical" className="h-6" />
              
              <span className="text-sm text-muted-foreground">
                Page {currentPage} of {totalPages}
              </span>
            </div>
          </div>
        </div>

        {/* Enhanced Toolbar */}
        {document.can_edit && viewMode === 'edit' && (
          <div className="border-b bg-white p-2">
            {/* First Row - Basic Formatting */}
            <div className="flex items-center space-x-1 flex-wrap mb-2">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => undo(editor.state, editor.dispatch)}
                    disabled={!editor.can().undo()}
                  >
                    <Undo className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Undo</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => redo(editor.state, editor.dispatch)}
                    disabled={!editor.can().redo()}
                  >
                    <Redo className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Redo</TooltipContent>
              </Tooltip>

              <Separator orientation="vertical" className="mx-2 h-6" />

              {/* Font Family Selector */}
              <Select
                value={editor.getAttributes('textStyle').fontFamily || 'Arial'}
                onValueChange={setFontFamily}
              >
                <SelectTrigger className="w-32">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {fontFamilies.map(font => (
                    <SelectItem key={font.value} value={font.value}>
                      <span style={{ fontFamily: font.value }}>{font.label}</span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              {/* Font Size Selector */}
              <Select
                value={editor.getAttributes('textStyle').fontSize?.replace('px', '') || '12'}
                onValueChange={(size) => setFontSize(parseInt(size))}
              >
                <SelectTrigger className="w-16">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {fontSizes.map(size => (
                    <SelectItem key={size} value={size.toString()}>
                      {size}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Separator orientation="vertical" className="mx-2 h-6" />

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleBold().run()}
                    className={editor.isActive('bold') ? 'bg-muted' : ''}
                  >
                    <Bold className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Bold</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                    className={editor.isActive('italic') ? 'bg-muted' : ''}
                  >
                    <Italic className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Italic</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleUnderline().run()}
                    className={editor.isActive('underline') ? 'bg-muted' : ''}
                  >
                    <UnderlineIcon className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Underline</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleStrike().run()}
                    className={editor.isActive('strike') ? 'bg-muted' : ''}
                  >
                    <Strikethrough className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Strikethrough</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHighlight().run()}
                    className={editor.isActive('highlight') ? 'bg-muted' : ''}
                  >
                    <Highlighter className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Highlight</TooltipContent>
              </Tooltip>

              {/* Text Color */}
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="ghost" size="sm">
                    <Palette className="w-4 h-4" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-64">
                  <div className="grid grid-cols-8 gap-1">
                    {[
                      '#000000', '#FF0000', '#00FF00', '#0000FF',
                      '#FFFF00', '#FF00FF', '#00FFFF', '#FFA500',
                      '#800080', '#008000', '#800000', '#808000',
                      '#008080', '#000080', '#808080', '#C0C0C0'
                    ].map(color => (
                      <button
                        key={color}
                        className="w-6 h-6 rounded border"
                        style={{ backgroundColor: color }}
                        onClick={() => setTextColor(color)}
                      />
                    ))}
                  </div>
                </PopoverContent>
              </Popover>
            </div>

            {/* Second Row - Structure & Alignment */}
            <div className="flex items-center space-x-1 flex-wrap">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
                    className={editor.isActive('heading', { level: 1 }) ? 'bg-muted' : ''}
                  >
                    <Heading1 className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 1</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                    className={editor.isActive('heading', { level: 2 }) ? 'bg-muted' : ''}
                  >
                    <Heading2 className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 2</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                    className={editor.isActive('heading', { level: 3 }) ? 'bg-muted' : ''}
                  >
                    <Heading3 className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 3</TooltipContent>
              </Tooltip>

              <Separator orientation="vertical" className="mx-2 h-6" />

              {/* Text Alignment */}
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().setTextAlign('left').run()}
                    className={editor.isActive({ textAlign: 'left' }) ? 'bg-muted' : ''}
                  >
                    <AlignLeft className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Align Left</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().setTextAlign('center').run()}
                    className={editor.isActive({ textAlign: 'center' }) ? 'bg-muted' : ''}
                  >
                    <AlignCenter className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Align Center</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().setTextAlign('right').run()}
                    className={editor.isActive({ textAlign: 'right' }) ? 'bg-muted' : ''}
                  >
                    <AlignRight className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Align Right</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().setTextAlign('justify').run()}
                    className={editor.isActive({ textAlign: 'justify' }) ? 'bg-muted' : ''}
                  >
                    <AlignJustify className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Justify</TooltipContent>
              </Tooltip>

              <Separator orientation="vertical" className="mx-2 h-6" />

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
                    className={editor.isActive('heading', { level: 1 }) ? 'bg-muted' : ''}
                  >
                    <Heading1 className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 1</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                    className={editor.isActive('heading', { level: 2 }) ? 'bg-muted' : ''}
                  >
                    <Heading2 className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 2</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                    className={editor.isActive('heading', { level: 3 }) ? 'bg-muted' : ''}
                  >
                    <Heading3 className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Heading 3</TooltipContent>
              </Tooltip>

              <Separator orientation="vertical" className="mx-2 h-6" />

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleBulletList().run()}
                    className={editor.isActive('bulletList') ? 'bg-muted' : ''}
                  >
                    <List className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Bullet List</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleOrderedList().run()}
                    className={editor.isActive('orderedList') ? 'bg-muted' : ''}
                  >
                    <ListOrdered className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Numbered List</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editor.chain().focus().toggleBlockquote().run()}
                    className={editor.isActive('blockquote') ? 'bg-muted' : ''}
                  >
                    <Quote className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Blockquote</TooltipContent>
              </Tooltip>

              <Separator orientation="vertical" className="mx-2 h-6" />

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={setLink}
                    className={editor.isActive('link') ? 'bg-muted' : ''}
                  >
                    <LinkIcon className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Add Link</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={insertTable}
                  >
                    <TableIcon className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Insert Table</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={addImage}
                  >
                    <ImageIcon className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Add Image</TooltipContent>
              </Tooltip>

              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      const previousUrl = editor?.getAttributes('link').href;
                      const url = window.prompt('URL', previousUrl);
                      if (url === null) return;
                      if (url === '') {
                        editor?.chain().focus().extendMarkRange('link').unsetLink().run();
                        return;
                      }
                      editor?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
                    }}
                    className={editor.isActive('link') ? 'bg-muted' : ''}
                  >
                    <LinkIcon className="w-4 h-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Add Link</TooltipContent>
              </Tooltip>
            </div>
          </div>
        )}

        {/* Ruler */}
        {showRuler && (
          <div className="bg-white border-b px-4 py-1">
            <div className="max-w-4xl mx-auto relative">
              {/* Ruler with margin indicators */}
              <div className="h-6 bg-gray-100 border border-gray-300 relative">
                <div className="absolute inset-0 flex items-center">
                  {Array.from({ length: 20 }, (_, i) => (
                    <div
                      key={i}
                      className="flex-1 border-r border-gray-300 h-full relative"
                    >
                      {i % 5 === 0 && (
                        <span className="absolute -bottom-4 left-0 text-xs text-gray-500">
                          {i}
                        </span>
                      )}
                    </div>
                  ))}
                </div>
                {/* Margin indicators */}
                <div 
                  className="absolute top-0 bottom-0 w-1 bg-blue-500 opacity-60"
                  style={{ left: `${(pageSettings.margins.left / 21) * 100}%` }}
                />
                <div 
                  className="absolute top-0 bottom-0 w-1 bg-blue-500 opacity-60"
                  style={{ right: `${(pageSettings.margins.right / 21) * 100}%` }}
                />
              </div>
            </div>
          </div>
        )}

        {/* Main Content Area */}
        <div className="flex">
          {/* Table of Contents Sidebar */}
          {showTOC && (
            <div className="w-64 border-r bg-white max-h-96 overflow-y-auto">
              <div className="p-4">
                <h3 className="font-semibold text-sm mb-3">Table of Contents</h3>
                <div className="space-y-1">
                  {tocItems.length > 0 ? (
                    tocItems.map((item, index) => (
                      <div
                        key={`${item.id}-${index}`}
                        className="text-sm cursor-pointer hover:bg-gray-100 p-1 rounded"
                        style={{ paddingLeft: `${(item.level - 1) * 12}px` }}
                        onClick={() => {
                          // Scroll to heading (simplified implementation)
                          const element = document.querySelector(`[data-heading-id="${item.id}"]`);
                          if (element) {
                            element.scrollIntoView({ behavior: 'smooth' });
                          }
                        }}
                      >
                        <span className={cn(
                          item.level === 1 && "font-medium text-gray-900",
                          item.level === 2 && "text-gray-700",
                          item.level >= 3 && "text-gray-500"
                        )}>
                          {item.text}
                        </span>
                      </div>
                    ))
                  ) : (
                    <div className="text-sm text-gray-600">
                      No headings found. Add headings to see table of contents.
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Document Editor */}
          <div 
            className="flex-1 bg-gray-100 p-8 overflow-auto"
            style={{ zoom: `${zoom}%` }}
          >
            <div 
              className={cn(
                "bg-white shadow-lg mx-auto relative",
                viewMode === 'print' ? 'print-layout' : '',
                {
                  'max-w-[21cm]': pageSettings.size === 'A4' && pageSettings.orientation === 'portrait',
                  'max-w-[29.7cm]': pageSettings.size === 'A4' && pageSettings.orientation === 'landscape',
                  'max-w-[8.5in]': pageSettings.size === 'Letter' && pageSettings.orientation === 'portrait',
                  'max-w-[11in]': pageSettings.size === 'Letter' && pageSettings.orientation === 'landscape',
                }
              )}
              style={{
                minHeight: pageSettings.size === 'A4' ? 
                  (pageSettings.orientation === 'portrait' ? '29.7cm' : '21cm') :
                  (pageSettings.orientation === 'portrait' ? '11in' : '8.5in'),
                paddingTop: `${pageSettings.margins.top}cm`,
                paddingBottom: `${pageSettings.margins.bottom + 2}cm`, // Extra space for footer
                paddingLeft: `${pageSettings.margins.left}cm`,
                paddingRight: `${pageSettings.margins.right}cm`,
              }}
            >
              {/* Header */}
              {headerText && (
                <div className="absolute top-4 left-0 right-0 text-center text-sm text-gray-600 font-medium px-8">
                  {headerText}
                </div>
              )}

              {/* Editor Content */}
              <div ref={editorRef}>
                <EditorContent 
                  editor={editor} 
                  className={cn(
                    "prose prose-sm sm:prose lg:prose-lg xl:prose-xl max-w-none focus:outline-none",
                    viewMode === 'preview' && 'pointer-events-none',
                  )}
                />
              </div>

              {/* Footer */}
              {(footerText || showPageNumbers) && (
                <div className="absolute bottom-4 left-0 right-0 flex justify-between items-center text-sm text-gray-600 px-8">
                  <span>{footerText}</span>
                  {showPageNumbers && <span>Page {currentPage} of {totalPages}</span>}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Page Layout Controls Panel */}
        <div className="border-t bg-white p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <div className="flex items-center space-x-2">
                <Label htmlFor="page-size" className="text-sm">Page Size:</Label>
                <Select
                  value={pageSettings.size}
                  onValueChange={(size) => setPageSettings(prev => ({ ...prev, size }))}
                >
                  <SelectTrigger id="page-size" className="w-24">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="A4">A4</SelectItem>
                    <SelectItem value="Letter">Letter</SelectItem>
                    <SelectItem value="Legal">Legal</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <div className="flex items-center space-x-2">
                <Label htmlFor="orientation" className="text-sm">Orientation:</Label>
                <Select
                  value={pageSettings.orientation}
                  onValueChange={(orientation) => setPageSettings(prev => ({ ...prev, orientation }))}
                >
                  <SelectTrigger id="orientation" className="w-24">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="portrait">Portrait</SelectItem>
                    <SelectItem value="landscape">Landscape</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="flex items-center space-x-4">
              <div className="flex items-center space-x-2">
                <Label htmlFor="header" className="text-sm">Header:</Label>
                <Input
                  id="header"
                  value={headerText}
                  onChange={(e) => setHeaderText(e.target.value)}
                  placeholder="Document header..."
                  className="w-40"
                />
              </div>
              
              <div className="flex items-center space-x-2">
                <Label htmlFor="footer" className="text-sm">Footer:</Label>
                <Input
                  id="footer"
                  value={footerText}
                  onChange={(e) => setFooterText(e.target.value)}
                  placeholder="Document footer..."
                  className="w-40"
                />
              </div>
              
              <div className="flex items-center space-x-2">
                <input
                  id="page-numbers"
                  type="checkbox"
                  checked={showPageNumbers}
                  onChange={(e) => setShowPageNumbers(e.target.checked)}
                  className="rounded"
                />
                <Label htmlFor="page-numbers" className="text-sm">Page Numbers</Label>
              </div>
            </div>
          </div>
        </div>

        {/* Dialogs */}
        <CollaboratorsList
          documentId={document.id}
          isOpen={showCollaborators}
          onClose={() => setShowCollaborators(false)}
        />

        <DocumentVersionHistory
          documentId={document.id}
          isOpen={showHistory}
          onClose={() => setShowHistory(false)}
          onRestore={(content) => {
            editor?.commands.setContent(content);
            toast.success('Document restored from history');
          }}
        />

        <ShareDocumentDialog
          document={document}
          isOpen={showShare}
          onClose={() => setShowShare(false)}
        />
      </div>

      <style jsx global>{`
        .collaboration-cursor {
          position: relative;
          margin-left: -1px;
          margin-right: -1px;
          border-left: 1px solid;
          border-right: 1px solid;
          word-break: normal;
          pointer-events: none;
        }

        .collaboration-selection {
          border-radius: 3px;
          pointer-events: none;
        }

        .ProseMirror {
          outline: none !important;
        }

        .ProseMirror p.is-editor-empty:first-child::before {
          color: #adb5bd;
          content: attr(data-placeholder);
          float: left;
          height: 0;
          pointer-events: none;
        }
      `}</style>
    </TooltipProvider>
  );
}