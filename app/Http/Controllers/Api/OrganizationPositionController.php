<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationPosition\StoreOrganizationPositionRequest;
use App\Http\Requests\OrganizationPosition\UpdateOrganizationPositionRequest;
use App\Models\OrganizationPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationPositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrganizationPosition::with(['organizationUnit.organization', 'activeMemberships.user', 'organizationPositionLevel'])
            ->leftJoin('organization_position_levels', 'organization_positions.organization_position_level_id', '=', 'organization_position_levels.id');

        if ($request->has('organization_unit_id')) {
            $query->where('organization_positions.organization_unit_id', $request->organization_unit_id);
        }

        if ($request->has('position_level')) {
            $query->where('organization_position_levels.hierarchy_level', $request->position_level);
        }

        if ($request->has('board_only') && $request->board_only) {
            $query->board();
        }

        if ($request->has('executive_only') && $request->executive_only) {
            $query->executive();
        }

        if ($request->has('management_only') && $request->management_only) {
            $query->management();
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('available_only') && $request->available_only) {
            $query->whereRaw('(SELECT COUNT(*) FROM organization_memberships WHERE organization_position_id = organization_positions.id AND status = "active") < max_incumbents');
        }

        $positions = $query->select('organization_positions.*')
            ->orderBy('organization_position_levels.hierarchy_level')
            ->orderBy('organization_positions.title')
            ->get();

        return response()->json($positions);
    }

    public function store(StoreOrganizationPositionRequest $request): JsonResponse
    {

        $position = OrganizationPosition::create($request->all());
        $position->load(['organizationUnit.organization', 'activeMemberships.user']);

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

        $organizationPosition->update($request->all());
        $organizationPosition->load(['organizationUnit.organization', 'activeMemberships.user']);

        return response()->json($organizationPosition);
    }

    public function destroy(OrganizationPosition $organizationPosition): JsonResponse
    {
        if ($organizationPosition->memberships()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete position with active or historical memberships. Please reassign or remove memberships first.',
            ], 400);
        }

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
