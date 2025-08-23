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
        $this->middleware('permission:position.view')->only(['index', 'show']);
        $this->middleware('permission:position.create')->only(['create', 'store']);
        $this->middleware('permission:position.edit')->only(['edit', 'update']);
        $this->middleware('permission:position.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = OrganizationPosition::query()
            ->with(['organizationUnit.organization', 'activeMemberships.user'])
            ->withCount(['activeMemberships']);

        if ($request->filled('organization_unit_id')) {
            $query->where('organization_unit_id', $request->organization_unit_id);
        }

        if ($request->filled('position_level')) {
            $query->where('position_level', $request->position_level);
        }

        $positions = $query->orderBy('position_level')->orderBy('title')->paginate(10);

        $organizationUnits = OrganizationUnit::with('organization')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        return Inertia::render('OrganizationPositions/Index', [
            'positions' => $positions,
            'organizationUnits' => $organizationUnits,
            'filters' => $request->only(['organization_unit_id', 'position_level']),
        ]);
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
