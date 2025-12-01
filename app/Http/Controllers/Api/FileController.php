<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Folder;
use App\Services\FileManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    protected FileManagementService $fileService;

    public function __construct(FileManagementService $fileService)
    {
        $this->fileService = $fileService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of files.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', File::class);

        $query = File::query()->where('owner_type', get_class(Auth::user()))
                             ->where('owner_id', Auth::id())
                             ->with(['folder', 'tags.tag']);

        // Filter by folder
        if ($request->has('folder_id')) {
            if ($request->folder_id === 'root') {
                $query->whereNull('folder_id');
            } else {
                $folder = Folder::findOrFail($request->folder_id);
                $this->authorize('view', $folder);
                $query->where('folder_id', $folder->id);
            }
        }

        // Filter by type
        if ($request->has('type')) {
            switch ($request->type) {
                case 'images':
                    $query->images();
                    break;
                case 'videos':
                    $query->videos();
                    break;
                case 'documents':
                    $query->documents();
                    break;
                case 'audio':
                    $query->audios();
                    break;
            }
        }

        // Filter by extension
        if ($request->has('extension')) {
            $query->byExtension($request->extension);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('original_name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $files = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'files' => $files->items(),
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ]
        ]);
    }

    /**
     * Store a newly uploaded file.
     */
    public function store(Request $request)
    {
        $this->authorize('create', File::class);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:102400', // 100MB
            'folder_id' => 'nullable|exists:folders,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'visibility' => 'nullable|in:private,internal,public',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $folder = null;
        if ($request->folder_id) {
            $folder = Folder::findOrFail($request->folder_id);
            $this->authorize('uploadFiles', $folder);
        }

        try {
            $file = $this->fileService->uploadFile(
                $request->file('file'),
                Auth::user(),
                $folder,
                [
                    'name' => $request->name,
                    'description' => $request->description,
                    'visibility' => $request->get('visibility', 'private'),
                    'generate_preview' => true,
                ]
            );

            $this->fileService->logFileAccess($file, 'upload', Auth::user());

            return response()->json([
                'message' => 'File uploaded successfully',
                'file' => $file->load(['folder', 'tags.tag'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'File upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified file.
     */
    public function show(File $file)
    {
        $this->authorize('view', $file);

        $this->fileService->logFileAccess($file, 'view', Auth::user());

        return response()->json([
            'file' => $file->load(['folder', 'tags.tag', 'comments.user', 'versions'])
        ]);
    }

    /**
     * Update the specified file.
     */
    public function update(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'folder_id' => 'nullable|exists:folders,id',
            'visibility' => 'sometimes|in:private,internal,public',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check folder permissions if moving
        if ($request->has('folder_id') && $request->folder_id !== $file->folder_id) {
            if ($request->folder_id) {
                $targetFolder = Folder::findOrFail($request->folder_id);
                $this->authorize('uploadFiles', $targetFolder);
            }
        }

        $file->update($request->only(['name', 'description', 'folder_id', 'visibility']));

        $this->fileService->logFileAccess($file, 'edit', Auth::user());

        return response()->json([
            'message' => 'File updated successfully',
            'file' => $file->load(['folder', 'tags.tag'])
        ]);
    }

    /**
     * Remove the specified file from storage.
     */
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        $this->fileService->deleteFile($file);
        $this->fileService->logFileAccess($file, 'delete', Auth::user());

        return response()->json([
            'message' => 'File deleted successfully'
        ]);
    }

    /**
     * Download a file.
     */
    public function download(File $file)
    {
        $this->authorize('download', $file);

        try {
            $this->fileService->logFileAccess($file, 'download', Auth::user());

            if (Storage::disk($file->disk)->exists($file->path)) {
                return Storage::disk($file->disk)->download($file->path, $file->original_name);
            }

            return response()->json(['message' => 'File not found'], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Download failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file thumbnail.
     */
    public function thumbnail(File $file)
    {
        $this->authorize('view', $file);

        if (!$file->thumbnail_path || !Storage::disk($file->disk)->exists($file->thumbnail_path)) {
            return response()->json(['message' => 'Thumbnail not available'], 404);
        }

        return Storage::disk($file->disk)->response($file->thumbnail_path);
    }

    /**
     * Copy a file.
     */
    public function copy(Request $request, File $file)
    {
        $this->authorize('copy', $file);

        $validator = Validator::make($request->all(), [
            'folder_id' => 'nullable|exists:folders,id',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetFolder = null;
        if ($request->folder_id) {
            $targetFolder = Folder::findOrFail($request->folder_id);
            $this->authorize('uploadFiles', $targetFolder);
        }

        try {
            $copiedFile = $this->fileService->copyFile($file, $targetFolder, $request->name);
            $this->fileService->logFileAccess($file, 'copy', Auth::user());

            return response()->json([
                'message' => 'File copied successfully',
                'file' => $copiedFile->load(['folder', 'tags.tag'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Copy failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move a file to different folder.
     */
    public function move(Request $request, File $file)
    {
        $this->authorize('move', $file);

        $validator = Validator::make($request->all(), [
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetFolder = null;
        if ($request->folder_id) {
            $targetFolder = Folder::findOrFail($request->folder_id);
            $this->authorize('uploadFiles', $targetFolder);
        }

        try {
            $this->fileService->moveFile($file, $targetFolder);
            $this->fileService->logFileAccess($file, 'move', Auth::user());

            return response()->json([
                'message' => 'File moved successfully',
                'file' => $file->fresh(['folder', 'tags.tag'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Move failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file versions.
     */
    public function versions(File $file)
    {
        $this->authorize('viewVersions', $file);

        return response()->json([
            'versions' => $file->getAllVersions()
        ]);
    }

    /**
     * Restore a deleted file.
     */
    public function restore($id)
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('restore', $file);

        $file->restore();
        $this->fileService->logFileAccess($file, 'restore', Auth::user());

        return response()->json([
            'message' => 'File restored successfully',
            'file' => $file->load(['folder', 'tags.tag'])
        ]);
    }

    /**
     * Search files.
     */
    public function search(Request $request)
    {
        $this->authorize('viewAny', File::class);

        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'folder_id' => 'nullable|exists:folders,id',
            'type' => 'nullable|in:images,videos,documents,audio',
            'extensions' => 'nullable|array',
            'size_min' => 'nullable|integer|min:0',
            'size_max' => 'nullable|integer|min:0',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $folder = null;
        if ($request->folder_id) {
            $folder = Folder::findOrFail($request->folder_id);
            $this->authorize('view', $folder);
        }

        $results = $this->fileService->search(
            $request->query,
            Auth::user(),
            $folder,
            $request->only(['type', 'extensions', 'size_min', 'size_max', 'date_from', 'date_to'])
        );

        return response()->json($results);
    }

    /**
     * Get storage statistics.
     */
    public function stats()
    {
        $this->authorize('viewAny', File::class);

        $stats = $this->fileService->getStorageStats(Auth::user());

        return response()->json($stats);
    }
}