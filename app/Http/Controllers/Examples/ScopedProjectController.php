<?php

namespace App\Http\Controllers\Examples;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Organization;
use App\Facades\ScopedPermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Example controller demonstrating scoped permissions for projects
 * This shows how to use the new scoped permission system
 */
class ScopedProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        
        // Example of scoped permission middleware
        // permission.scoped:{permission}:{scope_type}:{scope_param}
        $this->middleware('permission.scoped:view_project:project:project')->only(['show']);
        $this->middleware('permission.scoped:edit_project:project:project')->only(['update']);
        $this->middleware('permission.scoped:delete_project:project:project')->only(['destroy']);
        
        // Organization-level permissions
        $this->middleware('permission.scoped:create_project:organization:organization')->only(['store']);
        $this->middleware('permission.scoped:manage_projects:organization:organization')->only(['index']);
    }

    /**
     * List projects within an organization
     * Requires 'manage_projects' permission in organization scope
     */
    public function index(Request $request, string $organization): JsonResponse
    {
        $user = Auth::user();
        
        // Get projects where user has permissions
        $projects = Project::where('organization_id', $organization)
            ->whereHas('organization', function ($query) use ($user, $organization) {
                // Only show projects in organizations where user has permissions
                return $query->where('id', $organization)
                           ->whereExists(function ($subQuery) use ($user, $organization) {
                               $subQuery->from('sys_model_has_permissions')
                                      ->whereColumn('sys_model_has_permissions.model_id', $user->getKey())
                                      ->where('sys_model_has_permissions.scope_type', 'organization')
                                      ->where('sys_model_has_permissions.scope_id', $organization);
                           });
            })
            ->with(['members', 'organization'])
            ->paginate(15);

        return response()->json($projects);
    }

    /**
     * Show specific project
     * Requires 'view_project' permission in project scope
     */
    public function show(string $project): JsonResponse
    {
        $projectModel = Project::with(['members', 'organization', 'tasks'])->findOrFail($project);
        
        return response()->json($projectModel);
    }

    /**
     * Create new project in organization
     * Requires 'create_project' permission in organization scope
     */
    public function store(Request $request, string $organization): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'nullable|date',
        ]);

        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'deadline' => $request->deadline,
            'organization_id' => $organization,
            'creator_id' => Auth::id(),
        ]);

        // Set up scoped permissions for this project
        ScopedPermission::setupScopeHierarchy(
            $project,
            'organization', // parent scope type
            $organization, // parent scope id
            true, // inherits permissions from organization
            ['created_by' => Auth::id()]
        );

        // Give creator full permissions on the project
        ScopedPermission::assignRoleToUser(
            Auth::user(),
            'project_admin',
            'project',
            $project->id
        );

        return response()->json($project, 201);
    }

    /**
     * Update project
     * Requires 'edit_project' permission in project scope
     */
    public function update(Request $request, string $project): JsonResponse
    {
        $projectModel = Project::findOrFail($project);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,completed,cancelled',
        ]);

        $projectModel->update($request->only(['name', 'description', 'status']));

        return response()->json($projectModel);
    }

    /**
     * Delete project
     * Requires 'delete_project' permission in project scope
     */
    public function destroy(string $project): JsonResponse
    {
        $projectModel = Project::findOrFail($project);
        $projectModel->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Assign user to project with specific role
     * Requires 'manage_project_members' permission in project scope
     */
    public function assignMember(Request $request, string $project): JsonResponse
    {
        $user = Auth::user();
        $projectModel = Project::findOrFail($project);
        
        // Check scoped permission manually
        if (!$user->hasPermissionInScope('manage_project_members', 'project', $project)) {
            abort(403, 'Insufficient permissions');
        }

        $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'role' => 'required|in:member,admin,viewer',
        ]);

        $targetUser = \App\Models\User::findOrFail($request->user_id);
        
        // Assign role to user in project scope
        ScopedPermission::assignRoleToUser(
            $targetUser,
            "project_{$request->role}",
            'project',
            $project
        );

        return response()->json(['message' => 'Member assigned successfully']);
    }

    /**
     * Remove user from project
     * Requires 'manage_project_members' permission in project scope
     */
    public function removeMember(Request $request, string $project, string $userId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->hasPermissionInScope('manage_project_members', 'project', $project)) {
            abort(403, 'Insufficient permissions');
        }

        $targetUser = \App\Models\User::findOrFail($userId);
        
        // Remove all project roles from user
        $projectRoles = ['project_member', 'project_admin', 'project_viewer'];
        
        foreach ($projectRoles as $role) {
            ScopedPermission::removeRoleFromUser($targetUser, $role, 'project', $project);
        }

        return response()->json(['message' => 'Member removed successfully']);
    }

    /**
     * Get project permissions for current user
     */
    public function getMyPermissions(string $project): JsonResponse
    {
        $user = Auth::user();
        $permissions = $user->getPermissionsForScope('project', $project);
        $roles = $user->getRolesForScope('project', $project);
        
        return response()->json([
            'permissions' => $permissions->pluck('name'),
            'roles' => $roles->pluck('name'),
            'effective_permissions' => ScopedPermission::getUserEffectivePermissions($user, 'project', $project),
        ]);
    }

    /**
     * Get all project members and their permissions
     * Requires 'view_project_members' permission in project scope
     */
    public function getMembers(string $project): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->hasPermissionInScope('view_project_members', 'project', $project)) {
            abort(403, 'Insufficient permissions');
        }

        $members = ScopedPermission::getUsersWithPermissionInScope(
            'view_project',
            'project', 
            $project
        );

        $memberData = $members->map(function ($member) use ($project) {
            return [
                'user' => $member->only(['id', 'name', 'email']),
                'permissions' => $member->getPermissionsForScope('project', $project)->pluck('name'),
                'roles' => $member->getRolesForScope('project', $project)->pluck('name'),
            ];
        });

        return response()->json($memberData);
    }
}