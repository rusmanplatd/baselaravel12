import React, { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { 
  Eye,
  Download,
  Share2,
  Copy,
  Move,
  Trash2,
  Edit,
  Info,
  Lock,
  Users,
  Globe,
  FolderOpen,
  FileText,
  Star,
  Archive
} from 'lucide-react';

interface File {
  id: string;
  name: string;
  original_name: string;
  size: number;
  mime_type: string;
  extension: string;
  created_at: string;
  updated_at: string;
  folder_id: string | null;
  is_image: boolean;
  is_video: boolean;
  is_document: boolean;
  human_size: string;
  thumbnail_url: string | null;
  url: string;
  visibility: 'private' | 'internal' | 'public';
}

interface Folder {
  id: string;
  name: string;
  parent_id: string | null;
  level: number;
  path: string;
  file_count: number;
  folder_count: number;
  total_size: number;
  color: string | null;
  visibility: 'private' | 'internal' | 'public';
  created_at: string;
}

interface FileContextMenuProps {
  x: number;
  y: number;
  item: File | Folder;
  onClose: () => void;
  onPreview?: (file: File) => void;
  onDownload?: (file: File) => void;
  onShare?: (item: File | Folder) => void;
  onCopy?: (item: File | Folder) => void;
  onMove?: (item: File | Folder) => void;
  onDelete?: (item: File | Folder, type: 'file' | 'folder') => void;
  onRename?: (item: File | Folder) => void;
  onProperties?: (item: File | Folder) => void;
  onOpen?: (folder: Folder) => void;
}

const isFile = (item: File | Folder): item is File => {
  return 'mime_type' in item;
};

const getVisibilityInfo = (visibility: string) => {
  switch (visibility) {
    case 'public':
      return { icon: Globe, label: 'Public', description: 'Anyone can access' };
    case 'internal':
      return { icon: Users, label: 'Internal', description: 'Organization members' };
    case 'private':
      return { icon: Lock, label: 'Private', description: 'Only you' };
    default:
      return { icon: Lock, label: 'Private', description: 'Only you' };
  }
};

export default function FileContextMenu({
  x,
  y,
  item,
  onClose,
  onPreview,
  onDownload,
  onShare,
  onCopy,
  onMove,
  onDelete,
  onRename,
  onProperties,
  onOpen
}: FileContextMenuProps) {
  const menuRef = useRef<HTMLDivElement>(null);
  const file = isFile(item) ? item : null;
  const folder = !isFile(item) ? item : null;
  const visibilityInfo = getVisibilityInfo(item.visibility);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        onClose();
      }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [onClose]);

  // Position the menu to avoid going off screen
  const menuStyle = {
    left: x,
    top: y,
    position: 'fixed' as const,
    zIndex: 50,
  };

  // Adjust position if menu would go off screen
  useEffect(() => {
    if (menuRef.current) {
      const rect = menuRef.current.getBoundingClientRect();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;

      let adjustedX = x;
      let adjustedY = y;

      if (x + rect.width > viewportWidth) {
        adjustedX = viewportWidth - rect.width - 10;
      }

      if (y + rect.height > viewportHeight) {
        adjustedY = viewportHeight - rect.height - 10;
      }

      if (adjustedX !== x || adjustedY !== y) {
        menuRef.current.style.left = `${adjustedX}px`;
        menuRef.current.style.top = `${adjustedY}px`;
      }
    }
  }, [x, y]);

  const handleAction = (action: () => void) => {
    action();
    onClose();
  };

  return (
    <div
      ref={menuRef}
      className="bg-popover border rounded-lg shadow-lg py-1 min-w-[200px]"
      style={menuStyle}
    >
      {/* Item Info Header */}
      <div className="px-3 py-2 border-b">
        <div className="flex items-center space-x-2">
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium truncate">{item.name}</p>
            <div className="flex items-center space-x-1 text-xs text-muted-foreground">
              <visibilityInfo.icon className="w-3 h-3" />
              <span>{visibilityInfo.label}</span>
              {file && <span>• {file.human_size}</span>}
              {folder && <span>• {folder.file_count + folder.folder_count} items</span>}
            </div>
          </div>
        </div>
      </div>

      {/* Primary Actions */}
      <div className="py-1">
        {folder && onOpen && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onOpen(folder))}
          >
            <FolderOpen className="w-4 h-4 mr-2" />
            Open
          </Button>
        )}

        {file && onPreview && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onPreview(file))}
          >
            <Eye className="w-4 h-4 mr-2" />
            Preview
          </Button>
        )}

        {file && onDownload && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onDownload(file))}
          >
            <Download className="w-4 h-4 mr-2" />
            Download
          </Button>
        )}
      </div>

      <Separator />

      {/* File/Folder Actions */}
      <div className="py-1">
        {onShare && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onShare(item))}
          >
            <Share2 className="w-4 h-4 mr-2" />
            Share
          </Button>
        )}

        {onCopy && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onCopy(item))}
          >
            <Copy className="w-4 h-4 mr-2" />
            {file ? 'Copy file' : 'Copy folder'}
          </Button>
        )}

        {onMove && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onMove(item))}
          >
            <Move className="w-4 h-4 mr-2" />
            {file ? 'Move file' : 'Move folder'}
          </Button>
        )}

        {onRename && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onRename(item))}
          >
            <Edit className="w-4 h-4 mr-2" />
            Rename
          </Button>
        )}
      </div>

      <Separator />

      {/* Info and Management */}
      <div className="py-1">
        <Button
          variant="ghost"
          size="sm"
          className="w-full justify-start px-3 py-1 h-8 font-normal"
          onClick={() => handleAction(() => {})}
          disabled
        >
          <Star className="w-4 h-4 mr-2" />
          Add to favorites
        </Button>

        {onProperties && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal"
            onClick={() => handleAction(() => onProperties(item))}
          >
            <Info className="w-4 h-4 mr-2" />
            Properties
          </Button>
        )}
      </div>

      <Separator />

      {/* Danger Zone */}
      <div className="py-1">
        {onDelete && (
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start px-3 py-1 h-8 font-normal text-destructive hover:bg-destructive hover:text-destructive-foreground"
            onClick={() => handleAction(() => onDelete(item, file ? 'file' : 'folder'))}
          >
            <Trash2 className="w-4 h-4 mr-2" />
            {file ? 'Delete file' : 'Delete folder'}
          </Button>
        )}
      </div>

      {/* Quick Info Footer */}
      <div className="px-3 py-2 border-t bg-muted/30">
        <div className="text-xs text-muted-foreground">
          <div className="flex items-center justify-between">
            <span>Created</span>
            <span>{new Date(item.created_at).toLocaleDateString()}</span>
          </div>
          {file && (
            <div className="flex items-center justify-between mt-1">
              <span>Type</span>
              <span>{file.extension.toUpperCase()}</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}