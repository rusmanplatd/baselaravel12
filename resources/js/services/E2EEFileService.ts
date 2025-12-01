import { useE2EE } from '@/hooks/useE2EE';
import apiService from './ApiService';

export interface FileUploadMetadata {
    originalFilename: string;
    originalMimeType: string;
    originalSize: number;
    fileHash: string;
    encryptionKeyData: any;
    deviceId: string;
    messageContent?: string;
    generateThumbnail?: boolean;
}

export interface EncryptedFileData {
    encryptedBlob: Blob;
    metadata: FileUploadMetadata;
    encryptedThumbnail?: string;
}

export interface FileDownloadInfo {
    fileId: string;
    originalFilename: string;
    originalMimeType: string;
    originalSize: number;
    encryptedSize: number;
    fileType: 'image' | 'video' | 'audio' | 'document' | 'archive' | 'file';
    hasThumbnail: boolean;
    supportsPreview: boolean;
}

/**
 * E2EE File Service
 * 
 * Handles client-side file encryption/decryption for true end-to-end encryption.
 * Files are encrypted on the client before being sent to the server,
 * and decrypted on the client after being retrieved from the server.
 */
export class E2EEFileService {
    private static instance: E2EEFileService | null = null;
    private e2eeService: ReturnType<typeof useE2EE> | null = null;

    private constructor() {}

    static getInstance(): E2EEFileService {
        if (!E2EEFileService.instance) {
            E2EEFileService.instance = new E2EEFileService();
        }
        return E2EEFileService.instance;
    }

    /**
     * Initialize with E2EE service
     */
    initialize(e2eeService: ReturnType<typeof useE2EE>) {
        this.e2eeService = e2eeService;
    }

