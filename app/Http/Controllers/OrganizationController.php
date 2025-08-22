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
            ->withCount('departments')
            ->paginate(10);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create()
    {
        return Inertia::render('Organizations/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        Organization::create($validated);

        return redirect()->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization)
    {
        $organization->load(['departments.jobPositions.jobLevel']);

        return Inertia::render('Organizations/Show', [
            'organization' => $organization,
        ]);
    }

    public function edit(Organization $organization)
    {
        return Inertia::render('Organizations/Edit', [
            'organization' => $organization,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $organization->update($validated);

        return redirect()->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}
