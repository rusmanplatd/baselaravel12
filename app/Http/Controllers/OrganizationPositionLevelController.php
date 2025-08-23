<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationPositionLevel\StoreOrganizationPositionLevelRequest;
use App\Http\Requests\OrganizationPositionLevel\UpdateOrganizationPositionLevelRequest;
use App\Models\OrganizationPositionLevel;
use Illuminate\Support\Facades\Auth;
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
    public function store(StoreOrganizationPositionLevelRequest $request)
    {
        $validated = $request->validated();

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
    public function update(UpdateOrganizationPositionLevelRequest $request, OrganizationPositionLevel $organizationPositionLevel)
    {
        $validated = $request->validated();

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
