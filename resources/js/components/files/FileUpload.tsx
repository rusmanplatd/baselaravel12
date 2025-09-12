import React, { useState, useCallback } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { 
  Upload, 
  X, 
  File as FileIcon, 
  Image, 
  Video, 
  FileText,
  Music,
  Archive,
  AlertCircle,
  CheckCircle2,
  Loader2
} from 'lucide-react';
import { useDropzone } from 'react-dropzone';
import { formatBytes } from '@/utils/format';

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

interface FileUploadProps {
  onUpload: (files: FileList) => Promise<void>;
  onClose: () => void;
  currentFolder?: Folder | null;
  maxFileSize?: number;
  allowedTypes?: string[];
  maxFiles?: number;
}

interface UploadFile {
  file: File;
  id: string;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
}

const getFileIcon = (file: File) => {
  const extension = file.name.split('.').pop()?.toLowerCase() || '';
  
  if (file.type.startsWith('image/')) return <Image className="w-5 h-5 text-blue-500" />;
  if (file.type.startsWith('video/')) return <Video className="w-5 h-5 text-purple-500" />;
  if (extension === 'pdf' || file.type === 'application/pdf') return <FileText className="w-5 h-5 text-red-500" />;
  if (file.type.startsWith('audio/') || ['mp3', 'wav', 'flac'].includes(extension)) return <Music className="w-5 h-5 text-green-500" />;
  if (['zip', 'rar', '7z', 'tar', 'gz'].includes(extension)) return <Archive className="w-5 h-5 text-yellow-500" />;
  
  return <FileIcon className="w-5 h-5 text-gray-500" />;
};

