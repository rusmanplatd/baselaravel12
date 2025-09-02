<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationPositionLevel\StoreOrganizationPositionLevelRequest;
use App\Http\Requests\OrganizationPositionLevel\UpdateOrganizationPositionLevelRequest;
use App\Models\OrganizationPositionLevel;
use Illuminate\Support\Facades\Auth;

class OrganizationPositionLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organizationPositionLevels = OrganizationPositionLevel::ordered()
            ->withCount('organizationPositions')
            ->with('updatedBy:id,name')
            ->get();

        return response()->json([
            'data' => $organizationPositionLevels,
            'message' => 'Organization position levels retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationPositionLevelRequest $request)
    {
        $validated = $request->validated();

        $organizationPositionLevel = OrganizationPositionLevel::create([
            ...$validated,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $organizationPositionLevel,
            'message' => 'Organization position level created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(OrganizationPositionLevel $organizationPositionLevel)
    {
        $organizationPositionLevel->load(['organizationPositions.organizationUnit']);

        return response()->json([
            'data' => $organizationPositionLevel,
            'message' => 'Organization position level retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationPositionLevelRequest $request, OrganizationPositionLevel $organizationPositionLevel)
    {
        $validated = $request->validated();

        $organizationPositionLevel->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $organizationPositionLevel->fresh(),
            'message' => 'Organization position level updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrganizationPositionLevel $organizationPositionLevel)
    {
        if ($organizationPositionLevel->organizationPositions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete organization position level that is being used by organization positions.',
            ], 422);
        }

        $organizationPositionLevel->delete();

        return response()->json([
            'message' => 'Organization position level deleted successfully',
        ]);
    }

    /**
     * Get active organization position levels.
     */
    public function getActive()
    {
        $organizationPositionLevels = OrganizationPositionLevel::active()
            ->ordered()
            ->get(['id', 'code', 'name', 'hierarchy_level']);

        return response()->json([
            'data' => $organizationPositionLevels,
            'message' => 'Active organization position levels retrieved successfully',
        ]);
    }

    /**
     * Get organization position levels by hierarchy.
     */
    public function getByHierarchy()
    {
        $organizationPositionLevels = OrganizationPositionLevel::active()
            ->orderBy('hierarchy_level')
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'hierarchy_level', 'description']);

        return response()->json([
            'data' => $organizationPositionLevels,
            'message' => 'Organization position levels by hierarchy retrieved successfully',
        ]);
    }
}
