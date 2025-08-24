import React, { useState, useRef, useCallback, useEffect } from 'react';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Camera, Trash2, Upload, X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface AvatarUploadProps {
  currentAvatar?: string;
  userName: string;
  onUpload: (file: File) => Promise<void>;
  onDelete: () => Promise<void>;
  className?: string;
  size?: 'sm' | 'md' | 'lg';
  disabled?: boolean;
}

export function AvatarUpload({
  currentAvatar,
  userName,
  onUpload,
  onDelete,
  className,
  size = 'md',
  disabled = false,
}: AvatarUploadProps) {
  const [isUploading, setIsUploading] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [dragActive, setDragActive] = useState(false);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const previewUrlRef = useRef<string | null>(null);
  
  const fileInputRef = useRef<HTMLInputElement>(null);

  const sizeClasses = {
    sm: 'h-16 w-16',
    md: 'h-24 w-24',
    lg: 'h-32 w-32',
  };

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(part => part.charAt(0))
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };

  const validateFile = (file: File): string | null => {
    // Check file type
    if (!file.type.startsWith('image/')) {
      return 'Please select an image file';
    }

    // Check file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
      return 'File size must be less than 5MB';
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      return 'Only JPEG, PNG, GIF, and WebP files are allowed';
    }

    return null;
  };

  // Cleanup previous preview URL when component unmounts or changes
  const cleanupPreviewUrl = useCallback(() => {
    if (previewUrlRef.current) {
      URL.revokeObjectURL(previewUrlRef.current);
      previewUrlRef.current = null;
    }
  }, []);

  const handleFileSelect = useCallback(async (file: File) => {
    setError(null);
    
    const validationError = validateFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    // Cleanup any existing preview
    cleanupPreviewUrl();

    // Create preview
    const objectUrl = URL.createObjectURL(file);
    setPreviewUrl(objectUrl);
    previewUrlRef.current = objectUrl;

    try {
      setIsUploading(true);
      await onUpload(file);
      // Clear preview on success
      setPreviewUrl(null);
      cleanupPreviewUrl();
    } catch (error) {
      console.error('Error uploading avatar:', error);
      setError('Failed to upload avatar. Please try again.');
      // Keep preview visible on error so user can see what they tried to upload
    } finally {
      setIsUploading(false);
    }
  }, [onUpload, cleanupPreviewUrl]);

  const handleFileChange = useCallback((event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      handleFileSelect(file);
    }
    // Reset input value to allow selecting the same file again
    event.target.value = '';
  }, [handleFileSelect]);

  const handleDrop = useCallback((event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setDragActive(false);

    const file = event.dataTransfer.files[0];
    if (file) {
      handleFileSelect(file);
    }
  }, [handleFileSelect]);

  const handleDragOver = useCallback((event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setDragActive(true);
  }, []);

  const handleDragLeave = useCallback((event: React.DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setDragActive(false);
  }, []);

  const handleUploadClick = () => {
    fileInputRef.current?.click();
  };

  const handleDelete = async () => {
    if (!currentAvatar) return;

    try {
      setIsDeleting(true);
      await onDelete();
    } catch (error) {
      console.error('Error deleting avatar:', error);
      setError('Failed to delete avatar. Please try again.');
    } finally {
      setIsDeleting(false);
    }
  };

  const clearPreview = useCallback(() => {
    setPreviewUrl(null);
    setError(null);
    cleanupPreviewUrl();
  }, [cleanupPreviewUrl]);

  // Cleanup on component unmount
  useEffect(() => {
    return () => {
      cleanupPreviewUrl();
    };
  }, [cleanupPreviewUrl]);

  const displayAvatar = previewUrl || currentAvatar;

  return (
    <div className={cn('space-y-4', className)}>
      <div className="flex items-center gap-4">
        <div className="relative">
          <Avatar className={cn(
            sizeClasses[size], 
            'border-2 transition-colors',
            previewUrl ? 'border-primary' : 'border-border'
          )}>
            <AvatarImage src={displayAvatar} alt={userName} />
            <AvatarFallback className="bg-muted text-muted-foreground">
              {getInitials(userName)}
            </AvatarFallback>
          </Avatar>
          
          {previewUrl && !isUploading && (
            <div className="absolute -top-1 -right-1 h-4 w-4 bg-primary rounded-full flex items-center justify-center">
              <div className="h-2 w-2 bg-primary-foreground rounded-full" />
            </div>
          )}
          
          {isUploading && (
            <div className="absolute inset-0 flex items-center justify-center bg-black/50 rounded-full">
              <Upload className="h-4 w-4 text-white animate-spin" />
            </div>
          )}
        </div>

        <div className="flex-1 space-y-2">
          <div className="flex items-center gap-2">
            <Button
              onClick={handleUploadClick}
              disabled={disabled || isUploading || isDeleting}
              size="sm"
              variant="outline"
            >
              <Camera className="h-4 w-4 mr-2" />
              {isUploading ? 'Uploading...' : previewUrl ? 'Try Again' : 'Change Avatar'}
            </Button>

            {currentAvatar && !previewUrl && (
              <Button
                onClick={handleDelete}
                disabled={disabled || isUploading || isDeleting}
                size="sm"
                variant="outline"
              >
                <Trash2 className="h-4 w-4 mr-2" />
                {isDeleting ? 'Deleting...' : 'Remove'}
              </Button>
            )}

            {previewUrl && (
              <Button
                onClick={clearPreview}
                disabled={disabled || isUploading || isDeleting}
                size="sm"
                variant="outline"
              >
                <X className="h-4 w-4 mr-2" />
                Cancel
              </Button>
            )}
          </div>

          <p className="text-sm text-muted-foreground">
            {previewUrl 
              ? 'Preview ready. Click "Try Again" to upload or "Cancel" to discard.'
              : 'Upload a photo up to 5MB. JPG, PNG, GIF, or WebP formats supported.'
            }
          </p>
        </div>
      </div>

      {/* Drag and drop area - hidden when preview is active */}
      {!previewUrl && (
        <Card 
          className={cn(
            'border-2 border-dashed transition-colors cursor-pointer',
            dragActive ? 'border-primary bg-primary/5' : 'border-border',
            disabled && 'cursor-not-allowed opacity-50'
          )}
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onClick={!disabled ? handleUploadClick : undefined}
        >
          <CardContent className="py-8 text-center">
            <Upload className={cn(
              'h-8 w-8 mx-auto mb-2',
              dragActive ? 'text-primary' : 'text-muted-foreground'
            )} />
            <p className="text-sm font-medium">
              {dragActive ? 'Drop your image here' : 'Drag and drop or click to upload'}
            </p>
          </CardContent>
        </Card>
      )}

      {/* Hidden file input */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        onChange={handleFileChange}
        className="hidden"
        disabled={disabled || isUploading || isDeleting}
      />

      {/* Error message */}
      {error && (
        <div className="flex items-center gap-2 p-3 text-sm text-destructive-foreground bg-destructive/10 border border-destructive/20 rounded-md">
          <X className="h-4 w-4" />
          {error}
        </div>
      )}
    </div>
  );
}