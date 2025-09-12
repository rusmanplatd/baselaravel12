import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
  FolderPlus, 
  Palette,
  Lock,
  Users,
  Globe
} from 'lucide-react';

interface CreateFolderDialogProps {
  onCreateFolder: (name: string, description?: string, color?: string, visibility?: string) => void;
  onClose: () => void;
}

const folderColors = [
  { name: 'Blue', value: '#3b82f6', class: 'bg-blue-500' },
  { name: 'Green', value: '#10b981', class: 'bg-green-500' },
  { name: 'Purple', value: '#8b5cf6', class: 'bg-purple-500' },
  { name: 'Pink', value: '#ec4899', class: 'bg-pink-500' },
  { name: 'Red', value: '#ef4444', class: 'bg-red-500' },
  { name: 'Yellow', value: '#f59e0b', class: 'bg-yellow-500' },
  { name: 'Orange', value: '#f97316', class: 'bg-orange-500' },
  { name: 'Gray', value: '#6b7280', class: 'bg-gray-500' },
];

export default function CreateFolderDialog({ onCreateFolder, onClose }: CreateFolderDialogProps) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [selectedColor, setSelectedColor] = useState<string>('');
  const [visibility, setVisibility] = useState<string>('private');
  const [isCreating, setIsCreating] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!name.trim()) return;

    setIsCreating(true);
    
    try {
      await onCreateFolder(
        name.trim(), 
        description.trim() || undefined,
        selectedColor || undefined,
        visibility
      );
      onClose();
    } catch (error) {
      console.error('Failed to create folder:', error);
    } finally {
      setIsCreating(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose();
    }
  };

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md" onKeyDown={handleKeyDown}>
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <FolderPlus className="w-5 h-5" />
            <span>Create New Folder</span>
          </DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Folder Name */}
          <div className="space-y-2">
            <Label htmlFor="folder-name">Name *</Label>
            <Input
              id="folder-name"
              type="text"
              placeholder="Enter folder name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={255}
              autoFocus
              required
            />
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label htmlFor="folder-description">Description</Label>
            <Textarea
              id="folder-description"
              placeholder="Optional description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              maxLength={1000}
              rows={3}
              className="resize-none"
            />
          </div>

          {/* Color Selection */}
          <div className="space-y-2">
            <Label className="flex items-center space-x-2">
              <Palette className="w-4 h-4" />
              <span>Color</span>
            </Label>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => setSelectedColor('')}
                className={`w-8 h-8 rounded border-2 border-dashed border-gray-300 hover:border-gray-400 transition-colors ${
                  !selectedColor ? 'ring-2 ring-primary' : ''
                }`}
                title="Default color"
              >
                <div className="w-full h-full bg-gray-100 rounded"></div>
              </button>
              {folderColors.map((color) => (
                <button
                  key={color.value}
                  type="button"
                  onClick={() => setSelectedColor(color.value)}
                  className={`w-8 h-8 rounded ${color.class} hover:scale-110 transition-transform ${
                    selectedColor === color.value ? 'ring-2 ring-primary' : ''
                  }`}
                  title={color.name}
                />
              ))}
            </div>
          </div>

          {/* Visibility */}
          <div className="space-y-2">
            <Label>Visibility</Label>
            <Select value={visibility} onValueChange={setVisibility}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="private">
                  <div className="flex items-center space-x-2">
                    <Lock className="w-4 h-4" />
                    <div>
                      <div className="font-medium">Private</div>
                      <div className="text-xs text-muted-foreground">Only you can access</div>
                    </div>
                  </div>
                </SelectItem>
                <SelectItem value="internal">
                  <div className="flex items-center space-x-2">
                    <Users className="w-4 h-4" />
                    <div>
                      <div className="font-medium">Internal</div>
                      <div className="text-xs text-muted-foreground">Organization members can access</div>
                    </div>
                  </div>
                </SelectItem>
                <SelectItem value="public">
                  <div className="flex items-center space-x-2">
                    <Globe className="w-4 h-4" />
                    <div>
                      <div className="font-medium">Public</div>
                      <div className="text-xs text-muted-foreground">Anyone can access</div>
                    </div>
                  </div>
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Preview */}
          {name && (
            <div className="space-y-2">
              <Label>Preview</Label>
              <div className="flex items-center space-x-3 p-3 border rounded bg-muted/30">
                <div 
                  className="w-8 h-8 rounded flex items-center justify-center"
                  style={{ 
                    backgroundColor: selectedColor || '#3b82f6',
                    color: 'white'
                  }}
                >
                  <FolderPlus className="w-5 h-5" />
                </div>
                <div className="flex-1">
                  <p className="font-medium">{name}</p>
                  {description && (
                    <p className="text-sm text-muted-foreground truncate">{description}</p>
                  )}
                </div>
                <div className="flex items-center space-x-1 text-xs text-muted-foreground">
                  {visibility === 'private' && <Lock className="w-3 h-3" />}
                  {visibility === 'internal' && <Users className="w-3 h-3" />}
                  {visibility === 'public' && <Globe className="w-3 h-3" />}
                  <span className="capitalize">{visibility}</span>
                </div>
              </div>
            </div>
          )}

          <DialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button 
              type="submit" 
              disabled={!name.trim() || isCreating}
              className="min-w-[100px]"
            >
              {isCreating ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
                  Creating...
                </>
              ) : (
                <>
                  <FolderPlus className="w-4 h-4 mr-2" />
                  Create Folder
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}