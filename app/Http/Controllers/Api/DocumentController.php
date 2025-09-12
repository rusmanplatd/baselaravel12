<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCollaborator;
use App\Models\DocumentCollaborationSession;
use App\Models\DocumentRevision;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Document::query();

        // Filter by folder
        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }

        // Filter by ownership
        if ($request->boolean('my_documents')) {
            $query->forOwner(get_class($user), $user->id);
        }

        // Filter by collaboration
        if ($request->boolean('collaborative')) {
            $query->collaborative();
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'ilike', '%' . $request->search . '%')
                  ->orWhere('content', 'ilike', '%' . $request->search . '%');
            });
        }

        // Order by
        $orderBy = $request->input('order_by', 'updated_at');
        $orderDirection = $request->input('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $documents = $query->with([
            'owner',
            'folder',
            'lastEditedBy',
            'activeCollaborators.user'
        ])->paginate($request->input('per_page', 15));

        return response()->json($documents);
    }

    public function show(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->load([
            'owner',
            'folder',
            'lastEditedBy',
            'collaborators.user',
            'activeCollaborators.user'
        ]);

        // Log access
        $document->logAccess('view', Auth::user(), request()->ip(), request()->userAgent());

        return response()->json($document);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id',
            'visibility' => ['nullable', Rule::in(['private', 'public', 'restricted'])],
            'is_collaborative' => 'boolean',
            'is_template' => 'boolean',
            'template_data' => 'nullable|array',
            'metadata' => 'nullable|array',
            'collaboration_settings' => 'nullable|array',
        ]);

        $user = Auth::user();

        $document = Document::create([
            ...$validated,
            'owner_type' => get_class($user),
            'owner_id' => $user->id,
            'last_edited_by' => $user->id,
            'visibility' => $validated['visibility'] ?? 'private',
            'is_collaborative' => $validated['is_collaborative'] ?? true,
        ]);

        // Add creator as owner collaborator
        if ($document->is_collaborative) {
            $document->addCollaborator($user, 'owner');
        }

        // Create initial revision
        $document->createRevision($user, ['action' => 'created']);

        $document->load(['owner', 'folder', 'collaborators.user']);

        return response()->json($document, 201);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'folder_id' => 'sometimes|nullable|exists:folders,id',
            'visibility' => ['sometimes', Rule::in(['private', 'public', 'restricted'])],
            'is_shared' => 'boolean',
            'share_settings' => 'nullable|array',
            'is_collaborative' => 'boolean',
            'collaboration_settings' => 'nullable|array',
            'metadata' => 'nullable|array',
            'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $user = Auth::user();
        $changes = [];

        // Track changes for revision
        foreach ($validated as $key => $value) {
            if ($document->$key !== $value) {
                $changes[$key] = [
                    'from' => $document->$key,
                    'to' => $value
                ];
            }
        }

        $document->update([
            ...$validated,
            'last_edited_by' => $user->id,
            'last_edited_at' => now(),
        ]);

        $document->incrementVersion();

        // Create revision if there are significant changes
        if (!empty($changes)) {
            $document->createRevision($user, $changes);
        }

        $document->load(['owner', 'folder', 'collaborators.user']);

        return response()->json($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function sync(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'content' => 'required|string',
            'yjs_state' => 'required|array',
            'last_edited_by' => 'required|string|exists:sys_users,id',
        ]);

        // Convert array back to binary for storage
        $yjsState = pack('C*', ...$validated['yjs_state']);

        $document->update([
            'content' => $validated['content'],
            'yjs_state' => $yjsState,
            'last_edited_by' => $validated['last_edited_by'],
            'last_edited_at' => now(),
        ]);

        $document->incrementVersion();

        // Auto-save revision every 50 versions or significant content changes
        if ($document->version % 50 === 0 || strlen($validated['content']) - strlen($document->getOriginal('content')) > 1000) {
            $document->createRevision(
                Auth::user(),
                ['action' => 'auto_sync', 'content_length_change' => strlen($validated['content']) - strlen($document->getOriginal('content'))]
            );
        }

        return response()->json(['message' => 'Document synced successfully']);
    }

    public function permissions(Document $document): JsonResponse
    {
        $user = Auth::user();

        $permissions = [
            'can_read' => $document->isPublic() || $document->isOwnedBy($user) || 
                         $document->collaborators()->where('user_id', $user->id)->exists(),
            'can_edit' => $document->canBeEditedBy($user),
            'can_comment' => $document->canBeCommentedBy($user),
            'can_share' => $document->isOwnedBy($user),
            'can_delete' => $document->isOwnedBy($user),
        ];

        return response()->json($permissions);
    }

    public function collaborators(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $collaborators = $document->collaborators()
            ->with('user')
            ->orderBy('role')
            ->orderBy('last_seen', 'desc')
            ->get();

        return response()->json($collaborators);
    }

    public function addCollaborator(Request $request, Document $document): JsonResponse
    {
        $this->authorize('manage-collaborators', $document);

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'role' => ['required', Rule::in(['viewer', 'commenter', 'editor'])],
        ]);

        $collaborator = $document->addCollaborator(
            \App\Models\User::find($validated['user_id']),
            $validated['role']
        );

        $collaborator->load('user');

        return response()->json($collaborator, 201);
    }

    public function updateCollaborator(Request $request, Document $document, DocumentCollaborator $collaborator): JsonResponse
    {
        $this->authorize('manage-collaborators', $document);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['viewer', 'commenter', 'editor'])],
        ]);

        $collaborator->setRole($validated['role']);

        return response()->json($collaborator);
    }

    public function removeCollaborator(Document $document, DocumentCollaborator $collaborator): JsonResponse
    {
        $this->authorize('manage-collaborators', $document);

        $collaborator->delete();

        return response()->json(['message' => 'Collaborator removed successfully']);
    }

    public function updatePresence(Request $request, Document $document, string $userId): JsonResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'cursor_position' => 'nullable|array',
            'selection_range' => 'nullable|array',
            'last_seen' => 'nullable|date',
        ]);

        $collaborator = $document->collaborators()->where('user_id', $userId)->first();

        if ($collaborator) {
            $collaborator->updatePresence(
                $validated['cursor_position'],
                $validated['selection_range']
            );
        }

        return response()->json(['message' => 'Presence updated successfully']);
    }

    public function collaborationSessions(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $sessions = $document->collaborationSessions()
            ->with('user')
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('started_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($sessions);
    }

    public function createCollaborationSession(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'action' => 'required|string|in:connect,disconnect',
            'session_id' => 'required|string',
            'socket_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();

        if ($validated['action'] === 'connect') {
            $session = DocumentCollaborationSession::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'session_id' => $validated['session_id'],
                'socket_id' => $validated['socket_id'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => $validated['metadata'],
            ]);
        } else {
            $session = $document->collaborationSessions()
                ->where('user_id', $user->id)
                ->where('session_id', $validated['session_id'])
                ->where('is_active', true)
                ->first();

            if ($session) {
                $session->end();
            }
        }

        return response()->json(['message' => 'Session logged successfully']);
    }

    public function revisions(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $revisions = $document->revisions()
            ->with('createdBy')
            ->orderBy('version', 'desc')
            ->paginate(20);

        return response()->json($revisions);
    }

    public function createRevision(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'milestone_name' => 'nullable|string|max:255',
            'is_milestone' => 'boolean',
        ]);

        $user = Auth::user();
        $revision = $document->createRevision($user);

        if ($validated['is_milestone'] ?? false) {
            $revision->createMilestone($validated['milestone_name'] ?? 'Manual milestone');
        }

        $revision->load('createdBy');

        return response()->json($revision, 201);
    }

    public function restoreRevision(Document $document, DocumentRevision $revision): JsonResponse
    {
        $this->authorize('update', $document);

        $revision->restore();

        return response()->json(['message' => 'Document restored to revision ' . $revision->version]);
    }

    public function duplicate(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        $folder = null;
        if ($validated['folder_id'] ?? null) {
            $folder = Folder::find($validated['folder_id']);
        }

        $duplicate = $document->duplicate(
            $validated['title'] ?? null,
            $folder
        );

        $duplicate->update([
            'owner_type' => get_class(Auth::user()),
            'owner_id' => Auth::user()->id,
        ]);

        $duplicate->load(['owner', 'folder']);

        return response()->json($duplicate, 201);
    }
}