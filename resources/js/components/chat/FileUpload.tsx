import React, { useState, useRef, useCallback, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { 
    Upload, 
    X, 
    File as FileIcon, 
    Image, 
    Video, 
    Music,
    FileText,
    Archive,
    Paperclip,
    AlertCircle,
    CheckCircle,
    Loader2
} from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

interface FileUploadProps {
    conversationId: string;
    deviceId?: string;
    onUploadComplete?: (results: any) => void;
    onUploadStart?: () => void;
    maxFiles?: number;
    maxFileSize?: number; // in MB
    acceptedTypes?: string[];
    className?: string;
    compact?: boolean;
}

interface UploadingFile {
    file: File;
    id: string;
    progress: number;
    status: 'pending' | 'uploading' | 'completed' | 'error';
    error?: string;
    result?: any;
}

const DEFAULT_ACCEPTED_TYPES = [
    // Images
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    // Documents
    'application/pdf', 'text/plain', 'text/markdown',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // Audio
    'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac', 'audio/mpeg',
    // Video
    'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/avi',
    // Archives
    'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
];

export function FileUpload({
    conversationId,
    deviceId,
    onUploadComplete,
    onUploadStart,
    maxFiles = 10,
    maxFileSize = 50, // 50MB default
    acceptedTypes = DEFAULT_ACCEPTED_TYPES,
    className,
    compact = false
}: FileUploadProps) {
    const [isDragOver, setIsDragOver] = useState(false);
    const [uploadingFiles, setUploadingFiles] = useState<UploadingFile[]>([]);
    const [isUploading, setIsUploading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const dragCounterRef = useRef(0);

    const getFileIcon = (type: string) => {
        if (type.startsWith('image/')) return <Image className="h-4 w-4 text-blue-500" />;
        if (type.startsWith('video/')) return <Video className="h-4 w-4 text-red-500" />;
        if (type.startsWith('audio/')) return <Music className="h-4 w-4 text-purple-500" />;
        if (type.includes('pdf') || type.includes('document') || type.includes('text')) {
            return <FileText className="h-4 w-4 text-green-500" />;
        }
        if (type.includes('zip') || type.includes('rar') || type.includes('7z')) {
            return <Archive className="h-4 w-4 text-yellow-500" />;
        }
        return <FileIcon className="h-4 w-4 text-gray-500" />;
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const validateFile = (file: File): string | null => {
        // Check file size
        if (file.size > maxFileSize * 1024 * 1024) {
            return `File size exceeds ${maxFileSize}MB limit`;
        }

        // Check file type
        if (!acceptedTypes.includes(file.type)) {
            return `File type ${file.type} is not supported`;
        }

        return null;
    };

    const handleFiles = useCallback(async (files: FileList) => {
        const fileArray = Array.from(files);
        
        // Validate total file count
        if (uploadingFiles.length + fileArray.length > maxFiles) {
            toast.error(`Maximum ${maxFiles} files allowed`);
            return;
        }

        // Validate and prepare files
        const validFiles: UploadingFile[] = [];
        const errors: string[] = [];

        for (const file of fileArray) {
            const error = validateFile(file);
            if (error) {
                errors.push(`${file.name}: ${error}`);
            } else {
                validFiles.push({
                    file,
                    id: Math.random().toString(36).substr(2, 9),
                    progress: 0,
                    status: 'pending'
                });
            }
        }

        // Show validation errors
        if (errors.length > 0) {
            toast.error(`Some files were rejected:\n${errors.join('\n')}`);
        }

        if (validFiles.length === 0) {
            return;
        }

        // Add files to upload queue
        setUploadingFiles(prev => [...prev, ...validFiles]);
        setIsUploading(true);
        onUploadStart?.();

        // Start uploading files
        await uploadFiles(validFiles);
    }, [uploadingFiles.length, maxFiles, maxFileSize, acceptedTypes, onUploadStart]);

    const uploadFiles = async (files: UploadingFile[]) => {
        const { e2eeFileService } = await import('@/services/E2EEFileService');
        const results: any[] = [];

        for (const uploadFile of files) {
            try {
                // Update status
                setUploadingFiles(prev => prev.map(f => 
                    f.id === uploadFile.id 
                        ? { ...f, status: 'uploading', progress: 0 }
                        : f
                ));

                // Simulate progress updates (real implementation would track actual progress)
                const progressInterval = setInterval(() => {
                    setUploadingFiles(prev => prev.map(f => 
                        f.id === uploadFile.id && f.progress < 90
                            ? { ...f, progress: f.progress + 10 }
                            : f
                    ));
                }, 200);

                // Encrypt and upload file
                const encryptedFileData = await e2eeFileService.encryptFile(
                    uploadFile.file,
                    conversationId,
                    deviceId || 'unknown-device',
                    {
                        generateThumbnail: uploadFile.file.type.startsWith('image/')
                    }
                );

                const result = await e2eeFileService.uploadEncryptedFile(
                    conversationId,
                    encryptedFileData
                );

                clearInterval(progressInterval);

                // Mark as completed
                setUploadingFiles(prev => prev.map(f => 
                    f.id === uploadFile.id 
                        ? { ...f, status: 'completed', progress: 100, result }
                        : f
                ));

                results.push(result);
                toast.success(`${uploadFile.file.name} uploaded successfully`);

            } catch (error) {
                console.error(`Upload failed for ${uploadFile.file.name}:`, error);
                
                setUploadingFiles(prev => prev.map(f => 
                    f.id === uploadFile.id 
                        ? { 
                            ...f, 
                            status: 'error', 
                            error: error instanceof Error ? error.message : 'Upload failed' 
                        }
                        : f
                ));

                toast.error(`Failed to upload ${uploadFile.file.name}`);
            }
        }

        setIsUploading(false);
        onUploadComplete?.(results);

        // Clear completed files after a delay
        setTimeout(() => {
            setUploadingFiles(prev => prev.filter(f => f.status === 'error'));
        }, 3000);
    };

    const removeFile = (fileId: string) => {
        setUploadingFiles(prev => prev.filter(f => f.id !== fileId));
    };

    const clearAll = () => {
        setUploadingFiles([]);
    };

    // Drag and drop handlers
    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current++;
        setIsDragOver(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current--;
        if (dragCounterRef.current === 0) {
            setIsDragOver(false);
        }
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current = 0;
        setIsDragOver(false);

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFiles(files);
        }
    }, [handleFiles]);

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        console.log('File input changed!', e.target.files);
        const files = e.target.files;
        if (files && files.length > 0) {
            console.log('Processing files:', Array.from(files).map(f => ({ name: f.name, size: f.size, type: f.type })));
            handleFiles(files);
        }
        // Reset input to allow selecting the same file again
        e.target.value = '';
    };

    if (compact) {
        return (
            <div className={cn("relative", className)}>
                <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleFileInputChange}
                    multiple
                    accept={acceptedTypes.join(',')}
                    className="hidden"
                />
                
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        console.log('Paperclip button clicked!', { fileInputRef: fileInputRef.current, isUploading });
                        fileInputRef.current?.click();
                    }}
                    disabled={isUploading}
                    className="h-8 w-8 p-1 flex items-center justify-center"
                    title="Upload files"
                >
                    {isUploading ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                        <Paperclip className="h-4 w-4" />
                    )}
                </Button>

                {/* Upload progress overlay */}
                {uploadingFiles.length > 0 && (
                    <div className="absolute top-full left-0 z-50 mt-2 w-64 max-h-48 overflow-y-auto bg-background border border-border rounded-lg shadow-lg p-2">
                        {uploadingFiles.map((file) => (
                            <div key={file.id} className="flex items-center gap-2 p-2 text-xs">
                                {getFileIcon(file.file.type)}
                                <span className="flex-1 truncate">{file.file.name}</span>
                                {file.status === 'uploading' && (
                                    <Loader2 className="h-3 w-3 animate-spin" />
                                )}
                                {file.status === 'completed' && (
                                    <CheckCircle className="h-3 w-3 text-green-500" />
                                )}
                                {file.status === 'error' && (
                                    <AlertCircle className="h-3 w-3 text-red-500" />
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className={cn("space-y-4", className)}>
            {/* Drop Zone */}
            <div
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
                className={cn(
                    "relative border-2 border-dashed border-border rounded-lg p-6 transition-colors",
                    isDragOver && "border-primary bg-primary/5",
                    isUploading && "pointer-events-none opacity-60"
                )}
            >
                <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleFileInputChange}
                    multiple
                    accept={acceptedTypes.join(',')}
                    className="hidden"
                />

                <div className="text-center">
                    <Upload className={cn(
                        "mx-auto h-12 w-12 mb-4 transition-colors",
                        isDragOver ? "text-primary" : "text-muted-foreground"
                    )} />
                    
                    <h3 className="font-medium text-lg mb-2">
                        {isDragOver ? "Drop files here" : "Upload Files"}
                    </h3>
                    
                    <p className="text-muted-foreground mb-4">
                        Drag and drop files here, or{" "}
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="text-primary hover:underline"
                        >
                            browse
                        </button>
                    </p>

                    <div className="flex flex-wrap gap-2 justify-center text-xs text-muted-foreground">
                        <Badge variant="secondary">Max {maxFileSize}MB</Badge>
                        <Badge variant="secondary">Up to {maxFiles} files</Badge>
                        <Badge variant="secondary">Images, documents, audio, video</Badge>
                    </div>
                </div>
            </div>

            {/* File List */}
            {uploadingFiles.length > 0 && (
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h4 className="font-medium">
                                Uploading Files ({uploadingFiles.length})
                            </h4>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearAll}
                                disabled={isUploading}
                            >
                                Clear All
                            </Button>
                        </div>

                        <div className="space-y-3">
                            {uploadingFiles.map((file) => (
                                <div key={file.id} className="space-y-2">
                                    <div className="flex items-center gap-3">
                                        {getFileIcon(file.file.type)}
                                        
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate">
                                                {file.file.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatFileSize(file.file.size)}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {file.status === 'uploading' && (
                                                <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
                                            )}
                                            {file.status === 'completed' && (
                                                <CheckCircle className="h-4 w-4 text-green-500" />
                                            )}
                                            {file.status === 'error' && (
                                                <AlertCircle className="h-4 w-4 text-red-500" />
                                            )}
                                            
                                            {file.status !== 'uploading' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeFile(file.id)}
                                                    className="h-6 w-6 p-0"
                                                >
                                                    <X className="h-3 w-3" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Progress Bar */}
                                    {(file.status === 'uploading' || file.status === 'pending') && (
                                        <Progress value={file.progress} className="h-1" />
                                    )}

                                    {/* Error Message */}
                                    {file.status === 'error' && file.error && (
                                        <p className="text-xs text-red-600">{file.error}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}