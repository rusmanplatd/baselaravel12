<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::query()
            ->withCount(['departments', 'organizationUnits', 'childOrganizations'])
            ->with(['parentOrganization'])
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create()
    {
        $parentOrganizations = Organization::where('organization_type', '!=', 'unit')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_type']);

        return Inertia::render('Organizations/Create', [
            'parentOrganizations' => $parentOrganizations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_code' => 'nullable|string|unique:organizations',
            'name' => 'required|string|max:255',
            'organization_type' => 'required|in:holding_company,subsidiary,division,branch,department,unit',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $organization = Organization::create($validated);
        
        if ($organization->parent_organization_id) {
            $organization->updatePath();
        }

        return redirect()->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization)
    {
        $organization->load([
            'departments.jobPositions.jobLevel',
            'parentOrganization',
            'childOrganizations',
            'organizationUnits.positions.activeMemberships.user',
            'memberships.user.organizationPosition'
        ]);

        return Inertia::render('Organizations/Show', [
            'organization' => $organization,
        ]);
    }

    public function edit(Organization $organization)
    {
        $parentOrganizations = Organization::where('organization_type', '!=', 'unit')
            ->where('id', '!=', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'organization_type']);

        return Inertia::render('Organizations/Edit', [
            'organization' => $organization,
            'parentOrganizations' => $parentOrganizations,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'organization_code' => 'nullable|string|unique:organizations,organization_code,' . $organization->id,
            'name' => 'required|string|max:255',
            'organization_type' => 'required|in:holding_company,subsidiary,division,branch,department,unit',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['parent_organization_id']) && $validated['parent_organization_id'] == $organization->id) {
            return redirect()->back()
                ->withErrors(['parent_organization_id' => 'Organization cannot be its own parent']);
        }

        $oldParentId = $organization->parent_organization_id;
        $organization->update($validated);
        
        if ($oldParentId !== $organization->parent_organization_id) {
            $organization->updatePath();
        }

        return redirect()->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        if ($organization->childOrganizations()->count() > 0) {
            return redirect()->back()
                ->withErrors(['organization' => 'Cannot delete organization with child organizations. Please reassign or delete child organizations first.']);
        }

        if ($organization->organizationUnits()->count() > 0) {
            return redirect()->back()
                ->withErrors(['organization' => 'Cannot delete organization with organizational units. Please delete units first.']);
        }

        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }

    public function hierarchy()
    {
        $organizations = Organization::whereNull('parent_organization_id')
            ->with(['childOrganizations' => function ($query) {
                $this->loadHierarchy($query);
            }])
            ->orderBy('name')
            ->get();

        return Inertia::render('Organizations/Hierarchy', [
            'organizations' => $organizations,
        ]);
    }

    private function loadHierarchy($query)
    {
        $query->with(['childOrganizations' => function ($subQuery) {
            $this->loadHierarchy($subQuery);
        }])->orderBy('name');
    }
}
