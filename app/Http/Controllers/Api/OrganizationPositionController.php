<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationPosition\StoreOrganizationPositionRequest;
use App\Http\Requests\OrganizationPosition\UpdateOrganizationPositionRequest;
use App\Models\OrganizationPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OrganizationPositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50, 100]) ? $perPage : 15;

        $positions = QueryBuilder::for(OrganizationPosition::class)
            ->allowedFilters([
                AllowedFilter::partial('position_code'),
                AllowedFilter::partial('title'),
                AllowedFilter::partial('job_description'),
                AllowedFilter::exact('organization_unit_id'),
                AllowedFilter::exact('organization_position_level_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::scope('board'),
                AllowedFilter::scope('executive'),
                AllowedFilter::scope('management'),
                AllowedFilter::scope('active'),
                AllowedFilter::scope('available'),
                AllowedFilter::callback('min_salary_from', function ($query, $value) {
                    return $query->where('min_salary', '>=', $value);
                }),
                AllowedFilter::callback('max_salary_to', function ($query, $value) {
                    return $query->where('max_salary', '<=', $value);
                }),
                AllowedFilter::callback('position_level', function ($query, $value) {
                    return $query->whereHas('organizationPositionLevel', function ($q) use ($value) {
                        $q->where('hierarchy_level', $value);
                    });
                }),
            ])
            ->allowedSorts([
                'position_code',
                'title',
                'min_salary',
                'max_salary',
                'max_incumbents',
                'is_active',
                'created_at',
                'updated_at',
                AllowedSort::callback('level', function ($query, $descending, $property) {
                    return $query->join('organization_position_levels', 'organization_positions.organization_position_level_id', '=', 'organization_position_levels.id')
                        ->orderBy('organization_position_levels.hierarchy_level', $descending ? 'desc' : 'asc');
                }),
                AllowedSort::callback('unit', function ($query, $descending, $property) {
                    return $query->join('organization_units', 'organization_positions.organization_unit_id', '=', 'organization_units.id')
                        ->orderBy('organization_units.name', $descending ? 'desc' : 'asc');
                }),
            ])
            ->defaultSort('title')
            ->with(['organizationUnit.organization', 'activeMemberships.user', 'organizationPositionLevel', 'updatedBy'])
            ->withCount(['activeMemberships'])
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($positions);
    }

    public function list(Request $request): JsonResponse
    {
        $positions = QueryBuilder::for(OrganizationPosition::class)
            ->allowedFilters([
                AllowedFilter::partial('title'),
                AllowedFilter::exact('organization_unit_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::scope('active'),
                AllowedFilter::scope('available'),
            ])
            ->allowedSorts(['title', 'position_code'])
            ->defaultSort('title')
            ->select(['id', 'position_code', 'title', 'organization_unit_id', 'max_incumbents', 'is_active'])
            ->with(['organizationUnit:id,name', 'organizationPositionLevel:id,name,code'])
            ->withCount(['activeMemberships'])
            ->limit($request->input('limit', 100))
            ->get();

        return response()->json($positions);
    }

    public function store(StoreOrganizationPositionRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $validatedData['created_by'] = auth()->id();
        $validatedData['updated_by'] = auth()->id();

        // Handle position level mapping
        if (isset($validatedData['position_level'])) {
            $positionLevel = \App\Models\OrganizationPositionLevel::where('code', $validatedData['position_level'])->first();
            if ($positionLevel) {
                $validatedData['organization_position_level_id'] = $positionLevel->id;
            }
            unset($validatedData['position_level']);
        }

        $position = OrganizationPosition::create($validatedData);
        $position->load(['organizationUnit.organization', 'activeMemberships.user', 'organizationPositionLevel']);

        activity()
            ->performedOn($position)
            ->causedBy(auth()->user())
            ->log('Created organization position '.$position->title);

        return response()->json($position, 201);
    }

    public function show(OrganizationPosition $organizationPosition): JsonResponse
    {
        $organizationPosition->load([
            'organizationUnit.organization',
            'activeMemberships.user',
            'memberships.user',
        ]);

        return response()->json($organizationPosition);
    }

    public function update(UpdateOrganizationPositionRequest $request, OrganizationPosition $organizationPosition): JsonResponse
    {
        $validatedData = $request->validated();
        $validatedData['updated_by'] = auth()->id();

        // Handle position level mapping
        if (isset($validatedData['position_level'])) {
            $positionLevel = \App\Models\OrganizationPositionLevel::where('code', $validatedData['position_level'])->first();
            if ($positionLevel) {
                $validatedData['organization_position_level_id'] = $positionLevel->id;
            }
            unset($validatedData['position_level']);
        }

        $organizationPosition->update($validatedData);
        $organizationPosition->load(['organizationUnit.organization', 'activeMemberships.user', 'organizationPositionLevel']);

        activity()
            ->performedOn($organizationPosition)
            ->causedBy(auth()->user())
            ->log('Updated organization position '.$organizationPosition->title);

        return response()->json($organizationPosition);
    }

    public function destroy(OrganizationPosition $organizationPosition): JsonResponse
    {
        if ($organizationPosition->memberships()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete position with active or historical memberships. Please reassign or remove memberships first.',
            ], 400);
        }

        $positionTitle = $organizationPosition->title;

        activity()
            ->performedOn($organizationPosition)
            ->causedBy(auth()->user())
            ->log('Deleted organization position '.$positionTitle);

        $organizationPosition->delete();

        return response()->json(['message' => 'Organization position deleted successfully']);
    }

    public function getAvailablePositions(Request $request): JsonResponse
    {
        $query = OrganizationPosition::whereRaw('(SELECT COUNT(*) FROM organization_memberships WHERE organization_position_id = organization_positions.id AND status = "active") < max_incumbents')
            ->with(['organizationUnit.organization', 'activeMemberships.user', 'organizationPositionLevel'])
            ->leftJoin('organization_position_levels', 'organization_positions.organization_position_level_id', '=', 'organization_position_levels.id');

        if ($request->has('organization_id')) {
            $query->whereHas('organizationUnit', function ($q) use ($request) {
                $q->where('organization_id', $request->organization_id);
            });
        }

        if ($request->has('position_level')) {
            $query->where('organization_position_levels.hierarchy_level', $request->position_level);
        }

        $positions = $query->active()
            ->select('organization_positions.*')
            ->orderBy('organization_position_levels.hierarchy_level')
            ->orderBy('organization_positions.title')
            ->get();

        return response()->json($positions);
    }

    public function getByLevel(Request $request, string $level): JsonResponse
    {
        $validLevels = [
            'board_member', 'c_level', 'vice_president', 'director',
            'senior_manager', 'manager', 'assistant_manager', 'supervisor',
            'senior_staff', 'staff', 'junior_staff',
        ];

        if (! in_array($level, $validLevels)) {
            return response()->json([
                'message' => 'Invalid position level',
            ], 400);
        }

        $query = OrganizationPosition::with(['organizationUnit.organization', 'activeMemberships.user', 'organizationPositionLevel'])
            ->leftJoin('organization_position_levels', 'organization_positions.organization_position_level_id', '=', 'organization_position_levels.id')
            ->where('organization_position_levels.code', $level);

        if ($request->has('organization_id')) {
            $query->whereHas('organizationUnit', function ($q) use ($request) {
                $q->where('organization_id', $request->organization_id);
            });
        }

        $positions = $query->active()
            ->select('organization_positions.*')
            ->orderBy('organization_positions.title')
            ->get();

        return response()->json($positions);
    }

    public function getIncumbents(OrganizationPosition $organizationPosition): JsonResponse
    {
        $incumbents = $organizationPosition->getCurrentIncumbents();

        return response()->json([
            'position' => $organizationPosition->title,
            'max_incumbents' => $organizationPosition->max_incumbents,
            'current_incumbents' => $incumbents->count(),
            'available_slots' => $organizationPosition->getAvailableSlots(),
            'incumbents' => $incumbents,
        ]);
    }
}
