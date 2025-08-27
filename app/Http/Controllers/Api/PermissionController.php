<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePermissionRequest;
use App\Http\Requests\Api\UpdatePermissionRequest;
use App\Models\Auth\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('Role & Permission Management')]
class PermissionController extends Controller
{
    #[Endpoint(
        title: 'Get permissions',
        description: 'Retrieve a paginated list of permissions with their roles, optionally filtered by guard name'
    )]
    #[Authenticated]
    #[QueryParam('guard_name', 'string', 'Filter by guard name', false, 'web')]
    #[QueryParam('search', 'string', 'Search permissions by name', false, 'organization')]
    #[QueryParam('per_page', 'integer', 'Number of results per page', false, 15)]
    #[ScribeResponse([
        'data' => [
            ['id' => 1, 'name' => 'org:read', 'guard_name' => 'web', 'roles' => [['name' => 'admin']]],
            ['id' => 2, 'name' => 'org:write', 'guard_name' => 'web', 'roles' => [['name' => 'manager']]],
        ],
        'meta' => ['current_page' => 1, 'total' => 2],
    ])]
    public function index(Request $request)
    {
        $query = Permission::with(['roles']);

        // Filter by guard name
        if ($request->has('guard_name')) {
            $query->where('guard_name', $request->guard_name);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $permissions = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json($permissions);
    }

    #[Endpoint(
        title: 'Create permission',
        description: 'Create a new permission'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Permission created successfully', 'permission' => ['id' => 1, 'name' => 'organization:delete', 'guard_name' => 'web']], 201)]
    #[ScribeResponse(['message' => 'Permission already exists'], 400)]
    public function store(StorePermissionRequest $request)
    {
        $validated = $request->validated();

        // Check if permission already exists
        $existingPermission = Permission::where([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ])->first();

        if ($existingPermission) {
            return response()->json([
                'message' => 'Permission already exists',
            ], 400);
        }

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission,
        ], 201);
    }

    #[Endpoint(
        title: 'Get permission',
        description: 'Retrieve a specific permission with its roles'
    )]
    #[Authenticated]
    #[ScribeResponse(['id' => 1, 'name' => 'org:read', 'guard_name' => 'web', 'roles' => [['name' => 'admin']]])]
    public function show(Permission $permission)
    {
        return response()->json($permission->load(['roles']));
    }

    #[Endpoint(
        title: 'Update permission',
        description: 'Update an existing permission'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Permission updated successfully', 'permission' => ['id' => 1, 'name' => 'organization:manage', 'guard_name' => 'web']])]
    #[ScribeResponse(['message' => 'Permission name already exists'], 400)]
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $validated = $request->validated();

        // Check if another permission with same name exists for this guard
        $existingPermission = Permission::where([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $permission->guard_name,
        ])->where('id', '!=', $permission->id)->first();

        if ($existingPermission) {
            return response()->json([
                'message' => 'Permission name already exists for this guard',
            ], 400);
        }

        $permission->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $permission->guard_name,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => $permission->fresh(),
        ]);
    }

    #[Endpoint(
        title: 'Delete permission',
        description: 'Delete a permission (only if not assigned to any roles)'
    )]
    #[Authenticated]
    #[ScribeResponse(['message' => 'Permission deleted successfully'])]
    #[ScribeResponse(['message' => 'Cannot delete permission that is assigned to roles'], 400)]
    public function destroy(Permission $permission)
    {
        // Check if permission is assigned to roles
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete permission that is assigned to roles',
            ], 400);
        }

        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
