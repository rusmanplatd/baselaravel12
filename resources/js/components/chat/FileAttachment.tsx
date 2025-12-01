import React, { useState, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { 
    Download, 
    FileText, 
    Image, 
    Video, 
    Music, 
    Archive, 
    File as FileIcon,
    Eye,
    ExternalLink,
    Loader2
} from 'lucide-react';
import { toast } from 'sonner';
import { e2eeFileService } from '@/services/E2EEFileService';

interface FileAttachmentProps {
    file: {
        id: string;
        original_filename: string;
        mime_type: string;
        file_size: number;
        encrypted_size: number;
        file_type: 'image' | 'video' | 'audio' | 'document' | 'archive' | 'file';
        has_thumbnail: boolean;
        supports_preview: boolean;
    };
    conversationId: string;
    deviceId: string;
    className?: string;
    showPreview?: boolean;
}

export function FileAttachment({ 
    file, 
    conversationId, 
    deviceId, 
    className = '', 
    showPreview = true 
}: FileAttachmentProps) {
    const [isDownloading, setIsDownloading] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [previewContent, setPreviewContent] = useState<{
        blob: Blob;
        url: string;
        type: string;
    } | null>(null);

    const getFileIcon = () => {
        switch (file.file_type) {
            case 'image':
                return <Image className="h-8 w-8 text-blue-500" />;
            case 'video':
                return <Video className="h-8 w-8 text-red-500" />;
            case 'audio':
                return <Music className="h-8 w-8 text-purple-500" />;
            case 'document':
                return <FileText className="h-8 w-8 text-green-500" />;
            case 'archive':
                return <Archive className="h-8 w-8 text-yellow-500" />;
            default:
                return <FileIcon className="h-8 w-8 text-gray-500" />;
        }
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getFileExtension = () => {
        const parts = file.original_filename.split('.');
        return parts.length > 1 ? parts[parts.length - 1].toUpperCase() : 'FILE';
    };

    const handleDownload = useCallback(async () => {
        setIsDownloading(true);
        
        try {
            const { blob, filename, mimeType } = await e2eeFileService.downloadAndDecryptFile(
                conversationId,
                file.id,
                deviceId
            );

            // Create download link
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            
            // Cleanup
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            toast.success(`${filename} downloaded successfully`);
            
        } catch (error) {
            console.error('File download failed:', error);
            toast.error('Failed to download file');
        } finally {
            setIsDownloading(false);
        }
    }, [conversationId, file.id, deviceId]);

    const handlePreview = useCallback(async () => {
        if (!file.supports_preview) {
            toast.error('Preview not supported for this file type');
            return;
        }

        try {
            const { blob, filename, mimeType } = await e2eeFileService.downloadAndDecryptFile(
                conversationId,
                file.id,
                deviceId
            );

            const url = URL.createObjectURL(blob);
            
            setPreviewContent({
                blob,
                url,
                type: mimeType
            });
            setPreviewOpen(true);

        } catch (error) {
            console.error('File preview failed:', error);
            toast.error('Failed to preview file');
        }
    }, [conversationId, file.id, deviceId, file.supports_preview]);

    const closePreview = () => {
        if (previewContent) {
            URL.revokeObjectURL(previewContent.url);
            setPreviewContent(null);
        }
        setPreviewOpen(false);
    };

    return (
        <>
            <div className={`flex items-center gap-3 p-3 border border-border rounded-lg bg-background hover:bg-accent/50 transition-colors ${className}`}>
                {/* File Icon */}
                <div className="flex-shrink-0">
                    {getFileIcon()}
                </div>

                {/* File Info */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                        <p className="font-medium text-sm truncate" title={file.original_filename}>
                            {file.original_filename}
                        </p>
                        <Badge variant="secondary" className="text-xs">
                            {getFileExtension()}
                        </Badge>
                    </div>
                    
                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                        <span>{formatFileSize(file.file_size)}</span>
                        <span>•</span>
                        <span className="capitalize">{file.file_type}</span>
                        {file.has_thumbnail && (
                            <>
                                <span>•</span>
                                <Badge variant="outline" className="text-xs">
                                    Has Preview
                                </Badge>
                            </>
                        )}
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex items-center gap-1 flex-shrink-0">
                    {showPreview && file.supports_preview && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handlePreview}
                            className="h-8 w-8 p-0"
                            title="Preview file"
                        >
                            <Eye className="h-4 w-4" />
                        </Button>
                    )}
                    
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleDownload}
                        disabled={isDownloading}
                        className="h-8 w-8 p-0"
                        title="Download file"
                    >
                        {isDownloading ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Download className="h-4 w-4" />
                        )}
                    </Button>
                </div>
            </div>

            {/* Preview Dialog */}
            <Dialog open={previewOpen} onOpenChange={(open) => !open && closePreview()}>
                <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {getFileIcon()}
                            <span className="truncate">{file.original_filename}</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    if (previewContent) {
                                        window.open(previewContent.url, '_blank');
                                    }
                                }}
                                className="ml-auto"
                            >
                                <ExternalLink className="h-4 w-4" />
                            </Button>
                        </DialogTitle>
                    </DialogHeader>

                    <div className="overflow-auto max-h-[70vh]">
                        {previewContent && (
                            <div className="flex items-center justify-center">
                                {previewContent.type.startsWith('image/') && (
                                    <img 
                                        src={previewContent.url} 
                                        alt={file.original_filename}
                                        className="max-w-full max-h-[60vh] object-contain rounded-lg"
                                    />
                                )}
                                
                                {previewContent.type.startsWith('video/') && (
                                    <video 
                                        src={previewContent.url} 
                                        controls 
                                        className="max-w-full max-h-[60vh] rounded-lg"
                                    >
                                        Your browser doesn't support video playback.
                                    </video>
                                )}
                                
                                {previewContent.type.startsWith('audio/') && (
                                    <div className="w-full max-w-md">
                                        <audio 
                                            src={previewContent.url} 
                                            controls 
                                            className="w-full"
                                        >
                                            Your browser doesn't support audio playback.
                                        </audio>
                                    </div>
                                )}
                                
                                {previewContent.type === 'application/pdf' && (
                                    <iframe 
                                        src={previewContent.url}
                                        className="w-full h-[60vh] border-0 rounded-lg"
                                        title={file.original_filename}
                                    />
                                )}
                                
                                {previewContent.type === 'text/plain' && (
                                    <div className="w-full max-h-[60vh] overflow-auto">
                                        <pre className="whitespace-pre-wrap p-4 bg-muted rounded-lg text-sm">
                                            {/* Text content would be loaded here */}
                                            <TextFilePreview url={previewContent.url} />
                                        </pre>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

// Helper component for text file preview
function TextFilePreview({ url }: { url: string }) {
    const [content, setContent] = React.useState<string>('Loading...');

    React.useEffect(() => {
        fetch(url)
            .then(response => response.text())
            .then(text => setContent(text))
            .catch(() => setContent('Failed to load text content'));
    }, [url]);

    return <>{content}</>;
}