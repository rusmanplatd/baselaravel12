import React, { useState, useEffect, useRef } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { 
  Upload, 
  FolderPlus, 
  Search, 
  Grid3X3, 
  List, 
  Filter, 
  Download,
  Share2,
  Trash2,
  Copy,
  Move,
  Eye,
  MoreHorizontal
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import apiService from '@/services/ApiService';
import FileList from './FileList';
import FolderTree from './FolderTree';
import FileUpload from './FileUpload';
import CreateFolderDialog from './CreateFolderDialog';
import FilePreview from './FilePreview';
import FileContextMenu from './FileContextMenu';

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

interface FileManagerProps {
  className?: string;
}

export default function FileManager({ className = '' }: FileManagerProps) {
  const [files, setFiles] = useState<File[]>([]);
  const [folders, setFolders] = useState<Folder[]>([]);
  const [currentFolder, setCurrentFolder] = useState<Folder | null>(null);
  const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set());
  const [searchQuery, setSearchQuery] = useState('');
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [isLoading, setIsLoading] = useState(false);
  const [showUpload, setShowUpload] = useState(false);
  const [showCreateFolder, setShowCreateFolder] = useState(false);
  const [previewFile, setPreviewFile] = useState<File | null>(null);
  const [contextMenu, setContextMenu] = useState<{ x: number; y: number; item: File | Folder } | null>(null);
  const [sortBy, setSortBy] = useState<'name' | 'created_at' | 'size'>('name');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
  const [filterType, setFilterType] = useState<'all' | 'images' | 'videos' | 'documents'>('all');

  const fileInputRef = useRef<HTMLInputElement>(null);
  const { toast } = useToast();

  useEffect(() => {
    loadData();
  }, [currentFolder, searchQuery, sortBy, sortOrder, filterType]);

  const loadData = async () => {
    setIsLoading(true);
    try {
      const params = new URLSearchParams({
        folder_id: currentFolder?.id || 'root',
        sort_by: sortBy,
        sort_order: sortOrder,
        per_page: '50'
      });

      if (searchQuery) {
        params.append('search', searchQuery);
      }

      if (filterType !== 'all') {
        params.append('type', filterType);
      }

      const [filesResponse, foldersResponse] = await Promise.all([
        apiService.get(`/api/v1/files?${params}`),
        apiService.get(`/api/v1/folders?${params}`)
      ]);

      setFiles(filesResponse.files || []);
      setFolders(foldersResponse.folders || []);
    } catch (error) {
      console.error('Failed to load files:', error);
      toast({
        title: 'Error',
        description: 'Failed to load files and folders',
        variant: 'destructive'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleFolderNavigate = (folder: Folder | null) => {
    setCurrentFolder(folder);
    setSelectedItems(new Set());
  };

  const handleFileUpload = async (uploadedFiles: FileList) => {
    try {
      const uploads = Array.from(uploadedFiles).map(async (file) => {
        const formData = new FormData();
        formData.append('file', file);
        if (currentFolder) {
          formData.append('folder_id', currentFolder.id);
        }

        return apiService.postFormData('/api/v1/files', formData);
      });

      await Promise.all(uploads);
      
      toast({
        title: 'Success',
        description: `Uploaded ${uploadedFiles.length} file(s) successfully`
      });
      
      loadData();
    } catch (error) {
      console.error('Upload failed:', error);
      toast({
        title: 'Error',
        description: 'Failed to upload files',
        variant: 'destructive'
      });
    }
  };

  const handleCreateFolder = async (name: string, description?: string, color?: string) => {
    try {
      await apiService.post('/api/v1/folders', {
        name,
        description,
        color,
        parent_id: currentFolder?.id || null
      });

      toast({
        title: 'Success',
        description: 'Folder created successfully'
      });

      loadData();
    } catch (error) {
      console.error('Failed to create folder:', error);
      toast({
        title: 'Error',
        description: 'Failed to create folder',
        variant: 'destructive'
      });
    }
  };

  const handleItemSelect = (itemId: string, selected: boolean) => {
    const newSelection = new Set(selectedItems);
    if (selected) {
      newSelection.add(itemId);
    } else {
      newSelection.delete(itemId);
    }
    setSelectedItems(newSelection);
  };

  const handleSelectAll = () => {
    if (selectedItems.size === files.length + folders.length) {
      setSelectedItems(new Set());
    } else {
      const allIds = [...files.map(f => `file:${f.id}`), ...folders.map(f => `folder:${f.id}`)];
      setSelectedItems(new Set(allIds));
    }
  };

  const handleDownload = async (file: File) => {
    try {
      const response = await fetch(`/api/v1/files/${file.id}/download`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.original_name;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
      }
    } catch (error) {
      console.error('Download failed:', error);
      toast({
        title: 'Error',
        description: 'Failed to download file',
        variant: 'destructive'
      });
    }
  };

  const handleDelete = async (item: File | Folder, type: 'file' | 'folder') => {
    try {
      const endpoint = type === 'file' ? `/api/v1/files/${item.id}` : `/api/v1/folders/${item.id}`;
      await apiService.delete(endpoint);

      toast({
        title: 'Success',
        description: `${type === 'file' ? 'File' : 'Folder'} deleted successfully`
      });

      loadData();
    } catch (error) {
      console.error('Delete failed:', error);
      toast({
        title: 'Error',
        description: `Failed to delete ${type}`,
        variant: 'destructive'
      });
    }
  };

  const handleContextMenu = (event: React.MouseEvent, item: File | Folder) => {
    event.preventDefault();
    setContextMenu({
      x: event.clientX,
      y: event.clientY,
      item
    });
  };

  const breadcrumbs = currentFolder ? currentFolder.path.split('/') : [];

  return (
    <div className={`flex h-full ${className}`}>
      {/* Sidebar - Folder Tree */}
      <div className="w-64 border-r bg-muted/50">
        <div className="p-4 border-b">
          <h2 className="font-semibold">Folders</h2>
        </div>
        <FolderTree 
          onFolderSelect={handleFolderNavigate}
          currentFolder={currentFolder}
          className="h-full overflow-auto"
        />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col">
        {/* Toolbar */}
        <div className="border-b p-4">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center space-x-2">
              <Button onClick={() => setShowUpload(true)} size="sm">
                <Upload className="w-4 h-4 mr-2" />
                Upload
              </Button>
              <Button onClick={() => setShowCreateFolder(true)} variant="outline" size="sm">
                <FolderPlus className="w-4 h-4 mr-2" />
                New Folder
              </Button>
              {selectedItems.size > 0 && (
                <>
                  <Button variant="outline" size="sm">
                    <Download className="w-4 h-4 mr-2" />
                    Download ({selectedItems.size})
                  </Button>
                  <Button variant="outline" size="sm">
                    <Share2 className="w-4 h-4 mr-2" />
                    Share
                  </Button>
                  <Button variant="destructive" size="sm">
                    <Trash2 className="w-4 h-4 mr-2" />
                    Delete ({selectedItems.size})
                  </Button>
                </>
              )}
            </div>

            <div className="flex items-center space-x-2">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  placeholder="Search files and folders..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10 w-64"
                />
              </div>
              <Button
                variant={viewMode === 'grid' ? 'default' : 'outline'}
                size="sm"
                onClick={() => setViewMode('grid')}
              >
                <Grid3X3 className="w-4 h-4" />
              </Button>
              <Button
                variant={viewMode === 'list' ? 'default' : 'outline'}
                size="sm"
                onClick={() => setViewMode('list')}
              >
                <List className="w-4 h-4" />
              </Button>
            </div>
          </div>

          {/* Breadcrumbs */}
          <div className="flex items-center space-x-1 text-sm text-muted-foreground">
            <Button 
              variant="ghost" 
              size="sm"
              onClick={() => setCurrentFolder(null)}
              className="h-6 px-2"
            >
              My Files
            </Button>
            {breadcrumbs.map((segment, index) => (
              <React.Fragment key={index}>
                <span>/</span>
                <Button 
                  variant="ghost" 
                  size="sm"
                  className="h-6 px-2"
                >
                  {segment}
                </Button>
              </React.Fragment>
            ))}
          </div>
        </div>

        {/* Content Area */}
        <div className="flex-1 overflow-auto">
          <FileList
            files={files}
            folders={folders}
            viewMode={viewMode}
            selectedItems={selectedItems}
            onItemSelect={handleItemSelect}
            onSelectAll={handleSelectAll}
            onFolderDoubleClick={handleFolderNavigate}
            onFilePreview={setPreviewFile}
            onContextMenu={handleContextMenu}
            onDownload={handleDownload}
            isLoading={isLoading}
          />
        </div>
      </div>

      {/* Dialogs */}
      {showUpload && (
        <FileUpload
          onUpload={handleFileUpload}
          onClose={() => setShowUpload(false)}
          currentFolder={currentFolder}
        />
      )}

      {showCreateFolder && (
        <CreateFolderDialog
          onCreateFolder={handleCreateFolder}
          onClose={() => setShowCreateFolder(false)}
        />
      )}

      {previewFile && (
        <FilePreview
          file={previewFile}
          onClose={() => setPreviewFile(null)}
          onDownload={() => handleDownload(previewFile)}
        />
      )}

      {contextMenu && (
        <FileContextMenu
          x={contextMenu.x}
          y={contextMenu.y}
          item={contextMenu.item}
          onClose={() => setContextMenu(null)}
          onDownload={handleDownload}
          onDelete={handleDelete}
          onPreview={setPreviewFile}
        />
      )}

      {/* Hidden file input for drag & drop */}
      <input
        ref={fileInputRef}
        type="file"
        multiple
        className="hidden"
        onChange={(e) => {
          if (e.target.files) {
            handleFileUpload(e.target.files);
          }
        }}
      />
    </div>
  );
}