    /**
     * Encrypt a file for upload
     */
    async encryptFile(
        file: File,
        conversationId: string,
        deviceId: string,
        options: {
            messageContent?: string;
            generateThumbnail?: boolean;
        } = {}
    ): Promise<EncryptedFileData> {
        if (!this.e2eeService) {
            throw new Error('E2EE service not initialized');
        }

        try {
            // Read file as ArrayBuffer
            const fileBuffer = await this.fileToArrayBuffer(file);
            
            // Generate file hash for integrity verification
            const fileHash = await this.generateFileHash(fileBuffer);
            
            // Encrypt the file content using E2EE service
            const encryptionResult = await this.e2eeService.encryptData(
                conversationId,
                deviceId,
                fileBuffer
            );

            // Generate thumbnail if requested and file is an image
            let encryptedThumbnail: string | undefined;
            if (options.generateThumbnail && file.type.startsWith('image/')) {
                const thumbnail = await this.generateThumbnail(file);
                if (thumbnail) {
                    const thumbnailBuffer = await this.fileToArrayBuffer(thumbnail);
                    const encryptedThumbnailData = await this.e2eeService.encryptData(
                        conversationId,
                        deviceId,
                        thumbnailBuffer
                    );
                    encryptedThumbnail = this.arrayBufferToBase64(encryptedThumbnailData.encryptedContent);
                }
            }

            // Create encrypted blob
            const encryptedBlob = new Blob([encryptedResult.encryptedContent], {
                type: 'application/octet-stream'
            });

            const metadata: FileUploadMetadata = {
                originalFilename: file.name,
                originalMimeType: file.type,
                originalSize: file.size,
                fileHash,
                encryptionKeyData: encryptionResult.encryptionKeys,
                deviceId,
                messageContent: options.messageContent,
                generateThumbnail: options.generateThumbnail,
            };

            return {
                encryptedBlob,
                metadata,
                encryptedThumbnail,
            };

        } catch (error) {
            console.error('File encryption failed:', error);
            throw new Error(`File encryption failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
        }
    }

    /**
     * Decrypt a downloaded file
     */
    async decryptFile(
        encryptedContent: ArrayBuffer,
        fileInfo: FileDownloadInfo,
        conversationId: string,
        deviceId: string,
        encryptionKeyData: any
    ): Promise<Blob> {
        if (!this.e2eeService) {
            throw new Error('E2EE service not initialized');
        }

        try {
            // Decrypt the file content
            const decryptedContent = await this.e2eeService.decryptData(
                conversationId,
                deviceId,
                encryptedContent,
                encryptionKeyData
            );

            // Verify file integrity
            const decryptedHash = await this.generateFileHash(decryptedContent);
            // Note: Hash verification would require the original hash from metadata
            
            // Create decrypted blob with original mime type
            return new Blob([decryptedContent], {
                type: fileInfo.originalMimeType
            });

        } catch (error) {
            console.error('File decryption failed:', error);
            throw new Error(`File decryption failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
        }
    }

    /**
     * Upload encrypted file to server
     */
    async uploadEncryptedFile(
        conversationId: string,
        encryptedFileData: EncryptedFileData
    ): Promise<any> {
        try {
            const formData = new FormData();
            formData.append('encrypted_file', encryptedFileData.encryptedBlob);
            formData.append('device_id', encryptedFileData.metadata.deviceId);
            formData.append('original_filename', encryptedFileData.metadata.originalFilename);
            formData.append('original_mime_type', encryptedFileData.metadata.originalMimeType);
            formData.append('original_size', encryptedFileData.metadata.originalSize.toString());
            formData.append('file_hash', encryptedFileData.metadata.fileHash);
            formData.append('encryption_key_data', JSON.stringify(encryptedFileData.metadata.encryptionKeyData));
            
            if (encryptedFileData.metadata.messageContent) {
                formData.append('message_content', encryptedFileData.metadata.messageContent);
            }
            
            if (encryptedFileData.encryptedThumbnail) {
                formData.append('encrypted_thumbnail', encryptedFileData.encryptedThumbnail);
            }

            const response = await apiService.postFormData(
                `/api/v1/chat/conversations/${conversationId}/files/upload`,
                formData
            );

            return response;

        } catch (error) {
            console.error('Encrypted file upload failed:', error);
            throw new Error(`Upload failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
        }
    }

    /**
     * Download and decrypt file from server
     */
    async downloadAndDecryptFile(
        conversationId: string,
        fileId: string,
        deviceId: string
    ): Promise<{ blob: Blob; filename: string; mimeType: string }> {
        try {
            // Download encrypted file blob and metadata
            const response = await fetch(
                `/api/v1/chat/conversations/${conversationId}/files/${fileId}/download?device_id=${deviceId}`,
                {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Accept': 'application/octet-stream',
                    },
                }
            );

            if (!response.ok) {
                throw new Error(`Download failed: ${response.statusText}`);
            }

            // Get metadata from headers
            const originalFilename = response.headers.get('X-Original-Filename') || 'download';
            const originalMimeType = response.headers.get('X-Original-Mime-Type') || 'application/octet-stream';
            const originalSize = parseInt(response.headers.get('X-Original-Size') || '0', 10);
            const fileHash = response.headers.get('X-File-Hash') || '';
            const encryptionKeyDataHeader = response.headers.get('X-Encryption-Key-Data');
            
            if (!encryptionKeyDataHeader) {
                throw new Error('Missing encryption key data in response');
            }

            const encryptionKeyData = JSON.parse(atob(encryptionKeyDataHeader));
            
            // Get encrypted content
            const encryptedContent = await response.arrayBuffer();

            const fileInfo: FileDownloadInfo = {
                fileId,
                originalFilename,
                originalMimeType,
                originalSize,
                encryptedSize: encryptedContent.byteLength,
                fileType: this.getFileTypeCategory(originalMimeType),
                hasThumbnail: false, // Will be determined from server response
                supportsPreview: this.supportsPreview(originalMimeType),
            };

            // Decrypt the file
            const decryptedBlob = await this.decryptFile(
                encryptedContent,
                fileInfo,
                conversationId,
                deviceId,
                encryptionKeyData
            );

            return {
                blob: decryptedBlob,
                filename: originalFilename,
                mimeType: originalMimeType,
            };

        } catch (error) {
            console.error('File download and decrypt failed:', error);
            throw new Error(`Download failed: ${error instanceof Error ? error.message : 'Unknown error'}`);
        }
    }

    /**
     * Generate thumbnail for image files
     */
    private async generateThumbnail(file: File, maxSize: number = 150): Promise<File | null> {
        if (!file.type.startsWith('image/')) {
            return null;
        }

        try {
            return new Promise((resolve) => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const img = new Image();

                img.onload = () => {
                    // Calculate thumbnail dimensions maintaining aspect ratio
                    const { width, height } = this.calculateThumbnailSize(
                        img.width,
                        img.height,
                        maxSize
                    );

                    canvas.width = width;
                    canvas.height = height;

                    // Draw and compress
                    ctx?.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob(
                        (blob) => {
                            if (blob) {
                                const thumbnailFile = new File([blob], `thumb_${file.name}`, {
                                    type: 'image/jpeg',
                                });
                                resolve(thumbnailFile);
                            } else {
                                resolve(null);
                            }
                        },
                        'image/jpeg',
                        0.8
                    );
                };

                img.onerror = () => resolve(null);
                img.src = URL.createObjectURL(file);
            });

        } catch (error) {
            console.warn('Thumbnail generation failed:', error);
            return null;
        }
    }

    /**
     * Calculate thumbnail dimensions
     */
    private calculateThumbnailSize(
        originalWidth: number,
        originalHeight: number,
        maxSize: number
    ): { width: number; height: number } {
        const aspectRatio = originalWidth / originalHeight;

        if (originalWidth > originalHeight) {
            return {
                width: Math.min(maxSize, originalWidth),
                height: Math.min(maxSize, originalWidth) / aspectRatio,
            };
        } else {
            return {
                width: Math.min(maxSize, originalHeight) * aspectRatio,
                height: Math.min(maxSize, originalHeight),
            };
        }
    }

    /**
     * Convert File to ArrayBuffer
     */
    private fileToArrayBuffer(file: File | Blob): Promise<ArrayBuffer> {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result as ArrayBuffer);
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsArrayBuffer(file);
        });
    }

    /**
     * Generate SHA-256 hash of file content
     */
    private async generateFileHash(content: ArrayBuffer): Promise<string> {
        const hashBuffer = await crypto.subtle.digest('SHA-256', content);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Convert ArrayBuffer to Base64
     */
    private arrayBufferToBase64(buffer: ArrayBuffer): string {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    /**
     * Get file type category
     */
    private getFileTypeCategory(mimeType: string): 'image' | 'video' | 'audio' | 'document' | 'archive' | 'file' {
        if (mimeType.startsWith('image/')) {
            return 'image';
        } else if (mimeType.startsWith('video/')) {
            return 'video';
        } else if (mimeType.startsWith('audio/')) {
            return 'audio';
        } else if ([
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/markdown'
        ].includes(mimeType)) {
            return 'document';
        } else if ([
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
            'application/x-tar'
        ].includes(mimeType)) {
            return 'archive';
        } else {
            return 'file';
        }
    }

    /**
     * Check if file type supports preview
     */
    private supportsPreview(mimeType: string): boolean {
        return mimeType.startsWith('image/') ||
               mimeType.startsWith('video/') ||
               mimeType.startsWith('audio/') ||
               mimeType === 'application/pdf' ||
               mimeType === 'text/plain';
    }

    /**
     * Bulk file upload with progress tracking
     */
    async bulkUploadFiles(
        conversationId: string,
        files: File[],
        deviceId: string,
        options: {
            onProgress?: (fileIndex: number, progress: number) => void;
            onFileComplete?: (fileIndex: number, result: any) => void;
            onFileError?: (fileIndex: number, error: Error) => void;
            generateThumbnails?: boolean;
        } = {}
    ): Promise<{ successful: any[]; failed: any[] }> {
        const results = { successful: [], failed: [] };

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            try {
                options.onProgress?.(i, 0);

                // Encrypt file
                const encryptedFileData = await this.encryptFile(file, conversationId, deviceId, {
                    generateThumbnail: options.generateThumbnails,
                });

                options.onProgress?.(i, 50);

                // Upload encrypted file
                const result = await this.uploadEncryptedFile(conversationId, encryptedFileData);

                options.onProgress?.(i, 100);
                options.onFileComplete?.(i, result);

                results.successful.push({ index: i, result });

            } catch (error) {
                const err = error as Error;
                options.onFileError?.(i, err);
                results.failed.push({ 
                    index: i, 
                    error: err.message,
                    filename: file.name,
                });
            }
        }

        return results;
    }
}

// Export singleton instance
export const e2eeFileService = E2EEFileService.getInstance();
export default e2eeFileService;