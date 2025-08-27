<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Check permissions based on user role
        $canViewAll = $user->can('audit_log:admin') || $user->can('audit_log:read');
        $canViewOrganization = $user->can('audit_log:read');
        $canViewOwn = $user->can('audit_log:read') || !$canViewAll && !$canViewOrganization;

        // Build base query with automatic role-based filtering
        if ($canViewAll) {
            // Super admins can see all activities
            $query = Activity::query();
        } elseif ($canViewOrganization) {
            // Organization admins can see activities within their organizations
            $organizationIds = $user->activeOrganizations()->pluck('organizations.id');
            $query = Activity::whereIn('organization_id', $organizationIds)
                ->orWhere('causer_id', $user->id);
        } else {
            // Regular users can only see their own activities
            $query = Activity::forUser($user->id);
        }

        // Apply manual filters
        if ($request->filled('resource')) {
            $query->where('log_name', $request->resource);
        }

        if ($request->filled('organization_id')) {
            // Only allow if user has permission to view this organization
            if ($canViewAll || ($canViewOrganization && $user->activeOrganizations()->where('organizations.id', $request->organization_id)->exists())) {
                $query->forOrganization($request->organization_id);
            }
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        if ($request->filled('causer_id')) {
            // Only allow if user has permission to view other users' activities
            if ($canViewAll || $canViewOrganization) {
                $query->where('causer_id', $request->causer_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('properties->search_terms', 'like', "%{$search}%");
            });
        }

        // Get activities with relationships
        $activities = $query->with(['causer', 'subject', 'organization'])
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->appends($request->query());

        // Get filter options
        $resources = $this->getAvailableResources($user, $canViewAll, $canViewOrganization);
        $organizations = $this->getAvailableOrganizations($user, $canViewAll, $canViewOrganization);
        $users = $this->getAvailableUsers($user, $canViewAll, $canViewOrganization);

        return Inertia::render('ActivityLog/Index', [
            'activities' => $activities,
            'resources' => $resources,
            'organizations' => $organizations,
            'users' => $users,
            'filters' => $request->only(['resource', 'organization_id', 'from_date', 'to_date', 'causer_id', 'search']),
            'permissions' => [
                'canViewAll' => $canViewAll,
                'canViewOrganization' => $canViewOrganization,
                'canViewOwn' => $canViewOwn,
            ],
        ]);
    }

    public function show(Activity $activity)
    {
        $user = Auth::user();
        
        // Check if user can view this specific activity
        if (!$this->canViewActivity($activity, $user)) {
            abort(403, 'You do not have permission to view this activity.');
        }

        $activity->load(['causer', 'subject', 'organization']);

        return Inertia::render('ActivityLog/Show', [
            'activity' => $activity,
        ]);
    }

    private function canViewActivity(Activity $activity, User $user): bool
    {
        // Super admin can view all
        if ($user->can('audit_log:admin')) {
            return true;
        }

        // Organization admin can view activities in their organizations
        if ($user->can('audit_log:read')) {
            if ($activity->organization_id && $user->activeOrganizations()->where('organizations.id', $activity->organization_id)->exists()) {
                return true;
            }
        }

        // Users can view their own activities
        return $activity->causer_id === $user->id;
    }

    private function getAvailableResources(User $user, bool $canViewAll, bool $canViewOrganization): array
    {
        if ($canViewAll) {
            $query = Activity::select('log_name')->distinct();
        } elseif ($canViewOrganization) {
            $organizationIds = $user->activeOrganizations()->pluck('organizations.id');
            $query = Activity::select('log_name')
                ->where(function ($q) use ($organizationIds, $user) {
                    $q->whereIn('organization_id', $organizationIds)
                      ->orWhere('causer_id', $user->id);
                })
                ->distinct();
        } else {
            $query = Activity::select('log_name')
                ->forUser($user->id)
                ->distinct();
        }

        return $query->pluck('log_name')
            ->filter()
            ->map(function ($logName) {
                return [
                    'value' => $logName,
                    'label' => ucfirst(str_replace('_', ' ', $logName)),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getAvailableOrganizations(User $user, bool $canViewAll, bool $canViewOrganization): array
    {
        if ($canViewAll) {
            return Organization::select('id', 'name', 'organization_code')
                ->orderBy('name')
                ->get()
                ->map(function ($org) {
                    return [
                        'value' => $org->id,
                        'label' => $org->name . ' (' . $org->organization_code . ')',
                    ];
                })
                ->toArray();
        } elseif ($canViewOrganization) {
            return $user->activeOrganizations()
                ->select('organizations.id', 'organizations.name', 'organizations.organization_code')
                ->orderBy('organizations.name')
                ->get()
                ->map(function ($org) {
                    return [
                        'value' => $org->id,
                        'label' => $org->name . ' (' . $org->organization_code . ')',
                    ];
                })
                ->toArray();
        }

        return [];
    }

    private function getAvailableUsers(User $user, bool $canViewAll, bool $canViewOrganization): array
    {
        if (!$canViewAll && !$canViewOrganization) {
            return [];
        }

        if ($canViewAll) {
            return User::select('id', 'name', 'email')
                ->orderBy('name')
                ->get()
                ->map(function ($u) {
                    return [
                        'value' => $u->id,
                        'label' => $u->name . ' (' . $u->email . ')',
                    ];
                })
                ->toArray();
        }

        // Organization admins can see users in their organizations
        $organizationIds = $user->activeOrganizations()->pluck('organizations.id');
        $userIds = DB::table('organization_memberships')
            ->whereIn('organization_id', $organizationIds)
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique();

        return User::whereIn('id', $userIds)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                return [
                    'value' => $u->id,
                    'label' => $u->name . ' (' . $u->email . ')',
                ];
            })
            ->toArray();
    }
}