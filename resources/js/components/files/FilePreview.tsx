import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { 
  Download, 
  Share2, 
  Copy, 
  Move,
  Trash2,
  X,
  FileText,
  Image,
  Video,
  Music,
  Archive,
  File as FileIcon,
  Calendar,
  HardDrive,
  Eye,
  Lock,
  Users,
  Globe,
  ExternalLink
} from 'lucide-react';
import { formatBytes } from '@/utils/format';
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
  description?: string;
  download_count?: number;
  metadata?: {
    width?: number;
    height?: number;
    duration?: number;
    [key: string]: any;
  };
}

interface FilePreviewProps {
  file: File;
  onClose: () => void;
  onDownload: () => void;
  onShare?: () => void;
  onCopy?: () => void;
  onMove?: () => void;
  onDelete?: () => void;
}

const getFileIcon = (file: File, size: string = 'w-8 h-8') => {
  if (file.is_image) return <Image className={`${size} text-blue-500`} />;
  if (file.is_video) return <Video className={`${size} text-purple-500`} />;
  if (file.extension === 'pdf') return <FileText className={`${size} text-red-500`} />;
  if (['mp3', 'wav', 'flac'].includes(file.extension)) return <Music className={`${size} text-green-500`} />;
  if (['zip', 'rar', '7z'].includes(file.extension)) return <Archive className={`${size} text-yellow-500`} />;
  return <FileIcon className={`${size} text-gray-500`} />;
};

const getVisibilityIcon = (visibility: string) => {
  switch (visibility) {
    case 'public': return <Globe className="w-4 h-4 text-green-600" />;
    case 'internal': return <Users className="w-4 h-4 text-blue-600" />;
    case 'private': return <Lock className="w-4 h-4 text-gray-600" />;
    default: return <Lock className="w-4 h-4 text-gray-600" />;
  }
};

const PreviewContent = ({ file }: { file: File }) => {
  const [imageError, setImageError] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  // Image preview
  if (file.is_image && !imageError) {
    return (
      <div className="flex items-center justify-center bg-black/5 rounded-lg min-h-[300px]">
        <img
          src={file.url}
          alt={file.name}
          className="max-w-full max-h-[500px] object-contain rounded"
          onLoad={() => setIsLoading(false)}
          onError={() => {
            setImageError(true);
            setIsLoading(false);
          }}
          style={{ display: isLoading ? 'none' : 'block' }}
        />
        {isLoading && (
          <div className="flex items-center justify-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
          </div>
        )}
      </div>
    );
  }

  // Video preview
  if (file.is_video) {
    return (
      <div className="flex items-center justify-center bg-black/5 rounded-lg min-h-[300px]">
        <video
          src={file.url}
          controls
          className="max-w-full max-h-[500px] rounded"
          preload="metadata"
        >
          Your browser does not support the video tag.
        </video>
      </div>
    );
  }

  // PDF preview (if supported)
  if (file.extension === 'pdf') {
    return (
      <div className="flex items-center justify-center bg-black/5 rounded-lg min-h-[300px]">
        <div className="text-center space-y-4">
          <FileText className="w-16 h-16 mx-auto text-red-500" />
          <div>
            <p className="font-medium">PDF Document</p>
            <p className="text-sm text-muted-foreground">Click download to view the full document</p>
          </div>
          <Button variant="outline" onClick={() => window.open(file.url, '_blank')}>
            <ExternalLink className="w-4 h-4 mr-2" />
            Open in new tab
          </Button>
        </div>
      </div>
    );
  }

  // Audio preview
  if (['mp3', 'wav', 'flac', 'aac', 'ogg'].includes(file.extension)) {
    return (
      <div className="flex items-center justify-center bg-black/5 rounded-lg min-h-[300px]">
        <div className="text-center space-y-4">
          <Music className="w-16 h-16 mx-auto text-green-500" />
          <div>
            <p className="font-medium">Audio File</p>
            <p className="text-sm text-muted-foreground">{file.name}</p>
          </div>
          <audio
            src={file.url}
            controls
            className="w-full max-w-md"
            preload="metadata"
          >
            Your browser does not support the audio tag.
          </audio>
        </div>
      </div>
    );
  }

  // Text preview for text files
  if (['txt', 'md', 'json', 'xml', 'csv'].includes(file.extension)) {
    return (
      <div className="flex items-center justify-center bg-black/5 rounded-lg min-h-[300px]">
        <div className="text-center space-y-4">
          <FileText className="w-16 h-16 mx-auto text-blue-500" />
          <div>
            <p className="font-medium">Text Document</p>
            <p className="text-sm text-muted-foreground">{file.name}</p>
          </div>
          <Button variant="outline" onClick={() => window.open(file.url, '_blank')}>
            <ExternalLink className="w-4 h-4 mr-2" />
            Open in new tab
          </Button>
        </div>
      </div>
    );
  }

  // Default preview
  return (
    <div className="flex items-center justify-center bg-black/5 rounded-lg min-h-[300px]">
      <div className="text-center space-y-4">
        {getFileIcon(file, 'w-16 h-16')}
        <div>
          <p className="font-medium">No preview available</p>
          <p className="text-sm text-muted-foreground">
            {file.extension.toUpperCase()} file • {file.human_size}
          </p>
        </div>
      </div>
    </div>
  );
};

