<?php

namespace App\Http\Controllers;

use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationPositionController extends Controller
{
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_unit_id' => 'required|exists:organization_units,id',
            'position_code' => 'required|string|unique:organization_positions',
            'title' => 'required|string|max:255',
            'position_level' => 'required|in:board_member,c_level,vice_president,director,senior_manager,manager,assistant_manager,supervisor,senior_staff,staff,junior_staff',
            'job_description' => 'nullable|string',
            'qualifications' => 'nullable|array',
            'responsibilities' => 'nullable|array',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
            'max_incumbents' => 'integer|min:1',
        ]);

        OrganizationPosition::create($validated);

        return redirect()->route('organization-positions.index')
            ->with('success', 'Organization position created successfully.');
    }

    public function show(OrganizationPosition $organizationPosition)
    {
        $organizationPosition->load([
            'organizationUnit.organization',
            'activeMemberships.user',
            'memberships.user'
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

    public function update(Request $request, OrganizationPosition $organizationPosition)
    {
        $validated = $request->validate([
            'position_code' => 'required|string|unique:organization_positions,position_code,' . $organizationPosition->id,
            'title' => 'required|string|max:255',
            'position_level' => 'required|in:board_member,c_level,vice_president,director,senior_manager,manager,assistant_manager,supervisor,senior_staff,staff,junior_staff',
            'job_description' => 'nullable|string',
            'qualifications' => 'nullable|array',
            'responsibilities' => 'nullable|array',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'boolean',
            'max_incumbents' => 'integer|min:1',
        ]);

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