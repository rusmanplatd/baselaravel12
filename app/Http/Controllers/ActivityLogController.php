<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\AllowedFilter;

class ActivityLogController extends Controller
{
    protected ActivityLogExportService $exportService;

    public function __construct(ActivityLogExportService $exportService)
    {
        $this->middleware('auth');
        $this->exportService = $exportService;
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
            $baseQuery = Activity::query();
        } elseif ($canViewOrganization) {
            // Organization admins can see activities within their organizations
            $organizationIds = $user->activeOrganizations()->pluck('organizations.id');
            $baseQuery = Activity::whereIn('organization_id', $organizationIds)
                ->orWhere('causer_id', $user->id);
        } else {
            // Regular users can only see their own activities
            $baseQuery = Activity::forUser($user->id);
        }

        // Apply manual filters that need special permission checks
        if ($request->filled('organization_id') && $request->organization_id !== 'all') {
            // Only allow if user has permission to view this organization
            if ($canViewAll || ($canViewOrganization && $user->activeOrganizations()->where('organizations.id', $request->organization_id)->exists())) {
                $baseQuery->forOrganization($request->organization_id);
            }
        }

        if ($request->filled('causer_id') && $request->causer_id !== 'all') {
            // Only allow if user has permission to view other users' activities
            if ($canViewAll || $canViewOrganization) {
                $baseQuery->where('causer_id', $request->causer_id);
            }
        }

        // Use QueryBuilder for sorting and filtering
        $activities = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('resource', 'log_name'),
                AllowedFilter::callback('from_date', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('to_date', function ($query, $value) {
                    $query->where('created_at', '<=', $value . ' 23:59:59');
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('description', 'like', "%{$value}%")
                          ->orWhere('properties->search_terms', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts([
                'created_at',
                'log_name',
                'description',
                AllowedSort::field('causer_name', 'causer.name'),
                AllowedSort::field('organization_name', 'organization.name'),
                'event',
            ])
            ->defaultSort('-created_at')
            ->with(['causer', 'subject', 'organization'])
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
            'filters' => $request->only(['resource', 'organization_id', 'from_date', 'to_date', 'causer_id', 'search', 'sort']),
            'permissions' => [
                'canViewAll' => $canViewAll,
                'canViewOrganization' => $canViewOrganization,
                'canViewOwn' => $canViewOwn,
                'canExport' => $user->can('audit_log:admin'),
            ],
            'exportColumns' => $this->exportService->getAvailableColumns(),
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

    /**
     * Export all activity logs (with user permissions applied)
     */
    public function exportAll(Request $request)
    {
        $user = Auth::user();

        // Validate request
        $request->validate([
            'format' => ['required', Rule::in(['csv', 'json'])],
            'columns' => 'nullable|array',
            'columns.*' => 'string',
        ]);

        // Validate export permissions and limits
        $validation = $this->exportService->validateExportRequest($user);
        
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Export validation failed',
                'errors' => $validation['errors']
            ], 400);
        }

        try {
            $exportData = $this->exportService->exportAll(
                $user,
                $request->input('format', 'csv'),
                $request->input('columns', [])
            );

            return response($exportData['content'])
                ->header('Content-Type', $exportData['mime_type'])
                ->header('Content-Disposition', 'attachment; filename="' . $exportData['filename'] . '"')
                ->header('Content-Length', $exportData['size'])
                ->header('X-Total-Records', $exportData['total_records']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Export failed',
                'error' => 'An error occurred while generating the export file.'
            ], 500);
        }
    }

    /**
     * Export filtered activity logs
     */
    public function exportFiltered(Request $request)
    {
        $user = Auth::user();

        // Validate request
        $request->validate([
            'format' => ['required', Rule::in(['csv', 'json'])],
            'columns' => 'nullable|array',
            'columns.*' => 'string',
            'filters' => 'nullable|array',
            'filters.resource' => 'nullable|string',
            'filters.organization_id' => 'nullable|string',
            'filters.from_date' => 'nullable|date',
            'filters.to_date' => 'nullable|date|after_or_equal:filters.from_date',
            'filters.causer_id' => 'nullable|string',
            'filters.search' => 'nullable|string',
            'filters.event' => 'nullable|string',
            'filters.subject_type' => 'nullable|string',
        ]);

        $filters = $request->input('filters', []);

        // Validate export permissions and limits
        $validation = $this->exportService->validateExportRequest($user, $filters);
        
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Export validation failed',
                'errors' => $validation['errors'],
                'estimated_records' => $validation['estimated_records']
            ], 400);
        }

        try {
            $exportData = $this->exportService->exportFiltered(
                $user,
                $filters,
                $request->input('format', 'csv'),
                $request->input('columns', [])
            );

            return response($exportData['content'])
                ->header('Content-Type', $exportData['mime_type'])
                ->header('Content-Disposition', 'attachment; filename="' . $exportData['filename'] . '"')
                ->header('Content-Length', $exportData['size'])
                ->header('X-Total-Records', $exportData['total_records']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Export failed',
                'error' => 'An error occurred while generating the export file.'
            ], 500);
        }
    }

    /**
     * Validate export request and return estimated record count
     */
    public function validateExport(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'filters' => 'nullable|array',
        ]);

        $filters = $request->input('filters', []);
        $validation = $this->exportService->validateExportRequest($user, $filters);

        return response()->json([
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'estimated_records' => $validation['estimated_records'],
            'max_records' => 50000,
        ]);
    }

    /**
     * Get available export columns
     */
    public function getExportColumns()
    {
        return response()->json([
            'columns' => $this->exportService->getAvailableColumns(),
        ]);
    }
}