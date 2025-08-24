<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => $this->getUserPermissions($request->user()),
                'roles' => $this->getUserRoles($request->user()),
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Get user permissions, handling team-based permissions if enabled
     */
    private function getUserPermissions($user): \Illuminate\Support\Collection
    {
        if (! $user) {
            return collect();
        }

        // If teams are enabled, try to get permissions with team context
        if (config('permission.teams', false)) {
            // Get the default organization for team context
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();

            if ($defaultOrg) {
                $permissions = $user->getAllPermissions($defaultOrg->id);
                if ($permissions->count() > 0) {
                    return $permissions->pluck('name');
                }
            }

            // Also try without team context as fallback
            $permissions = $user->getAllPermissions();
            if ($permissions->count() > 0) {
                return $permissions->pluck('name');
            }

            // If still no permissions, collect permissions from all team contexts
            $allPermissions = collect();
            $teamRoles = \Illuminate\Support\Facades\DB::table('sys_model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $user->id)
                ->whereNotNull('team_id')
                ->get();

            foreach ($teamRoles as $teamRole) {
                $role = \App\Models\Auth\Role::find($teamRole->role_id);
                if ($role) {
                    $rolePermissions = $role->permissions->pluck('name');
                    $allPermissions = $allPermissions->merge($rolePermissions);
                }
            }

            return $allPermissions->unique();
        }

        // Non-team permissions
        return $user->getAllPermissions()->pluck('name');
    }

    /**
     * Get user roles, handling team-based roles if enabled
     */
    private function getUserRoles($user): \Illuminate\Support\Collection
    {
        if (! $user) {
            return collect();
        }

        // If teams are enabled, try to get roles with team context
        if (config('permission.teams', false)) {
            // Get the default organization for team context
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();

            if ($defaultOrg) {
                $roles = $user->getRoleNames($defaultOrg->id);
                if ($roles->count() > 0) {
                    return $roles;
                }
            }

            // Also try without team context as fallback
            $roles = $user->getRoleNames();
            if ($roles->count() > 0) {
                return $roles;
            }

            // If still no roles, collect roles from all team contexts
            $allRoles = collect();
            $teamRoles = \Illuminate\Support\Facades\DB::table('sys_model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $user->id)
                ->whereNotNull('team_id')
                ->get();

            foreach ($teamRoles as $teamRole) {
                $role = \App\Models\Auth\Role::find($teamRole->role_id);
                if ($role) {
                    $allRoles->push($role->name);
                }
            }

            return $allRoles->unique();
        }

        // Non-team roles
        return $user->getRoleNames();
    }
}
