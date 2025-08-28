<?php

namespace App\Http\Controllers;

use App\Models\Auth\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view users')->only(['index', 'show']);
        $this->middleware('permission:edit users')->only(['edit', 'update', 'assignRoles']);
        $this->middleware('permission:delete users')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->with(['roles'])
            ->withCount('roles');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        $users = $query->orderBy('name')->paginate(10);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(User $user)
    {
        $user->load(['roles.permissions', 'organizationMemberships.organization']);

        return Inertia::render('Users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function assignRoles(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:sys_roles,name',
        ]);

        $roles = $request->input('roles', []);

        // Set team context for role assignment - use the first organization of the authenticated user
        $currentUserOrganizations = auth()->user()->organizations;
        $teamId = $currentUserOrganizations->isNotEmpty()
            ? $currentUserOrganizations->first()->id
            : \App\Models\Organization::first()?->id;

        if ($teamId) {
            setPermissionsTeamId($teamId);
        }
        $user->syncRoles($roles);

        ActivityLogService::logSystem('roles_updated', 'User roles updated for: '.$user->name, [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'roles_assigned' => $roles,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $user);

        return redirect()->route('users.show', $user)
            ->with('success', 'User roles updated successfully.');
    }

    public function destroy(User $user)
    {
        $userName = $user->name;
        $userEmail = $user->email;

        // Remove all roles before deletion
        $currentUserOrganizations = auth()->user()->organizations;
        $teamId = $currentUserOrganizations->isNotEmpty()
            ? $currentUserOrganizations->first()->id
            : \App\Models\Organization::first()?->id;

        if ($teamId) {
            setPermissionsTeamId($teamId);
        }
        $user->syncRoles([]);

        $user->delete();

        ActivityLogService::logSystem('deleted', 'User deleted: '.$userName, [
            'user_name' => $userName,
            'user_email' => $userEmail,
        ]);

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
