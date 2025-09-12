import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { 
  Folder, 
  FolderOpen, 
  FolderPlus,
  ChevronRight,
  ChevronDown,
  Home,
  Loader2
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import apiService from '@/services/ApiService';

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
  children?: Folder[];
}

interface FolderTreeProps {
  onFolderSelect: (folder: Folder | null) => void;
  currentFolder: Folder | null;
  className?: string;
}

interface TreeNodeProps {
  folder: Folder;
  level: number;
  isSelected: boolean;
  isExpanded: boolean;
  onSelect: (folder: Folder) => void;
  onToggleExpanded: (folderId: string) => void;
  expandedFolders: Set<string>;
}

function TreeNode({
  folder,
  level,
  isSelected,
  isExpanded,
  onSelect,
  onToggleExpanded,
  expandedFolders
}: TreeNodeProps) {
  const hasChildren = folder.folder_count > 0 || (folder.children && folder.children.length > 0);
  const indentLevel = level * 16;

  return (
    <div>
      <Button
        variant={isSelected ? 'secondary' : 'ghost'}
        className={`w-full justify-start h-8 px-2 font-normal ${isSelected ? 'bg-muted' : ''}`}
        style={{ paddingLeft: `${8 + indentLevel}px` }}
        onClick={() => onSelect(folder)}
      >
        {hasChildren && (
          <div
            className="flex items-center justify-center w-4 h-4 mr-1 cursor-pointer"
            onClick={(e) => {
              e.stopPropagation();
              onToggleExpanded(folder.id);
            }}
          >
            {isExpanded ? (
              <ChevronDown className="w-3 h-3" />
            ) : (
              <ChevronRight className="w-3 h-3" />
            )}
          </div>
        )}
        
        {!hasChildren && <div className="w-4 h-4 mr-1" />}
        
        <div className="flex items-center space-x-2 flex-1 min-w-0">
          {isSelected && isExpanded ? (
            <FolderOpen 
              className="w-4 h-4 flex-shrink-0" 
              style={{ color: folder.color || undefined }}
            />
          ) : (
            <Folder 
              className="w-4 h-4 flex-shrink-0" 
              style={{ color: folder.color || undefined }}
            />
          )}
          <span className="text-sm truncate flex-1" title={folder.name}>
            {folder.name}
          </span>
          {folder.file_count + folder.folder_count > 0 && (
            <span className="text-xs text-muted-foreground flex-shrink-0">
              {folder.file_count + folder.folder_count}
            </span>
          )}
        </div>
      </Button>

      {isExpanded && folder.children && (
        <div>
          {folder.children.map((child) => (
            <TreeNode
              key={child.id}
              folder={child}
              level={level + 1}
              isSelected={false}
              isExpanded={expandedFolders.has(child.id)}
              onSelect={onSelect}
              onToggleExpanded={onToggleExpanded}
              expandedFolders={expandedFolders}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export default function FolderTree({ 
  onFolderSelect, 
  currentFolder, 
  className = '' 
}: FolderTreeProps) {
  const [folders, setFolders] = useState<Folder[]>([]);
  const [expandedFolders, setExpandedFolders] = useState<Set<string>>(new Set());
  const [isLoading, setIsLoading] = useState(false);
  const { toast } = useToast();

  useEffect(() => {
    loadFolderTree();
  }, []);

  useEffect(() => {
    if (currentFolder) {
      expandToFolder(currentFolder);
    }
  }, [currentFolder]);

  const loadFolderTree = async () => {
    setIsLoading(true);
    try {
      const response = await apiService.get('/api/v1/folders/tree?max_depth=5');
      setFolders(response.tree || []);
    } catch (error) {
      console.error('Failed to load folder tree:', error);
      toast({
        title: 'Error',
        description: 'Failed to load folder structure',
        variant: 'destructive'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const expandToFolder = (folder: Folder) => {
    if (!folder.path) return;
    
    const pathParts = folder.path.split('/');
    const newExpanded = new Set(expandedFolders);
    
    const expandPath = (currentFolders: Folder[], pathIndex: number) => {
      if (pathIndex >= pathParts.length) return;
      
      const currentPath = pathParts.slice(0, pathIndex + 1).join('/');
      const targetFolder = currentFolders.find(f => f.path === currentPath);
      
      if (targetFolder) {
        newExpanded.add(targetFolder.id);
        if (targetFolder.children) {
          expandPath(targetFolder.children, pathIndex + 1);
        }
      }
    };
    
    expandPath(folders, 0);
    setExpandedFolders(newExpanded);
  };

  const handleToggleExpanded = async (folderId: string) => {
    const newExpanded = new Set(expandedFolders);
    
    if (newExpanded.has(folderId)) {
      newExpanded.delete(folderId);
    } else {
      newExpanded.add(folderId);
      
      const folder = findFolderById(folders, folderId);
      if (folder && (!folder.children || folder.children.length === 0)) {
        await loadFolderChildren(folder);
      }
    }
    
    setExpandedFolders(newExpanded);
  };

  const loadFolderChildren = async (folder: Folder) => {
    try {
      const response = await apiService.get(`/api/v1/folders?parent_id=${folder.id}&per_page=50`);
      const children = response.folders || [];
      
      const updateFolderChildren = (folderList: Folder[]): Folder[] => {
        return folderList.map(f => {
          if (f.id === folder.id) {
            return { ...f, children };
          }
          if (f.children) {
            return { ...f, children: updateFolderChildren(f.children) };
          }
          return f;
        });
      };
      
      setFolders(updateFolderChildren(folders));
    } catch (error) {
      console.error('Failed to load folder children:', error);
      toast({
        title: 'Error',
        description: 'Failed to load subfolder contents',
        variant: 'destructive'
      });
    }
  };

  const findFolderById = (folderList: Folder[], id: string): Folder | null => {
    for (const folder of folderList) {
      if (folder.id === id) return folder;
      if (folder.children) {
        const found = findFolderById(folder.children, id);
        if (found) return found;
      }
    }
    return null;
  };

  const handleFolderSelect = (folder: Folder) => {
    onFolderSelect(folder);
  };

  const handleRootSelect = () => {
    onFolderSelect(null);
  };

  if (isLoading && folders.length === 0) {
    return (
      <div className={`${className} flex items-center justify-center p-4`}>
        <Loader2 className="w-4 h-4 animate-spin" />
        <span className="ml-2 text-sm text-muted-foreground">Loading folders...</span>
      </div>
    );
  }

  return (
    <div className={`${className} overflow-auto`}>
      <div className="p-2 space-y-1">
        {/* Root folder */}
        <Button
          variant={currentFolder === null ? 'secondary' : 'ghost'}
          className={`w-full justify-start h-8 px-2 font-normal ${currentFolder === null ? 'bg-muted' : ''}`}
          onClick={handleRootSelect}
        >
          <Home className="w-4 h-4 mr-2" />
          <span className="text-sm">My Files</span>
        </Button>

        {/* Folder tree */}
        {folders.map((folder) => (
          <TreeNode
            key={folder.id}
            folder={folder}
            level={0}
            isSelected={currentFolder?.id === folder.id}
            isExpanded={expandedFolders.has(folder.id)}
            onSelect={handleFolderSelect}
            onToggleExpanded={handleToggleExpanded}
            expandedFolders={expandedFolders}
          />
        ))}

        {folders.length === 0 && !isLoading && (
          <div className="text-center py-8 text-muted-foreground">
            <Folder className="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No folders yet</p>
            <p className="text-xs">Create your first folder to get started</p>
          </div>
        )}

        {isLoading && folders.length > 0 && (
          <div className="flex items-center justify-center py-2">
            <Loader2 className="w-3 h-3 animate-spin mr-2" />
            <span className="text-xs text-muted-foreground">Updating...</span>
          </div>
        )}
      </div>
    </div>
  );
}