export default function FilePreview({
  file,
  onClose,
  onDownload,
  onShare,
  onCopy,
  onMove,
  onDelete
}: FilePreviewProps) {
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose();
    }
  };

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] flex flex-col" onKeyDown={handleKeyDown}>
        <DialogHeader className="flex-shrink-0">
          <DialogTitle className="flex items-center space-x-3">
            <div className="flex items-center space-x-2 flex-1 min-w-0">
              {getFileIcon(file)}
              <span className="truncate">{file.name}</span>
            </div>
            <div className="flex items-center space-x-2">
              {getVisibilityIcon(file.visibility)}
              <Badge variant="outline" className="capitalize">
                {file.visibility}
              </Badge>
            </div>
          </DialogTitle>
        </DialogHeader>

        {/* Preview Area */}
        <div className="flex-1 overflow-auto">
          <PreviewContent file={file} />
        </div>

        {/* File Information */}
        <div className="flex-shrink-0 space-y-4 pt-4">
          <Separator />
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div className="space-y-3">
              <div>
                <label className="font-medium text-muted-foreground">Original Name</label>
                <p className="break-all">{file.original_name}</p>
              </div>
              
              {file.description && (
                <div>
                  <label className="font-medium text-muted-foreground">Description</label>
                  <p>{file.description}</p>
                </div>
              )}
              
              <div className="flex items-center space-x-4">
                <div>
                  <label className="font-medium text-muted-foreground">Size</label>
                  <div className="flex items-center space-x-1">
                    <HardDrive className="w-3 h-3" />
                    <span>{file.human_size}</span>
                  </div>
                </div>
                
                <div>
                  <label className="font-medium text-muted-foreground">Type</label>
                  <p>{file.extension.toUpperCase()}</p>
                </div>
              </div>
            </div>
            
            <div className="space-y-3">
              <div>
                <label className="font-medium text-muted-foreground">Created</label>
                <div className="flex items-center space-x-1">
                  <Calendar className="w-3 h-3" />
                  <span>{formatDistanceToNow(new Date(file.created_at), { addSuffix: true })}</span>
                </div>
              </div>
              
              <div>
                <label className="font-medium text-muted-foreground">Modified</label>
                <div className="flex items-center space-x-1">
                  <Calendar className="w-3 h-3" />
                  <span>{formatDistanceToNow(new Date(file.updated_at), { addSuffix: true })}</span>
                </div>
              </div>
              
              {file.download_count !== undefined && (
                <div>
                  <label className="font-medium text-muted-foreground">Downloads</label>
                  <div className="flex items-center space-x-1">
                    <Download className="w-3 h-3" />
                    <span>{file.download_count}</span>
                  </div>
                </div>
              )}
              
              {file.metadata && (
                <div>
                  <label className="font-medium text-muted-foreground">Properties</label>
                  <div className="space-y-1">
                    {file.metadata.width && file.metadata.height && (
                      <p>{file.metadata.width} × {file.metadata.height} pixels</p>
                    )}
                    {file.metadata.duration && (
                      <p>Duration: {Math.round(file.metadata.duration)}s</p>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        <DialogFooter className="flex-shrink-0 gap-2">
          <div className="flex items-center space-x-2 flex-1">
            {onShare && (
              <Button variant="outline" size="sm" onClick={onShare}>
                <Share2 className="w-4 h-4 mr-2" />
                Share
              </Button>
            )}
            
            {onCopy && (
              <Button variant="outline" size="sm" onClick={onCopy}>
                <Copy className="w-4 h-4 mr-2" />
                Copy
              </Button>
            )}
            
            {onMove && (
              <Button variant="outline" size="sm" onClick={onMove}>
                <Move className="w-4 h-4 mr-2" />
                Move
              </Button>
            )}
            
            {onDelete && (
              <Button variant="outline" size="sm" onClick={onDelete} className="text-destructive hover:bg-destructive hover:text-destructive-foreground">
                <Trash2 className="w-4 h-4 mr-2" />
                Delete
              </Button>
            )}
          </div>

          <Button variant="outline" onClick={onClose}>
            <X className="w-4 h-4 mr-2" />
            Close
          </Button>
          
          <Button onClick={onDownload}>
            <Download className="w-4 h-4 mr-2" />
            Download
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}