<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrganizationUnit::with(['organization', 'parentUnit', 'childUnits']);

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('unit_type')) {
            $query->where('unit_type', $request->unit_type);
        }

        if ($request->has('governance_only') && $request->governance_only) {
            $query->governance();
        }

        if ($request->has('operational_only') && $request->operational_only) {
            $query->operational();
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $units = $query->orderBy('sort_order')->orderBy('name')->get();

        return response()->json($units);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'unit_code' => 'required|string|unique:organization_units',
            'name' => 'required|string|max:255',
            'unit_type' => 'required|in:board_of_commissioners,board_of_directors,executive_committee,audit_committee,risk_committee,nomination_committee,remuneration_committee,division,department,section,team,branch_office,representative_office',
            'description' => 'nullable|string',
            'parent_unit_id' => 'nullable|exists:organization_units,id',
            'responsibilities' => 'nullable|array',
            'authorities' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $unit = OrganizationUnit::create($request->all());
        $unit->load(['organization', 'parentUnit', 'childUnits']);

        return response()->json($unit, 201);
    }

    public function show(OrganizationUnit $organizationUnit): JsonResponse
    {
        $organizationUnit->load([
            'organization',
            'parentUnit',
            'childUnits.positions',
            'positions.activeMemberships.user',
            'memberships.user',
        ]);

        return response()->json($organizationUnit);
    }

    public function update(Request $request, OrganizationUnit $organizationUnit): JsonResponse
    {
        $request->validate([
            'unit_code' => 'required|string|unique:organization_units,unit_code,'.$organizationUnit->id,
            'name' => 'required|string|max:255',
            'unit_type' => 'required|in:board_of_commissioners,board_of_directors,executive_committee,audit_committee,risk_committee,nomination_committee,remuneration_committee,division,department,section,team,branch_office,representative_office',
            'description' => 'nullable|string',
            'parent_unit_id' => 'nullable|exists:organization_units,id',
            'responsibilities' => 'nullable|array',
            'authorities' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($request->parent_unit_id && $request->parent_unit_id == $organizationUnit->id) {
            return response()->json([
                'message' => 'Unit cannot be its own parent',
            ], 400);
        }

        $organizationUnit->update($request->all());
        $organizationUnit->load(['organization', 'parentUnit', 'childUnits']);

        return response()->json($organizationUnit);
    }

    public function destroy(OrganizationUnit $organizationUnit): JsonResponse
    {
        if ($organizationUnit->childUnits()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete unit with child units. Please reassign or delete child units first.',
            ], 400);
        }

        if ($organizationUnit->positions()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete unit with positions. Please reassign or delete positions first.',
            ], 400);
        }

        $organizationUnit->delete();

        return response()->json(['message' => 'Organization unit deleted successfully']);
    }

    public function getHierarchy(Request $request): JsonResponse
    {
        $organizationId = $request->get('organization_id');

        if (! $organizationId) {
            return response()->json([
                'message' => 'organization_id is required',
            ], 400);
        }

        $rootUnits = OrganizationUnit::where('organization_id', $organizationId)
            ->whereNull('parent_unit_id')
            ->with(['childUnits' => function ($query) {
                $this->loadHierarchy($query);
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($rootUnits);
    }

    private function loadHierarchy($query)
    {
        $query->with(['childUnits' => function ($subQuery) {
            $this->loadHierarchy($subQuery);
        }])->orderBy('sort_order')->orderBy('name');
    }

    public function getByType(Request $request, string $type): JsonResponse
    {
        $validTypes = [
            'governance' => ['board_of_commissioners', 'board_of_directors', 'executive_committee', 'audit_committee', 'risk_committee', 'nomination_committee', 'remuneration_committee'],
            'operational' => ['division', 'department', 'section', 'team', 'branch_office', 'representative_office'],
        ];

        if (! array_key_exists($type, $validTypes)) {
            return response()->json([
                'message' => 'Invalid type. Must be governance or operational',
            ], 400);
        }

        $query = OrganizationUnit::whereIn('unit_type', $validTypes[$type])
            ->with(['organization', 'parentUnit', 'positions']);

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        $units = $query->active()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json($units);
    }
}
