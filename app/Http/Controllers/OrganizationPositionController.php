<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationPosition\StoreOrganizationPositionRequest;
use App\Http\Requests\OrganizationPosition\UpdateOrganizationPositionRequest;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationPositionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:org_position:read')->only(['index', 'show']);
        $this->middleware('permission:org_position:write')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:org_position:delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        // Use the new API-driven table interface
        return Inertia::render('OrganizationPositions/IndexApi');
    }

    public function create()
    {
        $organizationUnits = OrganizationUnit::with('organization')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        return Inertia::render('OrganizationPositions/Create', [
            'organizationUnits' => $organizationUnits,
        ]);
    }

    public function store(StoreOrganizationPositionRequest $request)
    {
        $validated = $request->validated();

        OrganizationPosition::create($validated);

        return redirect()->route('organization-positions.index')
            ->with('success', 'Organization position created successfully.');
    }

    public function show(OrganizationPosition $organizationPosition)
    {
        $organizationPosition->load([
            'organizationUnit.organization',
            'activeMemberships.user',
            'memberships.user',
        ]);

        return Inertia::render('OrganizationPositions/Show', [
            'position' => $organizationPosition,
        ]);
    }

    public function edit(OrganizationPosition $organizationPosition)
    {
        $organizationUnits = OrganizationUnit::with('organization')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        return Inertia::render('OrganizationPositions/Edit', [
            'position' => $organizationPosition,
            'organizationUnits' => $organizationUnits,
        ]);
    }

    public function update(UpdateOrganizationPositionRequest $request, OrganizationPosition $organizationPosition)
    {
        $validated = $request->validated();

        $organizationPosition->update($validated);

        return redirect()->route('organization-positions.index')
            ->with('success', 'Organization position updated successfully.');
    }

    public function destroy(OrganizationPosition $organizationPosition)
    {
        if ($organizationPosition->memberships()->count() > 0) {
            return redirect()->back()
                ->withErrors(['position' => 'Cannot delete position with active or historical memberships. Please reassign or remove memberships first.']);
        }

        $organizationPosition->delete();

        return redirect()->route('organization-positions.index')
            ->with('success', 'Organization position deleted successfully.');
    }
}
