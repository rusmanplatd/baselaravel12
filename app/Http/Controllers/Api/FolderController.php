<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Services\FileManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FolderController extends Controller
{
    protected FileManagementService $fileService;

    public function __construct(FileManagementService $fileService)
    {
        $this->fileService = $fileService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of folders.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Folder::class);

        $query = Folder::query()->where('owner_type', get_class(Auth::user()))
                              ->where('owner_id', Auth::id())
                              ->with(['parent']);

        // Filter by parent folder
        if ($request->has('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->whereNull('parent_id');
            } else {
                $parent = Folder::findOrFail($request->parent_id);
                $this->authorize('view', $parent);
                $query->where('parent_id', $parent->id);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $folders = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'folders' => $folders->items(),
            'pagination' => [
                'current_page' => $folders->currentPage(),
                'last_page' => $folders->lastPage(),
                'per_page' => $folders->perPage(),
                'total' => $folders->total(),
            ]
        ]);
    }

    /**
     * Store a newly created folder.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Folder::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'visibility' => 'nullable|in:private,internal,public',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $parentFolder = null;
        if ($request->parent_id) {
            $parentFolder = Folder::findOrFail($request->parent_id);
            $this->authorize('createSubfolders', $parentFolder);
        }

        try {
            $folder = $this->fileService->createFolder(
                $request->name,
                Auth::user(),
                $parentFolder,
                [
                    'description' => $request->description,
                    'color' => $request->color,
                    'visibility' => $request->get('visibility', 'private'),
                ]
            );

            return response()->json([
                'message' => 'Folder created successfully',
                'folder' => $folder->load(['parent'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Folder creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified folder.
     */
    public function show(Folder $folder)
    {
        $this->authorize('view', $folder);

        return response()->json([
            'folder' => $folder->load([
                'parent',
                'children' => function ($query) {
                    $query->orderBy('name');
                },
                'files' => function ($query) {
                    $query->orderBy('name')->limit(50);
                }
            ])
        ]);
    }

    /**
     * Update the specified folder.
     */
    public function update(Request $request, Folder $folder)
    {
        $this->authorize('update', $folder);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'parent_id' => 'nullable|exists:folders,id',
            'visibility' => 'sometimes|in:private,internal,public',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for circular references if changing parent
        if ($request->has('parent_id') && $request->parent_id !== $folder->parent_id) {
            if ($request->parent_id) {
                $targetParent = Folder::findOrFail($request->parent_id);
                $this->authorize('createSubfolders', $targetParent);
                
                // Prevent circular references
                if ($targetParent->id === $folder->id || 
                    $folder->getDescendants()->pluck('id')->contains($targetParent->id)) {
                    return response()->json([
                        'message' => 'Cannot move folder to its own descendant'
                    ], 422);
                }
            }
        }

        $folder->update($request->only(['name', 'description', 'color', 'parent_id', 'visibility']));

        // Update path if parent changed
        if ($request->has('parent_id')) {
            $folder->updatePath();
        }

        return response()->json([
            'message' => 'Folder updated successfully',
            'folder' => $folder->load(['parent'])
        ]);
    }

    /**
     * Remove the specified folder.
     */
    public function destroy(Folder $folder)
    {
        $this->authorize('delete', $folder);

        try {
            $this->fileService->deleteFolder($folder);

            return response()->json([
                'message' => 'Folder deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Folder deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get folder tree structure.
     */
    public function tree(Request $request)
    {
        $this->authorize('viewAny', Folder::class);

        $maxDepth = $request->get('max_depth', 5);
        
        $folders = Folder::query()
            ->where('owner_type', get_class(Auth::user()))
            ->where('owner_id', Auth::id())
            ->whereNull('parent_id')
            ->with($this->getNestedWith($maxDepth))
            ->orderBy('name')
            ->get();

        return response()->json([
            'tree' => $folders
        ]);
    }

    /**
     * Copy a folder and its contents.
     */
    public function copy(Request $request, Folder $folder)
    {
        $this->authorize('copy', $folder);

        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:folders,id',
            'name' => 'nullable|string|max:255',
            'include_files' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetParent = null;
        if ($request->parent_id) {
            $targetParent = Folder::findOrFail($request->parent_id);
            $this->authorize('createSubfolders', $targetParent);
        }

        try {
            $copiedFolder = $this->copyFolderRecursively(
                $folder, 
                $targetParent, 
                $request->name ?? ($folder->name . ' (Copy)'),
                $request->get('include_files', true)
            );

            return response()->json([
                'message' => 'Folder copied successfully',
                'folder' => $copiedFolder->load(['parent'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Copy failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move a folder to a different parent.
     */
    public function move(Request $request, Folder $folder)
    {
        $this->authorize('move', $folder);

        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetParent = null;
        if ($request->parent_id) {
            $targetParent = Folder::findOrFail($request->parent_id);
            $this->authorize('createSubfolders', $targetParent);
            
            // Prevent circular references
            if ($targetParent->id === $folder->id || 
                $folder->getDescendants()->pluck('id')->contains($targetParent->id)) {
                return response()->json([
                    'message' => 'Cannot move folder to its own descendant'
                ], 422);
            }
        }

        try {
            $oldParent = $folder->parent;
            
            $folder->update(['parent_id' => $targetParent?->id]);
            $folder->updatePath();

            // Update counts for old and new parents
            if ($oldParent) {
                $oldParent->updateCounts();
            }
            if ($targetParent) {
                $targetParent->updateCounts();
            }

            return response()->json([
                'message' => 'Folder moved successfully',
                'folder' => $folder->fresh(['parent'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Move failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nested with array for tree loading.
     */
    protected function getNestedWith(int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        $with = ['children' => function ($query) {
            $query->orderBy('name');
        }];

        if ($depth > 1) {
            $with['children.children'] = function ($query) use ($depth) {
                $query->orderBy('name');
                if ($depth > 2) {
                    $query->with($this->getNestedWith($depth - 2));
                }
            };
        }

        return $with;
    }

    /**
     * Recursively copy a folder and its contents.
     */
    protected function copyFolderRecursively(Folder $folder, ?Folder $targetParent, string $name, bool $includeFiles): Folder
    {
        // Create new folder
        $newFolder = $this->fileService->createFolder(
            $name,
            Auth::user(),
            $targetParent,
            [
                'description' => $folder->description,
                'color' => $folder->color,
                'visibility' => $folder->visibility,
            ]
        );

        // Copy files if requested
        if ($includeFiles) {
            foreach ($folder->files as $file) {
                $this->fileService->copyFile($file, $newFolder);
            }
        }

        // Recursively copy subfolders
        foreach ($folder->children as $child) {
            $this->copyFolderRecursively($child, $newFolder, $child->name, $includeFiles);
        }

        return $newFolder;
    }
}