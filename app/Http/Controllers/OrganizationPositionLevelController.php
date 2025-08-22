<?php

namespace App\Http\Controllers;

use App\Models\OrganizationPositionLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationPositionLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $organizationPositionLevels = OrganizationPositionLevel::ordered()->get();

        return Inertia::render('OrganizationPositionLevels/Index', [
            'organizationPositionLevels' => $organizationPositionLevels,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('OrganizationPositionLevels/Create');
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

        OrganizationPositionLevel::create([
            ...$validated,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('organization-position-levels.index')
            ->with('success', 'Organization position level created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(OrganizationPositionLevel $organizationPositionLevel)
    {
        $organizationPositionLevel->load(['organizationPositions.organizationUnit']);

        return Inertia::render('OrganizationPositionLevels/Show', [
            'organizationPositionLevel' => $organizationPositionLevel,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OrganizationPositionLevel $organizationPositionLevel)
    {
        return Inertia::render('OrganizationPositionLevels/Edit', [
            'organizationPositionLevel' => $organizationPositionLevel,
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

        return redirect()->route('organization-position-levels.index')
            ->with('success', 'Organization position level updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrganizationPositionLevel $organizationPositionLevel)
    {
        if ($organizationPositionLevel->organizationPositions()->exists()) {
            return redirect()->route('organization-position-levels.index')
                ->with('error', 'Cannot delete organization position level that is being used by organization positions.');
        }

        $organizationPositionLevel->delete();

        return redirect()->route('organization-position-levels.index')
            ->with('success', 'Organization position level deleted successfully.');
    }

    /**
     * Get organization position levels for API/select options.
     */
    public function api()
    {
        return response()->json([
            'data' => OrganizationPositionLevel::active()->ordered()->get(['id', 'code', 'name', 'hierarchy_level']),
        ]);
    }
}
