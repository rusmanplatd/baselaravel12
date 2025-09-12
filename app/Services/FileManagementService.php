<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileAccessLog;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class FileManagementService
{
    protected string $defaultDisk = 'minio';
    protected array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    protected array $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
    protected array $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

    /**
     * Upload a file to the specified folder or root
     */
    public function uploadFile(
        UploadedFile $uploadedFile, 
        Model $owner, 
        ?Folder $folder = null,
        array $options = []
    ): File {
        return DB::transaction(function () use ($uploadedFile, $owner, $folder, $options) {
            // Generate file paths and metadata
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();
            $mimeType = $uploadedFile->getMimeType();
            $size = $uploadedFile->getSize();
            $hash = hash_file('sha256', $uploadedFile->getPathname());

            // Check for duplicate files
            if ($options['check_duplicates'] ?? true) {
                $existingFile = File::where('hash', $hash)->first();
                if ($existingFile && ($options['skip_duplicates'] ?? false)) {
                    return $existingFile;
                }
            }

            // Generate storage path
            $storagePath = $this->generateStoragePath($originalName, $extension);
            
            // Store file
            $disk = $options['disk'] ?? $this->defaultDisk;
            $uploadedFile->storeAs(dirname($storagePath), basename($storagePath), $disk);

            // Create file record
            $file = File::create([
                'name' => $options['name'] ?? pathinfo($originalName, PATHINFO_FILENAME),
                'original_name' => $originalName,
                'description' => $options['description'] ?? null,
                'owner_type' => get_class($owner),
                'owner_id' => $owner->id,
                'folder_id' => $folder?->id,
                'mime_type' => $mimeType,
                'extension' => strtolower($extension),
                'size' => $size,
                'hash' => $hash,
                'disk' => $disk,
                'path' => $storagePath,
                'visibility' => $options['visibility'] ?? 'private',
                'is_encrypted' => $options['encrypt'] ?? false,
                'metadata' => $this->extractMetadata($uploadedFile, $mimeType),
            ]);

            // Generate thumbnails for images
            if ($this->isImage($extension)) {
                $this->generateThumbnail($file, $uploadedFile);
            }

            // Generate preview for documents if enabled
            if ($options['generate_preview'] ?? false) {
                $this->generatePreview($file);
            }

            // Update folder counts
            if ($folder) {
                $folder->updateCounts();
            }

            return $file;
        });
    }

    /**
     * Create a new folder
     */
    public function createFolder(
        string $name, 
        Model $owner, 
        ?Folder $parentFolder = null,
        array $options = []
    ): Folder {
        return DB::transaction(function () use ($name, $owner, $parentFolder, $options) {
            $folder = Folder::create([
                'name' => $name,
                'slug' => $options['slug'] ?? Str::slug($name),
                'description' => $options['description'] ?? null,
                'color' => $options['color'] ?? null,
                'owner_type' => get_class($owner),
                'owner_id' => $owner->id,
                'parent_id' => $parentFolder?->id,
                'visibility' => $options['visibility'] ?? 'private',
            ]);

            // Update path and level
            $folder->updatePath();

            // Update parent folder counts
            if ($parentFolder) {
                $parentFolder->updateCounts();
            }

            return $folder;
        });
    }

    /**
     * Copy a file to a new location
     */
    public function copyFile(File $file, ?Folder $targetFolder = null, string $newName = null): File
    {
        return DB::transaction(function () use ($file, $targetFolder, $newName) {
            $newPath = $this->generateStoragePath($newName ?? $file->name, $file->extension);
            
            // Copy physical file
            Storage::disk($file->disk)->copy($file->path, $newPath);
            
            // Copy thumbnails if they exist
            if ($file->thumbnail_path) {
                $newThumbnailPath = $this->generateThumbnailPath($newPath);
                Storage::disk($file->disk)->copy($file->thumbnail_path, $newThumbnailPath);
            }

            // Create new file record
            $newFile = $file->replicate();
            $newFile->name = $newName ?? ($file->name . ' (Copy)');
            $newFile->path = $newPath;
            $newFile->thumbnail_path = $newThumbnailPath ?? null;
            $newFile->folder_id = $targetFolder?->id ?? $file->folder_id;
            $newFile->save();

            // Update folder counts
            if ($newFile->folder) {
                $newFile->folder->updateCounts();
            }

            return $newFile;
        });
    }

    /**
     * Move a file to a different folder
     */
    public function moveFile(File $file, ?Folder $targetFolder = null): File
    {
        return DB::transaction(function () use ($file, $targetFolder) {
            $oldFolder = $file->folder;
            
            $file->update(['folder_id' => $targetFolder?->id]);

            // Update folder counts
            if ($oldFolder) {
                $oldFolder->updateCounts();
            }
            if ($targetFolder) {
                $targetFolder->updateCounts();
            }

            return $file;
        });
    }

    /**
     * Delete a file (soft delete)
     */
    public function deleteFile(File $file, bool $permanent = false): bool
    {
        return DB::transaction(function () use ($file, $permanent) {
            if ($permanent) {
                // Delete physical files
                if ($file->path && Storage::disk($file->disk)->exists($file->path)) {
                    Storage::disk($file->disk)->delete($file->path);
                }
                if ($file->thumbnail_path && Storage::disk($file->disk)->exists($file->thumbnail_path)) {
                    Storage::disk($file->disk)->delete($file->thumbnail_path);
                }
                if ($file->preview_path && Storage::disk($file->disk)->exists($file->preview_path)) {
                    Storage::disk($file->disk)->delete($file->preview_path);
                }

                $result = $file->forceDelete();
            } else {
                $result = $file->delete();
            }

            // Update folder counts
            if ($file->folder) {
                $file->folder->updateCounts();
            }

            return $result;
        });
    }

    /**
     * Delete a folder and all its contents
     */
    public function deleteFolder(Folder $folder, bool $permanent = false): bool
    {
        return DB::transaction(function () use ($folder, $permanent) {
            // Delete all files in folder
            foreach ($folder->files as $file) {
                $this->deleteFile($file, $permanent);
            }

            // Recursively delete subfolders
            foreach ($folder->children as $child) {
                $this->deleteFolder($child, $permanent);
            }

            $parentFolder = $folder->parent;
            
            if ($permanent) {
                $result = $folder->forceDelete();
            } else {
                $result = $folder->delete();
            }

            // Update parent folder counts
            if ($parentFolder) {
                $parentFolder->updateCounts();
            }

            return $result;
        });
    }

    /**
     * Generate a secure download URL for a file
     */
    public function generateDownloadUrl(File $file, int $expiresIn = 3600): string
    {
        return Storage::disk($file->disk)->temporaryUrl($file->path, now()->addSeconds($expiresIn));
    }

    /**
     * Log file access
     */
    public function logFileAccess(File $file, string $action, ?User $user = null, array $metadata = []): void
    {
        $request = request();
        
        FileAccessLog::create([
            'file_type' => 'file',
            'file_id' => $file->id,
            'user_id' => $user?->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'action' => $action,
            'metadata' => array_merge($metadata, [
                'file_name' => $file->name,
                'file_size' => $file->size,
                'mime_type' => $file->mime_type,
            ]),
            'accessed_at' => now(),
        ]);

        // Update file access stats
        if ($action === 'download') {
            $file->incrementDownloadCount();
        }
    }

    /**
     * Search files and folders
     */
    public function search(string $query, Model $owner, ?Folder $folder = null, array $filters = [])
    {
        $fileQuery = File::query()
            ->where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                  ->orWhere('original_name', 'ILIKE', "%{$query}%")
                  ->orWhere('description', 'ILIKE', "%{$query}%");
            });

        $folderQuery = Folder::query()
            ->where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                  ->orWhere('description', 'ILIKE', "%{$query}%");
            });

        // Apply folder filter
        if ($folder) {
            $folderIds = $folder->getDescendants()->pluck('id')->prepend($folder->id);
            $fileQuery->whereIn('folder_id', $folderIds);
            $folderQuery->whereIn('parent_id', $folderIds);
        }

        // Apply additional filters
        if (!empty($filters['mime_types'])) {
            $fileQuery->whereIn('mime_type', $filters['mime_types']);
        }

        if (!empty($filters['extensions'])) {
            $fileQuery->whereIn('extension', $filters['extensions']);
        }

        if (!empty($filters['size_min'])) {
            $fileQuery->where('size', '>=', $filters['size_min']);
        }

        if (!empty($filters['size_max'])) {
            $fileQuery->where('size', '<=', $filters['size_max']);
        }

        if (!empty($filters['date_from'])) {
            $fileQuery->where('created_at', '>=', $filters['date_from']);
            $folderQuery->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $fileQuery->where('created_at', '<=', $filters['date_to']);
            $folderQuery->where('created_at', '<=', $filters['date_to']);
        }

        return [
            'files' => $fileQuery->get(),
            'folders' => $folderQuery->get(),
        ];
    }

    /**
     * Get storage statistics for an owner
     */
    public function getStorageStats(Model $owner): array
    {
        $files = File::where('owner_type', get_class($owner))
                    ->where('owner_id', $owner->id);

        return [
            'total_files' => $files->count(),
            'total_size' => $files->sum('size'),
            'total_folders' => Folder::where('owner_type', get_class($owner))
                                   ->where('owner_id', $owner->id)
                                   ->count(),
            'by_type' => $files->select('mime_type', DB::raw('count(*) as count'), DB::raw('sum(size) as size'))
                              ->groupBy('mime_type')
                              ->get()
                              ->keyBy('mime_type'),
            'recent_files' => $files->orderBy('created_at', 'desc')->limit(10)->get(),
        ];
    }

    /**
     * Generate storage path for file
     */
    protected function generateStoragePath(string $filename, string $extension): string
    {
        $date = now()->format('Y/m/d');
        $hash = Str::random(32);
        return "files/{$date}/{$hash}.{$extension}";
    }

    /**
     * Generate thumbnail for image files
     */
    protected function generateThumbnail(File $file, UploadedFile $uploadedFile): void
    {
        if (!$this->isImage($file->extension)) {
            return;
        }

        try {
            $thumbnailPath = $this->generateThumbnailPath($file->path);
            
            $image = Image::make($uploadedFile->getPathname())
                         ->fit(300, 300)
                         ->encode('jpg', 80);

            Storage::disk($file->disk)->put($thumbnailPath, $image->getEncoded());
            
            $file->update([
                'thumbnail_path' => $thumbnailPath,
                'has_preview' => true,
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the upload
            \Log::error('Thumbnail generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate preview for documents
     */
    protected function generatePreview(File $file): void
    {
        // Placeholder for document preview generation
        // Could integrate with services like ImageMagick, LibreOffice, etc.
        
        if (in_array($file->extension, ['pdf', 'doc', 'docx'])) {
            // TODO: Implement document preview generation
        }
    }

    /**
     * Extract metadata from uploaded file
     */
    protected function extractMetadata(UploadedFile $file, string $mimeType): array
    {
        $metadata = [];

        if (str_starts_with($mimeType, 'image/')) {
            try {
                $imageInfo = getimagesize($file->getPathname());
                if ($imageInfo) {
                    $metadata['width'] = $imageInfo[0];
                    $metadata['height'] = $imageInfo[1];
                    $metadata['type'] = $imageInfo[2];
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return $metadata;
    }

    /**
     * Generate thumbnail path from file path
     */
    protected function generateThumbnailPath(string $filePath): string
    {
        $pathInfo = pathinfo($filePath);
        return $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['filename'] . '_thumb.jpg';
    }

    /**
     * Check if file is an image
     */
    protected function isImage(string $extension): bool
    {
        return in_array(strtolower($extension), $this->imageExtensions);
    }

    /**
     * Check if file is a video
     */
    protected function isVideo(string $extension): bool
    {
        return in_array(strtolower($extension), $this->videoExtensions);
    }

    /**
     * Check if file is a document
     */
    protected function isDocument(string $extension): bool
    {
        return in_array(strtolower($extension), $this->documentExtensions);
    }
}