<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationPositionLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrganizationPositionLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organizationPositionLevels = OrganizationPositionLevel::ordered()
            ->withCount('organizationPositions')
            ->get();

        return response()->json([
            'data' => $organizationPositionLevels,
            'message' => 'Organization position levels retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:organization_position_levels',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hierarchy_level' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer',
        ]);

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
    public function update(Request $request, OrganizationPositionLevel $organizationPositionLevel)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('organization_position_levels')->ignore($organizationPositionLevel)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hierarchy_level' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer',
        ]);

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
