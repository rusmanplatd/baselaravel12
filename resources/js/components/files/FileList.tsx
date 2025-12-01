import React from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { 
  FileText, 
  Image, 
  Video, 
  Music, 
  Archive,
  Download,
  Share2,
  Trash2,
  Copy,
  Move,
  Eye,
  MoreHorizontal,
  Folder,
  File as FileIcon,
  Calendar,
  HardDrive
} from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

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

interface FileListProps {
  files: File[];
  folders: Folder[];
  viewMode: 'grid' | 'list';
  selectedItems: Set<string>;
  onItemSelect: (itemId: string, selected: boolean) => void;
  onSelectAll: () => void;
  onFolderDoubleClick: (folder: Folder) => void;
  onFilePreview: (file: File) => void;
  onContextMenu: (event: React.MouseEvent, item: File | Folder) => void;
  onDownload: (file: File) => void;
  isLoading: boolean;
}

const getFileIcon = (file: File) => {
  if (file.is_image) return <Image className="w-4 h-4 text-blue-500" />;
  if (file.is_video) return <Video className="w-4 h-4 text-purple-500" />;
  if (file.extension === 'pdf') return <FileText className="w-4 h-4 text-red-500" />;
  if (['mp3', 'wav', 'flac'].includes(file.extension)) return <Music className="w-4 h-4 text-green-500" />;
  if (['zip', 'rar', '7z'].includes(file.extension)) return <Archive className="w-4 h-4 text-yellow-500" />;
  return <FileIcon className="w-4 h-4 text-gray-500" />;
};

const getVisibilityColor = (visibility: string) => {
  switch (visibility) {
    case 'public': return 'text-green-600';
    case 'internal': return 'text-blue-600';
    case 'private': return 'text-gray-600';
    default: return 'text-gray-600';
  }
};

