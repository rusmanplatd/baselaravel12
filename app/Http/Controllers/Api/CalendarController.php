<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'owner_type' => 'nullable|in:user,organization,project',
            'owner_id' => 'nullable|string',
            'visibility' => 'nullable|in:public,private,shared',
        ]);

        $user = Auth::user();
        $query = Calendar::visible($user)->active()->with(['calendarable', 'permissions.user']);

        if ($request->owner_type && $request->owner_id) {
            $ownerClass = match ($request->owner_type) {
                'user' => User::class,
                'organization' => Organization::class,
                'project' => Project::class,
            };
            
            $query->where('calendarable_type', $ownerClass)
                  ->where('calendarable_id', $request->owner_id);
        }

        if ($request->visibility) {
            $query->where('visibility', $request->visibility);
        }

        $calendars = $query->get();

        return response()->json([
            'calendars' => $calendars->map(function ($calendar) {
                return [
                    'id' => $calendar->id,
                    'name' => $calendar->name,
                    'description' => $calendar->description,
                    'color' => $calendar->color,
                    'timezone' => $calendar->timezone,
                    'visibility' => $calendar->visibility,
                    'owner_type' => class_basename($calendar->calendarable_type),
                    'owner_id' => $calendar->calendarable_id,
                    'owner_name' => $calendar->owner_name,
                    'events_count' => $calendar->activeEvents()->count(),
                    'settings' => $calendar->settings,
                    'created_at' => $calendar->created_at,
                    'updated_at' => $calendar->updated_at,
                ];
            }),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'timezone' => 'nullable|string|timezone',
            'owner_type' => 'required|in:user,organization,project',
            'owner_id' => 'required|string',
            'visibility' => 'nullable|in:public,private,shared',
            'settings' => 'nullable|array',
        ]);

        $ownerClass = match ($validated['owner_type']) {
            'user' => User::class,
            'organization' => Organization::class,
            'project' => Project::class,
        };

        $owner = $ownerClass::findOrFail($validated['owner_id']);
        
        // Check permissions to create calendar for this owner
        $this->authorizeCalendarCreation($owner);

        $calendar = Calendar::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#3498db',
            'timezone' => $validated['timezone'] ?? 'UTC',
            'calendarable_id' => $owner->id,
            'calendarable_type' => $ownerClass,
            'visibility' => $validated['visibility'] ?? 'private',
            'settings' => $validated['settings'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $calendar->load('calendarable');

        return response()->json([
            'calendar' => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'description' => $calendar->description,
                'color' => $calendar->color,
                'timezone' => $calendar->timezone,
                'visibility' => $calendar->visibility,
                'owner_type' => class_basename($calendar->calendarable_type),
                'owner_id' => $calendar->calendarable_id,
                'owner_name' => $calendar->owner_name,
                'settings' => $calendar->settings,
                'created_at' => $calendar->created_at,
            ],
        ], 201);
    }

    public function show(Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canView($user)) {
            return response()->json(['error' => 'Not authorized to view this calendar'], 403);
        }

        $calendar->load(['calendarable', 'permissions.user', 'events' => function($query) {
            $query->active()->orderBy('starts_at');
        }]);

        return response()->json([
            'calendar' => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'description' => $calendar->description,
                'color' => $calendar->color,
                'timezone' => $calendar->timezone,
                'visibility' => $calendar->visibility,
                'owner_type' => class_basename($calendar->calendarable_type),
                'owner_id' => $calendar->calendarable_id,
                'owner_name' => $calendar->owner_name,
                'events_count' => $calendar->events->count(),
                'permissions' => $calendar->permissions->map(function($permission) {
                    return [
                        'user_id' => $permission->user_id,
                        'user_name' => $permission->user->name,
                        'permission' => $permission->permission,
                        'granted_at' => $permission->created_at,
                    ];
                }),
                'settings' => $calendar->settings,
                'created_at' => $calendar->created_at,
                'updated_at' => $calendar->updated_at,
            ],
        ]);
    }

    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canEdit($user)) {
            return response()->json(['error' => 'Not authorized to edit this calendar'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'timezone' => 'nullable|string|timezone',
            'visibility' => 'sometimes|in:public,private,shared',
            'settings' => 'nullable|array',
        ]);

        $calendar->update(array_merge($validated, ['updated_by' => Auth::id()]));

        return response()->json([
            'calendar' => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'description' => $calendar->description,
                'color' => $calendar->color,
                'timezone' => $calendar->timezone,
                'visibility' => $calendar->visibility,
                'owner_type' => class_basename($calendar->calendarable_type),
                'owner_id' => $calendar->calendarable_id,
                'owner_name' => $calendar->owner_name,
                'settings' => $calendar->settings,
                'updated_at' => $calendar->updated_at,
            ],
        ]);
    }

    public function destroy(Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canAdmin($user)) {
            return response()->json(['error' => 'Not authorized to delete this calendar'], 403);
        }

        $calendar->update(['is_active' => false, 'updated_by' => Auth::id()]);

        return response()->json(['message' => 'Calendar deactivated successfully']);
    }

    public function shareCalendar(Request $request, Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canAdmin($user)) {
            return response()->json(['error' => 'Not authorized to share this calendar'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'permission' => 'required|in:read,write,admin',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $permission = $calendar->shareWith($targetUser, $validated['permission'], $user);

        return response()->json([
            'message' => 'Calendar shared successfully',
            'permission' => [
                'user_id' => $permission->user_id,
                'user_name' => $targetUser->name,
                'permission' => $permission->permission,
                'granted_at' => $permission->created_at,
            ],
        ]);
    }

    public function revokeAccess(Request $request, Calendar $calendar): JsonResponse
    {
        $user = Auth::user();
        
        if (!$calendar->canAdmin($user)) {
            return response()->json(['error' => 'Not authorized to revoke access to this calendar'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $calendar->revokeAccess($targetUser);

        return response()->json(['message' => 'Access revoked successfully']);
    }

    private function authorizeCalendarCreation($owner): void
    {
        $user = Auth::user();

        if ($owner instanceof User) {
            // Users can only create calendars for themselves
            if ($owner->id !== $user->id) {
                abort(403, 'Not authorized to create calendar for this user');
            }
        } elseif ($owner instanceof Organization) {
            // Check if user has permission in this organization
            if (!$owner->activeUsers()->where('user_id', $user->id)->exists()) {
                abort(403, 'Not authorized to create calendar for this organization');
            }
        } elseif ($owner instanceof Project) {
            // Check if user is a member of this project
            if (!$owner->users()->where('user_id', $user->id)->exists()) {
                abort(403, 'Not authorized to create calendar for this project');
            }
        }
    }
}
