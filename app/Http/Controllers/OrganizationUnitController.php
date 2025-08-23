<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationUnit\StoreOrganizationUnitRequest;
use App\Http\Requests\OrganizationUnit\UpdateOrganizationUnitRequest;
use App\Models\Organization;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationUnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:unit.view')->only(['index', 'show', 'governance', 'operational']);
        $this->middleware('permission:unit.create')->only(['create', 'store']);
        $this->middleware('permission:unit.edit')->only(['edit', 'update']);
        $this->middleware('permission:unit.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = OrganizationUnit::query()
            ->with(['organization', 'parentUnit', 'positions'])
            ->withCount(['childUnits', 'positions']);

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->filled('unit_type')) {
            $query->where('unit_type', $request->unit_type);
        }

        $units = $query->orderBy('sort_order')->orderBy('name')->paginate(10);

        $organizations = Organization::orderBy('name')->get(['id', 'name', 'organization_type']);

        return Inertia::render('OrganizationUnits/Index', [
            'units' => $units,
            'organizations' => $organizations,
            'filters' => $request->only(['organization_id', 'unit_type']),
        ]);
    }

    public function create()
    {
        $organizations = Organization::orderBy('name')->get(['id', 'name', 'organization_type']);
        $parentUnits = OrganizationUnit::orderBy('name')->get(['id', 'name', 'organization_id']);

        return Inertia::render('OrganizationUnits/Create', [
            'organizations' => $organizations,
            'parentUnits' => $parentUnits,
        ]);
    }

    public function store(StoreOrganizationUnitRequest $request)
    {
        $validated = $request->validated();

        OrganizationUnit::create($validated);

        return redirect()->route('organization-units.index')
            ->with('success', 'Organization unit created successfully.');
    }

    public function show(OrganizationUnit $organizationUnit)
    {
        $organizationUnit->load([
            'organization',
            'parentUnit',
            'childUnits.positions',
            'positions.activeMemberships.user',
            'memberships.user',
        ]);

        return Inertia::render('OrganizationUnits/Show', [
            'unit' => $organizationUnit,
        ]);
    }

    public function edit(OrganizationUnit $organizationUnit)
    {
        $organizations = Organization::orderBy('name')->get(['id', 'name', 'organization_type']);
        $parentUnits = OrganizationUnit::where('id', '!=', $organizationUnit->id)
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        return Inertia::render('OrganizationUnits/Edit', [
            'unit' => $organizationUnit,
            'organizations' => $organizations,
            'parentUnits' => $parentUnits,
        ]);
    }

    public function update(UpdateOrganizationUnitRequest $request, OrganizationUnit $organizationUnit)
    {
        $validated = $request->validated();

        $organizationUnit->update($validated);

        return redirect()->route('organization-units.index')
            ->with('success', 'Organization unit updated successfully.');
    }

    public function destroy(OrganizationUnit $organizationUnit)
    {
        if ($organizationUnit->childUnits()->count() > 0) {
            return redirect()->back()
                ->withErrors(['unit' => 'Cannot delete unit with child units. Please reassign or delete child units first.']);
        }

        if ($organizationUnit->positions()->count() > 0) {
            return redirect()->back()
                ->withErrors(['unit' => 'Cannot delete unit with positions. Please reassign or delete positions first.']);
        }

        $organizationUnit->delete();

        return redirect()->route('organization-units.index')
            ->with('success', 'Organization unit deleted successfully.');
    }

    public function governance()
    {
        $units = OrganizationUnit::governance()
            ->with(['organization', 'parentUnit', 'positions.activeMemberships.user'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('OrganizationUnits/Governance', [
            'units' => $units,
        ]);
    }

    public function operational()
    {
        $units = OrganizationUnit::operational()
            ->with(['organization', 'parentUnit', 'positions.activeMemberships.user'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('OrganizationUnits/Operational', [
            'units' => $units,
        ]);
    }
}