export default function FileList({
  files,
  folders,
  viewMode,
  selectedItems,
  onItemSelect,
  onSelectAll,
  onFolderDoubleClick,
  onFilePreview,
  onContextMenu,
  onDownload,
  isLoading
}: FileListProps) {
  const allItems = [
    ...folders.map(f => ({ ...f, type: 'folder' as const })),
    ...files.map(f => ({ ...f, type: 'file' as const }))
  ];

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (allItems.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center h-64 text-muted-foreground">
        <Folder className="w-12 h-12 mb-4 opacity-50" />
        <p>No files or folders found</p>
        <p className="text-sm">Upload files or create folders to get started</p>
      </div>
    );
  }

  if (viewMode === 'list') {
    return (
      <div className="p-4">
        {/* Header */}
        <div className="grid grid-cols-12 gap-4 mb-2 pb-2 border-b text-sm font-medium text-muted-foreground">
          <div className="col-span-1">
            <Checkbox
              checked={selectedItems.size === allItems.length && allItems.length > 0}
              onCheckedChange={() => onSelectAll()}
              aria-label="Select all items"
            />
          </div>
          <div className="col-span-4">Name</div>
          <div className="col-span-2">Modified</div>
          <div className="col-span-2">Size</div>
          <div className="col-span-2">Visibility</div>
          <div className="col-span-1">Actions</div>
        </div>

        {/* Items */}
        <div className="space-y-1">
          {allItems.map((item) => {
            const itemId = `${item.type}:${item.id}`;
            const isSelected = selectedItems.has(itemId);
            
            return (
              <div
                key={itemId}
                className={`grid grid-cols-12 gap-4 py-2 px-2 rounded hover:bg-muted/50 cursor-pointer ${
                  isSelected ? 'bg-muted' : ''
                }`}
                onContextMenu={(e) => onContextMenu(e, item)}
                onDoubleClick={() => {
                  if (item.type === 'folder') {
                    onFolderDoubleClick(item);
                  } else {
                    onFilePreview(item);
                  }
                }}
              >
                <div className="col-span-1">
                  <Checkbox
                    checked={isSelected}
                    onCheckedChange={(checked) => onItemSelect(itemId, !!checked)}
                    aria-label={`Select ${item.name}`}
                  />
                </div>
                
                <div className="col-span-4 flex items-center space-x-3 min-w-0">
                  <div className="flex-shrink-0">
                    {item.type === 'folder' ? (
                      <Folder 
                        className="w-5 h-5 text-blue-500" 
                        style={{ color: item.color || undefined }}
                      />
                    ) : (
                      <>
                        {item.thumbnail_url ? (
                          <img
                            src={item.thumbnail_url}
                            alt={item.name}
                            className="w-8 h-8 object-cover rounded"
                          />
                        ) : (
                          getFileIcon(item)
                        )}
                      </>
                    )}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{item.name}</p>
                    {item.type === 'folder' && (
                      <p className="text-xs text-muted-foreground">
                        {item.file_count} files, {item.folder_count} folders
                      </p>
                    )}
                  </div>
                </div>
                
                <div className="col-span-2 text-sm text-muted-foreground">
                  {formatDistanceToNow(new Date(item.created_at), { addSuffix: true })}
                </div>
                
                <div className="col-span-2 text-sm text-muted-foreground">
                  {item.type === 'folder' ? (
                    <div className="flex items-center space-x-1">
                      <HardDrive className="w-3 h-3" />
                      <span>{item.total_size ? `${Math.round(item.total_size / (1024 * 1024))} MB` : '--'}</span>
                    </div>
                  ) : (
                    item.human_size
                  )}
                </div>
                
                <div className={`col-span-2 text-sm capitalize ${getVisibilityColor(item.visibility)}`}>
                  {item.visibility}
                </div>
                
                <div className="col-span-1">
                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    <MoreHorizontal className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  // Grid view
  return (
    <div className="p-4">
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
        {allItems.map((item) => {
          const itemId = `${item.type}:${item.id}`;
          const isSelected = selectedItems.has(itemId);
          
          return (
            <Card
              key={itemId}
              className={`p-3 cursor-pointer transition-colors hover:bg-muted/50 ${
                isSelected ? 'ring-2 ring-primary bg-muted' : ''
              }`}
              onContextMenu={(e) => onContextMenu(e, item)}
              onClick={() => onItemSelect(itemId, !isSelected)}
              onDoubleClick={() => {
                if (item.type === 'folder') {
                  onFolderDoubleClick(item);
                } else {
                  onFilePreview(item);
                }
              }}
            >
              <div className="flex flex-col items-center space-y-2">
                {/* Icon/Thumbnail */}
                <div className="w-12 h-12 flex items-center justify-center">
                  {item.type === 'folder' ? (
                    <Folder 
                      className="w-10 h-10 text-blue-500" 
                      style={{ color: item.color || undefined }}
                    />
                  ) : (
                    <>
                      {item.thumbnail_url ? (
                        <img
                          src={item.thumbnail_url}
                          alt={item.name}
                          className="w-12 h-12 object-cover rounded"
                        />
                      ) : (
                        <div className="w-10 h-10 flex items-center justify-center">
                          {getFileIcon(item)}
                        </div>
                      )}
                    </>
                  )}
                </div>

                {/* Name */}
                <div className="text-center w-full">
                  <p className="text-xs font-medium truncate" title={item.name}>
                    {item.name}
                  </p>
                  {item.type === 'folder' && (
                    <p className="text-xs text-muted-foreground">
                      {item.file_count + item.folder_count} items
                    </p>
                  )}
                </div>

                {/* Quick actions */}
                <div className="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  {item.type === 'file' && (
                    <>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0"
                        onClick={(e) => {
                          e.stopPropagation();
                          onFilePreview(item);
                        }}
                      >
                        <Eye className="w-3 h-3" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0"
                        onClick={(e) => {
                          e.stopPropagation();
                          onDownload(item);
                        }}
                      >
                        <Download className="w-3 h-3" />
                      </Button>
                    </>
                  )}
                </div>

                {/* Selection indicator */}
                {isSelected && (
                  <div className="absolute top-1 right-1 w-4 h-4 bg-primary rounded-full flex items-center justify-center">
                    <div className="w-2 h-2 bg-white rounded-full"></div>
                  </div>
                )}

                {/* Visibility indicator */}
                <div className={`absolute bottom-1 right-1 w-2 h-2 rounded-full ${
                  item.visibility === 'public' ? 'bg-green-500' :
                  item.visibility === 'internal' ? 'bg-blue-500' : 'bg-gray-500'
                }`} title={`Visibility: ${item.visibility}`}></div>
              </div>
            </Card>
          );
        })}
      </div>
    </div>
  );
}