export default function FileUpload({
  onUpload,
  onClose,
  currentFolder,
  maxFileSize = 100 * 1024 * 1024, // 100MB
  allowedTypes,
  maxFiles = 10
}: FileUploadProps) {
  const [uploadFiles, setUploadFiles] = useState<UploadFile[]>([]);
  const [isUploading, setIsUploading] = useState(false);
  const [totalProgress, setTotalProgress] = useState(0);

  const onDrop = useCallback((acceptedFiles: File[], rejectedFiles: any[]) => {
    // Handle rejected files
    if (rejectedFiles.length > 0) {
      const errors = rejectedFiles.map(({ file, errors }) => ({
        file: file.name,
        errors: errors.map((e: any) => e.message).join(', ')
      }));
      
      console.warn('Rejected files:', errors);
    }

    // Add accepted files to upload queue
    const newUploadFiles: UploadFile[] = acceptedFiles.map(file => ({
      file,
      id: `${file.name}-${file.size}-${Date.now()}`,
      progress: 0,
      status: 'pending'
    }));

    setUploadFiles(prev => [...prev, ...newUploadFiles]);
  }, []);

  const { getRootProps, getInputProps, isDragActive, isDragReject } = useDropzone({
    onDrop,
    maxSize: maxFileSize,
    maxFiles: maxFiles - uploadFiles.length,
    accept: allowedTypes ? allowedTypes.reduce((acc, type) => {
      acc[type] = [];
      return acc;
    }, {} as Record<string, string[]>) : undefined,
    disabled: isUploading
  });

  const removeFile = (fileId: string) => {
    if (isUploading) return;
    setUploadFiles(prev => prev.filter(f => f.id !== fileId));
  };

  const clearAllFiles = () => {
    if (isUploading) return;
    setUploadFiles([]);
  };

  const startUpload = async () => {
    if (uploadFiles.length === 0 || isUploading) return;

    setIsUploading(true);
    
    try {
      // Create FileList from upload files
      const dataTransfer = new DataTransfer();
      uploadFiles.forEach(({ file }) => dataTransfer.items.add(file));
      const fileList = dataTransfer.files;

      // Simulate progress for UI (actual progress would come from upload service)
      const progressInterval = setInterval(() => {
        setTotalProgress(prev => {
          const newProgress = prev + Math.random() * 20;
          return newProgress >= 90 ? 90 : newProgress;
        });
        
        setUploadFiles(prev => prev.map(f => ({
          ...f,
          progress: f.status === 'pending' ? Math.min(90, f.progress + Math.random() * 20) : f.progress,
          status: f.status === 'pending' ? 'uploading' : f.status
        })));
      }, 500);

      await onUpload(fileList);

      clearInterval(progressInterval);
      
      // Complete the upload
      setTotalProgress(100);
      setUploadFiles(prev => prev.map(f => ({
        ...f,
        progress: 100,
        status: 'completed'
      })));

      // Close dialog after a short delay
      setTimeout(() => {
        onClose();
      }, 1000);

    } catch (error) {
      console.error('Upload failed:', error);
      
      setUploadFiles(prev => prev.map(f => ({
        ...f,
        status: 'error',
        error: error instanceof Error ? error.message : 'Upload failed'
      })));
    } finally {
      setIsUploading(false);
    }
  };

  const canUpload = uploadFiles.length > 0 && !isUploading;
  const hasErrors = uploadFiles.some(f => f.status === 'error');
  const allCompleted = uploadFiles.length > 0 && uploadFiles.every(f => f.status === 'completed');

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-2xl max-h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <Upload className="w-5 h-5" />
            <span>Upload Files</span>
            {currentFolder && (
              <span className="text-sm text-muted-foreground font-normal">
                to {currentFolder.name}
              </span>
            )}
          </DialogTitle>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto space-y-4">
          {/* Drop zone */}
          {!isUploading && !allCompleted && (
            <div
              {...getRootProps()}
              className={`
                border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors
                ${isDragActive && !isDragReject ? 'border-primary bg-primary/5' : ''}
                ${isDragReject ? 'border-destructive bg-destructive/5' : ''}
                ${!isDragActive ? 'border-muted-foreground/25 hover:border-muted-foreground/50' : ''}
              `}
            >
              <input {...getInputProps()} />
              
              <div className="space-y-3">
                <Upload className="w-12 h-12 mx-auto text-muted-foreground" />
                
                {isDragActive && !isDragReject ? (
                  <p className="text-lg font-medium">Drop files here</p>
                ) : isDragReject ? (
                  <p className="text-lg font-medium text-destructive">
                    Some files are not allowed
                  </p>
                ) : (
                  <>
                    <p className="text-lg font-medium">
                      Drag & drop files here, or click to browse
                    </p>
                    <p className="text-sm text-muted-foreground">
                      Upload up to {maxFiles} files, max {formatBytes(maxFileSize)} each
                    </p>
                  </>
                )}
                
                {allowedTypes && (
                  <p className="text-xs text-muted-foreground">
                    Allowed types: {allowedTypes.join(', ')}
                  </p>
                )}
              </div>
            </div>
          )}

          {/* Upload progress */}
          {isUploading && (
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Uploading files...</span>
                <span className="text-sm text-muted-foreground">{Math.round(totalProgress)}%</span>
              </div>
              <Progress value={totalProgress} className="w-full" />
            </div>
          )}

          {/* File list */}
          {uploadFiles.length > 0 && (
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">
                  Files ({uploadFiles.length})
                </span>
                {!isUploading && !allCompleted && (
                  <Button 
                    variant="ghost" 
                    size="sm" 
                    onClick={clearAllFiles}
                    className="h-6 px-2"
                  >
                    Clear all
                  </Button>
                )}
              </div>

              <div className="max-h-60 overflow-y-auto space-y-2">
                {uploadFiles.map(({ file, id, progress, status, error }) => (
                  <div key={id} className="flex items-center space-x-3 p-2 border rounded">
                    <div className="flex-shrink-0">
                      {getFileIcon(file)}
                    </div>

                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{file.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {formatBytes(file.size)}
                      </p>

                      {status === 'uploading' && (
                        <Progress value={progress} className="w-full h-1 mt-1" />
                      )}

                      {error && (
                        <p className="text-xs text-destructive mt-1">{error}</p>
                      )}
                    </div>

                    <div className="flex-shrink-0">
                      {status === 'completed' && (
                        <CheckCircle2 className="w-4 h-4 text-green-500" />
                      )}
                      {status === 'error' && (
                        <AlertCircle className="w-4 h-4 text-destructive" />
                      )}
                      {status === 'uploading' && (
                        <Loader2 className="w-4 h-4 animate-spin" />
                      )}
                      {status === 'pending' && !isUploading && (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => removeFile(id)}
                          className="h-6 w-6 p-0"
                        >
                          <X className="w-3 h-3" />
                        </Button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={isUploading}>
            {allCompleted ? 'Close' : 'Cancel'}
          </Button>
          {!allCompleted && (
            <Button 
              onClick={startUpload} 
              disabled={!canUpload}
              className="min-w-[100px]"
            >
              {isUploading ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Uploading...
                </>
              ) : (
                <>
                  <Upload className="w-4 h-4 mr-2" />
                  Upload {uploadFiles.length} {uploadFiles.length === 1 ? 'file' : 'files'}
                </>
              )}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}